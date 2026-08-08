"""Asset lineage tracking for StoryOS orchestrator.

Tracks every generated asset with full provenance:
- Source scene/post
- Workflow template used
- Prompt ID from ComfyUI
- All generation parameters
- Output media URL
- Timestamp
"""

from __future__ import annotations

import logging
import time
from typing import Any, Optional

import requests

logger = logging.getLogger(__name__)


class WordPressAssetError(Exception):
    """Raised when asset creation/update in WordPress fails."""


class AssetLineage:
    """Tracks and manages asset lineage in WordPress."""

    def __init__(self, wordpress_url: str, wordpress_user: str, wordpress_password: str):
        self.wordpress_url = wordpress_url.rstrip("/")
        self.wordpress_user = wordpress_user
        self.wordpress_password = wordpress_password
        self._token: Optional[str] = None

    def _authenticate(self) -> str:
        """Get WordPress JWT or application password.

        Uses application passwords (WordPress 5.6+) if available.
        Returns the auth token string.
        """
        if self._token:
            return self._token

        try:
            # Try application password auth
            resp = requests.get(
                f"{self.wordpress_url}/wp-json/",
                auth=(self.wordpress_user, self.wordpress_password),
                timeout=10,
            )
            if resp.ok:
                self._token = f"Basic {self.wordpress_user}:{self.wordpress_password}"
                logger.info("Authenticated with WordPress application passwords")
                return self._token

        except Exception as e:
            logger.warning("Application password auth failed: %s", e)

        # Fallback: try JWT
        try:
            resp = requests.post(
                f"{self.wordpress_url}/wp-login.php",
                data={
                    "log": self.wordpress_user,
                    "pwd": self.wordpress_password,
                    "wp-submit": "Log+In",
                },
                timeout=10,
            )
            if resp.ok and "wp-login" not in resp.url:
                self._token = f"Cookie: {resp.cookies.get('wordpress_sec', '')}"
                logger.info("Authenticated with WordPress cookies")
                return self._token

        except Exception as e:
            logger.warning("Cookie auth failed: %s", e)

        raise WordPressAssetError("Failed to authenticate with WordPress")

    def _get_headers(self) -> dict[str, str]:
        """Get headers for WordPress API requests."""
        token = self._authenticate()
        return {
            "Content-Type": "application/json",
            "Authorization": token,
        }

    def _get_media_dir(self) -> dict[str, Any]:
        """Get WordPress upload directory info."""
        try:
            resp = requests.get(
                f"{self.wordpress_url}/wp-json/wp/v2/settings",
                headers=self._get_headers(),
                timeout=10,
            )
            if resp.ok:
                return resp.json()
        except Exception as e:
            logger.warning("Failed to get WordPress settings: %s", e)

        return {"upload_url": f"{self.wordpress_url}/wp-content/uploads", "upload_path": ""}

    def create_asset(
        self,
        source_post_id: int,
        source_type: str,
        workflow_template: str,
        prompt_id: Optional[str],
        generation_params: dict[str, Any],
        output_media_url: str,
        output_media_id: Optional[int] = None,
        post_id: Optional[int] = None,
    ) -> dict[str, Any]:
        """Create an Asset CPT in WordPress with full provenance.

        Args:
            source_post_id: ID of the source WordPress post/scene
            source_type: Type of source (scene, character, location, etc.)
            workflow_template: Name of the workflow template used
            prompt_id: ComfyUI prompt ID for traceability
            generation_params: All generation parameters (seed, steps, cfg, etc.)
            output_media_url: URL of the generated media in WordPress
            output_media_id: WordPress media ID (if already uploaded)
            post_id: Existing asset post ID to update (for re-generation)

        Returns:
            WordPress post creation/update response
        """
        headers = self._get_headers()
        now = time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime())

        # Build the asset post data
        asset_post = {
            "post_title": f"Asset: {source_type} #{source_post_id}",
            "post_status": "publish",
            "post_type": "asset",
            "meta_input": {
                # Provenance tracking
                "_source_post_id": source_post_id,
                "_source_type": source_type,
                "_workflow_template": workflow_template,
                "_prompt_id": prompt_id,
                "_generation_params": generation_params,
                "_output_media_url": output_media_url,
                "_output_media_id": output_media_id,
                "_created_at": now,
                "_updated_at": now,

                # Asset metadata
                "_asset_type": "generated",
                "_generation_tool": "StoryOS Orchestrator",
            },
        }

        # If updating existing asset
        if post_id:
            try:
                resp = requests.put(
                    f"{self.wordpress_url}/wp-json/wp/v2/assets/{post_id}",
                    json=asset_post,
                    headers=headers,
                    timeout=30,
                )
                if resp.ok:
                    logger.info("Updated asset post %d for source %s #%d", post_id, source_type, source_post_id)
                    return resp.json()
                else:
                    logger.error("Failed to update asset: %s", resp.text[:200])
                    raise WordPressAssetError(f"WordPress update failed: {resp.status_code}")
            except Exception as e:
                logger.error("Error updating asset: %s", e)
                raise

        # Create new asset
        try:
            resp = requests.post(
                f"{self.wordpress_url}/wp-json/wp/v2/assets",
                json=asset_post,
                headers=headers,
                timeout=30,
            )
            if resp.ok:
                created_post = resp.json()
                logger.info(
                    "Created asset post %d for source %s #%d (media: %s)",
                    created_post.get("id"),
                    source_type,
                    source_post_id,
                    output_media_url,
                )
                return created_post
            else:
                logger.error("Failed to create asset: %s", resp.text[:200])
                raise WordPressAssetError(f"WordPress create failed: {resp.status_code}")

        except Exception as e:
            logger.error("Error creating asset: %s", e)
            raise

    def upload_media(
        self,
        file_path: str,
        title: str,
        source_post_id: int,
        post_id: Optional[int] = None,
    ) -> dict[str, Any]:
        """Upload media file to WordPress and return media info.

        Args:
            file_path: Local path to the file to upload
            title: Media title
            source_post_id: Source post ID for association
            post_id: Existing media ID to update

        Returns:
            WordPress media item response
        """
        headers = self._get_headers()

        try:
            with open(file_path, "rb") as f:
                files = {"file": (title, f, "image/png")}
                data = {"post_title": title}

                if post_id:
                    resp = requests.post(
                        f"{self.wordpress_url}/wp-json/wp/v2/media/{post_id}",
                        headers=headers,
                        files=files,
                        timeout=60,
                    )
                else:
                    resp = requests.post(
                        f"{self.wordpress_url}/wp-json/wp/v2/media",
                        headers=headers,
                        files=files,
                        timeout=60,
                    )

            if resp.ok:
                media = resp.json()
                logger.info("Uploaded media %d: %s", media.get("id"), title)
                return media
            else:
                logger.error("Failed to upload media: %s", resp.text[:200])
                raise WordPressAssetError(f"Media upload failed: {resp.status_code}")

        except Exception as e:
            logger.error("Error uploading media: %s", e)
            raise

    def get_asset(self, post_id: int) -> Optional[dict[str, Any]]:
        """Get an asset by post ID."""
        try:
            resp = requests.get(
                f"{self.wordpress_url}/wp-json/wp/v2/assets/{post_id}",
                headers=self._get_headers(),
                timeout=10,
            )
            if resp.ok:
                return resp.json()
            return None
        except Exception as e:
            logger.warning("Failed to get asset %d: %s", post_id, e)
            return None

    def list_assets(
        self,
        source_post_id: Optional[int] = None,
        source_type: Optional[str] = None,
        workflow_template: Optional[str] = None,
        per_page: int = 20,
        page: int = 1,
    ) -> list[dict[str, Any]]:
        """List assets with optional filters."""
        try:
            params = {
                "per_page": per_page,
                "page": page,
                "type": "asset",
            }

            if source_post_id:
                params["meta_query"] = [
                    {
                        "key": "_source_post_id",
                        "value": source_post_id,
                    }
                ]

            resp = requests.get(
                f"{self.wordpress_url}/wp-json/wp/v2/assets",
                headers=self._get_headers(),
                params=params,
                timeout=10,
            )
            if resp.ok:
                return resp.json()
            return []

        except Exception as e:
            logger.warning("Failed to list assets: %s", e)
            return []

    def update_asset_status(
        self,
        post_id: int,
        status: str,
        metadata: Optional[dict[str, Any]] = None,
    ) -> bool:
        """Update asset status (pending, processing, done, error)."""
        try:
            headers = self._get_headers()
            update_data = {
                "meta_input": {
                    "_status": status,
                    "_updated_at": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime()),
                }
            }

            if metadata:
                update_data["meta_input"].update(metadata)

            resp = requests.put(
                f"{self.wordpress_url}/wp-json/wp/v2/assets/{post_id}",
                json=update_data,
                headers=headers,
                timeout=10,
            )
            if resp.ok:
                logger.info("Updated asset %d status to %s", post_id, status)
                return True
            return False

        except Exception as e:
            logger.error("Error updating asset status: %s", e)
            return False
