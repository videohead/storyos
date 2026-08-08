"""Story Graph context builder.

Queries WordPress REST API to fetch CPT data and SCF fields,
then builds a context dict for workflow template rendering.
"""

from __future__ import annotations

import logging
import os
import time
from typing import Any, Optional

import requests

logger = logging.getLogger(__name__)


class WordPressAPIError(Exception):
    """Raised when a WordPress API call fails."""


class StoryGraphContextBuilder:
    """Builds generation context from WordPress Story Graph data."""

    def __init__(
        self,
        wordpress_url: str,
        username: str,
        app_password: str,
        timeout: int = 30,
    ):
        self.base_url = wordpress_url.rstrip("/")
        self.username = username
        self.app_password = app_password
        self.timeout = timeout
        self._cache: dict[str, tuple[float, dict[str, Any]]] = {}
        self._cache_ttl = int(os.getenv("CACHE_TTL", "300"))  # seconds (Optimization 3)

    def _auth(self):
        return (self.username, self.app_password)

    def _get(self, endpoint: str, params: Optional[dict] = None) -> dict[str, Any]:
        """Make a GET request to the WordPress REST API."""
        url = f"{self.base_url}/wp-json/wp/v2/{endpoint}"
        try:
            resp = requests.get(
                url,
                auth=self._auth(),
                params=params,
                timeout=self.timeout,
            )
            resp.raise_for_status()
            return resp.json()
        except requests.exceptions.RequestException as e:
            raise WordPressAPIError(f"WordPress GET {endpoint} failed: {e}")

    def _get_post(self, post_type: str, post_id: int) -> dict[str, Any]:
        """Get a single post by ID."""
        return self._get(f"{post_type}/{post_id}")

    def _get_media(self, media_id: int) -> dict[str, Any]:
        """Get media item details."""
        return self._get(f"media/{media_id}")

    def _download_media(self, media_url: str) -> Optional[str]:
        """Download media file to a local temp path. Returns the path or None."""
        try:
            resp = requests.get(media_url, timeout=self.timeout)
            resp.raise_for_status()
            import tempfile

            tmp = tempfile.NamedTemporaryFile(delete=False, suffix=".png")
            tmp.write(resp.content)
            tmp.close()
            logger.info("Downloaded media to %s", tmp.name)
            return tmp.name
        except Exception as e:
            logger.warning("Failed to download media %s: %s", media_url, e)
            return None

    def _cached_get(self, key: str, fetch_func) -> dict[str, Any]:
        """Fetch with simple TTL-based caching."""
        now = time.time()
        if key in self._cache:
            cached_time, data = self._cache[key]
            if now - cached_time < self._cache_ttl:
                return data

        data = fetch_func()
        self._cache[key] = (now, data)
        return data

    def build_for_post(
        self, post_id: int, entity_type: str = "post"
    ) -> dict[str, Any]:
        """Build generation context from a WordPress post.

        Reads SCF/custom fields from the post and related entities
        to create a context dict suitable for workflow template rendering.
        """
        # Fetch the post
        post = self._get_post(entity_type, post_id)

        meta = post.get("meta", {}) or {}

        # Build base context from post data
        context: dict[str, Any] = {
            "post_id": post_id,
            "entity_type": entity_type,
            "post_title": post.get("title", ""),
            "post_content": post.get("content", {}).get("rendered", ""),
            "positive_prompt": meta.get("positive_prompt", ""),
            "negative_prompt": meta.get("negative_prompt", ""),
            "seed": int(meta.get("seed", 42)),
            "steps": int(meta.get("steps", 20)),
            "cfg": float(meta.get("cfg", 8.0)),
            "resolution_x": int(meta.get("resolution_x", 1024)),
            "resolution_y": int(meta.get("resolution_y", 1024)),
            "fps": int(meta.get("fps", 24)),
        }

        # Resolve reference images from media IDs
        style_media_id = meta.get("style_image")
        if style_media_id:
            style_url = self._get_media_url(style_media_id)
            if style_url:
                context["style_ref_path"] = self._download_media(style_url)

        pose_media_id = meta.get("pose_example")
        if pose_media_id:
            pose_url = self._get_media_url(pose_media_id)
            if pose_url:
                context["pose_ref_path"] = self._download_media(pose_url)

        return context

    def build_for_character(
        self, character_id: int, scene_id: Optional[int] = None
    ) -> dict[str, Any]:
        """Build context for character sheet generation."""
        char_data = self._get_post("characters", character_id)

        meta = char_data.get("meta", {}) or {}

        context: dict[str, Any] = {
            "entity_type": "character",
            "entity_id": character_id,
            "character_name": char_data.get("title", {}).get("rendered", ""),
            "character_appearance": meta.get("appearance", ""),
            "character_biography": meta.get("biography", ""),
            "positive_prompt": meta.get(
                "positive_prompt",
                f"Character sheet of {char_data.get('title', {}).get('rendered', '')}",
            ),
            "negative_prompt": meta.get("negative_prompt", ""),
            "seed": int(meta.get("seed", 42)),
            "steps": int(meta.get("steps", 20)),
            "cfg": float(meta.get("cfg", 8.0)),
            "resolution_x": int(meta.get("resolution_x", 1024)),
            "resolution_y": int(meta.get("resolution_y", 1024)),
        }

        # Resolve avatar/visual references
        avatar_id = meta.get("avatar_asset")
        if avatar_id:
            avatar_url = self._get_media_url(avatar_id)
            if avatar_url:
                context["visual_ref_path"] = self._download_media(avatar_url)

        # If scene_id provided, enrich with scene context
        if scene_id:
            scene_context = self.build_for_scene(scene_id)
            context.update(scene_context)

        return context

    def build_for_scene(
        self, scene_id: int, include_location: bool = True
    ) -> dict[str, Any]:
        """Build context for scene/storyboard generation."""
        scene_data = self._get_post("scenes", scene_id)

        meta = scene_data.get("meta", {}) or {}

        context: dict[str, Any] = {
            "entity_type": "scene",
            "entity_id": scene_id,
            "scene_number": meta.get("scene_number", ""),
            "scene_title": scene_data.get("title", {}).get("rendered", ""),
            "scene_description": meta.get("summary", ""),
            "shot_number": meta.get("shot_number", ""),
            "shot_type": meta.get("shot_type", ""),
            "positive_prompt": meta.get(
                "positive_prompt",
                f"Scene {meta.get('scene_number', '')}: {scene_data.get('title', {}).get('rendered', '')}",
            ),
            "negative_prompt": meta.get("negative_prompt", ""),
            "seed": int(meta.get("seed", 42)),
            "steps": int(meta.get("steps", 20)),
            "cfg": float(meta.get("cfg", 8.0)),
            "resolution_x": int(meta.get("resolution_x", 1024)),
            "resolution_y": int(meta.get("resolution_y", 1024)),
        }

        # Enrich with location data
        if include_location:
            location_id = meta.get("location")
            if location_id:
                location_context = self.build_for_location(location_id)
                context.update(location_context)

        return context

    def build_for_location(self, location_id: int) -> dict[str, Any]:
        """Build context for location/environment generation."""
        loc_data = self._get_post("locations", location_id)

        meta = loc_data.get("meta", {}) or {}

        context: dict[str, Any] = {
            "entity_type": "location",
            "entity_id": location_id,
            "location_name": loc_data.get("title", {}).get("rendered", ""),
            "location_description": meta.get("description", ""),
            "location_environment_type": meta.get("environment_type", ""),
            "location_mood": meta.get("mood", ""),
            "positive_prompt": meta.get(
                "positive_prompt",
                f"Environment of {loc_data.get('title', {}).get('rendered', '')}",
            ),
            "negative_prompt": meta.get("negative_prompt", ""),
            "seed": int(meta.get("seed", 42)),
            "steps": int(meta.get("steps", 20)),
            "cfg": float(meta.get("cfg", 8.0)),
            "resolution_x": int(meta.get("resolution_x", 1024)),
            "resolution_y": int(meta.get("resolution_y", 1024)),
        }

        # Resolve visual reference
        ref_id = meta.get("visual_reference")
        if ref_id:
            ref_url = self._get_media_url(ref_id)
            if ref_url:
                context["visual_ref_path"] = self._download_media(ref_url)

        return context

    def _get_media_url(self, media_id: int) -> Optional[str]:
        """Get the full URL for a media item."""
        try:
            media = self._get_media(media_id)
            return media.get("source_url")
        except Exception as e:
            logger.warning("Failed to get media %s: %s", media_id, e)
            return None

    def clear_cache(self):
        """Clear the internal cache."""
        self._cache.clear()

    def cleanup_temp_files(self):
        """Remove any temporary files downloaded during context building.

        NOTE: This is a placeholder for a more robust temp file management
        system. In production, use a proper temp directory with cleanup.
        """
        import glob
        import os

        # This is a simple approach — in production, use tempfile.gettempdir()
        # and track downloaded files in a registry.
        logger.info("Temp file cleanup placeholder — implement as needed")
