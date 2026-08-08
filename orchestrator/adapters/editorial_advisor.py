"""Editorial Advisor — quality review and content curation.

Provides:
- Asset quality review
- Content curation
- Style consistency checks
- Editorial recommendations
"""
from __future__ import annotations

import logging
import os
from typing import Any

from api_client import simple_chat

logger = logging.getLogger(__name__)

SYSTEM_PROMPT = """You are the Editorial Advisor for StoryOS, a collaborative storytelling platform.

Your role is to assist with quality review, content curation, and editorial
decisions while respecting that humans make final creative decisions.

You have access to:
- Generated assets (images, storyboards, concept art)
- Story context and style guides
- Character and location descriptions
- Production requirements

Guidelines:
- Provide constructive quality feedback
- Identify style inconsistencies
- Suggest improvements for visual coherence
- Review assets against story requirements
- Never reject work definitively — recommend and explain
- Be specific about visual quality and narrative fit
"""


class EditorialAdvisor:
    """Editorial quality review advisor agent."""

    def __init__(self, name: str = "editorial_advisor"):
        self.name = name
        self.role = "Editorial Advisor"
        self.system_prompt = SYSTEM_PROMPT

    def review_asset_quality(
        self,
        asset: dict[str, Any],
        quality_criteria: dict[str, Any] = None,
    ) -> str:
        """Review an asset's quality.

        Args:
            asset: Asset data with metadata and generation params
            quality_criteria: Quality standards to evaluate against

        Returns:
            Quality review feedback
        """
        criteria = quality_criteria or self._default_criteria()
        prompt = f"""Asset Details:
{self._format_asset(asset)}

Quality Criteria:
{self._format_criteria(criteria)}

Review this asset for:
- Technical quality (resolution, clarity, artifacts)
- Visual composition and aesthetics
- Consistency with style guidelines
- Narrative appropriateness

Provide specific, actionable feedback."""

        return self._call_model(prompt)

    def check_style_consistency(
        self,
        assets: list[dict[str, Any]],
        style_guide: dict[str, Any],
    ) -> str:
        """Check consistency across multiple assets.

        Args:
            assets: List of generated assets
            style_guide: Style guidelines and references

        Returns:
            Consistency report
        """
        prompt = f"""Assets to Review:
{self._format_assets(assets)}

Style Guide:
{self._format_style_guide(style_guide)}

Check these assets for:
- Consistent art style across all assets
- Character appearance consistency
- Color palette consistency
- Lighting and mood consistency
- Overall visual coherence

Identify any assets that deviate from the style guide."""

        return self._call_model(prompt)

    def curate_assets(
        self,
        asset_pool: list[dict[str, Any]],
        selection_criteria: dict[str, Any],
    ) -> str:
        """Curate and select best assets from a pool.

        Args:
            asset_pool: Pool of generated assets
            selection_criteria: Criteria for selection

        Returns:
            Curation recommendations
        """
        prompt = f"""Asset Pool:
{self._format_assets(asset_pool)}

Selection Criteria:
{self._format_criteria(selection_criteria)}

From this pool, recommend:
- Top selections for each required asset
- Assets that meet all criteria
- Assets that need revision
- Any gaps in the asset collection"""

        return self._call_model(prompt)

    def evaluate_narrative_fit(
        self,
        asset: dict[str, Any],
        scene_context: dict[str, Any],
    ) -> str:
        """Evaluate if an asset fits its narrative context.

        Args:
            asset: Asset data
            scene_context: Scene description and requirements

        Returns:
            Narrative fit assessment
        """
        prompt = f"""Asset:
{self._format_asset(asset)}

Scene Context:
{self._format_scene(scene_context)}

Evaluate whether this asset:
- Accurately represents the scene description
- Conveys the intended emotion/mood
- Shows correct characters and props
- Fits the overall narrative tone

Provide specific feedback on narrative alignment."""

        return self._call_model(prompt)

    def _default_criteria(self) -> dict[str, Any]:
        """Return default quality criteria."""
        return {
            "technical_quality": [
                "No visible artifacts or glitches",
                "Appropriate resolution for production",
                "Clean lines and clear details",
                "Proper lighting and exposure",
            ],
            "visual_quality": [
                "Strong composition",
                "Appropriate style for project",
                "Visually engaging",
                "Professional quality",
            ],
            "narrative_quality": [
                "Accurate to story context",
                "Conveys intended emotion",
                "Character consistency",
                "Worldbuilding consistency",
            ],
        }

    def _call_model(self, prompt: str) -> str:
        """Call the local model via API client."""
        try:
            response = simple_chat(
                system_prompt=self.system_prompt,
                user_message=prompt,
            )
            logger.info(f"EditorialAdvisor response generated for: {prompt[:50]}...")
            return response
        except Exception as e:
            error_msg = f"EditorialAdvisor error: {str(e)}"
            logger.error(error_msg)
            return f"Error generating response: {str(e)}"

    def _format_asset(self, asset: dict[str, Any]) -> str:
        """Format asset for display."""
        lines = [
            f"ID: {asset.get('id', 'Unknown')}",
            f"Type: {asset.get('type', 'Unknown')}",
            f"Source: {asset.get('source', 'Unknown')}",
            f"Status: {asset.get('status', 'Unknown')}",
            f"Description: {asset.get('description', 'N/A')}",
        ]
        return "\n".join(lines)

    def _format_assets(self, assets: list[dict[str, Any]]) -> str:
        """Format asset list for display."""
        lines = []
        for i, asset in enumerate(assets, 1):
            lines.append(
                f"{i}. {asset.get('type', 'Asset')} - "
                f"{asset.get('description', 'No description')}"
            )
        return "\n".join(lines)

    def _format_criteria(self, criteria: dict[str, Any]) -> str:
        """Format criteria for display."""
        lines = []
        for category, items in criteria.items():
            lines.append(f"## {category.replace('_', ' ').title()}")
            if isinstance(items, list):
                for item in items:
                    lines.append(f"- {item}")
            else:
                lines.append(str(items))
        return "\n\n".join(lines)

    def _format_style_guide(self, style_guide: dict[str, Any]) -> str:
        """Format style guide for display."""
        lines = []
        for key, value in style_guide.items():
            if isinstance(value, (list, dict)):
                lines.append(f"## {key.replace('_', ' ').title()}")
                lines.append(str(value))
            else:
                lines.append(f"{key.replace('_', ' ').title()}: {value}")
        return "\n\n".join(lines)

    def _format_scene(self, scene: dict[str, Any]) -> str:
        """Format scene for display."""
        lines = [
            f"Scene: {scene.get('name', 'Unknown')}",
            f"Description: {scene.get('description', 'N/A')}",
            f"Mood: {scene.get('mood', 'N/A')}",
            f"Characters: {', '.join(scene.get('characters', []))}",
        ]
        return "\n".join(lines)
