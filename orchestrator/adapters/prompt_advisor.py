"""Prompt Advisor — transforms story context into asset-generation prompts.

Provides:
- Character prompts
- Environment prompts
- Storyboard prompts
- Style recommendations
"""
from __future__ import annotations

import logging
import os
from typing import Any

from api_client import simple_chat

logger = logging.getLogger(__name__)

SYSTEM_PROMPT = """You are the Prompt Advisor for StoryOS, a collaborative storytelling platform.

Your role is to transform story context into detailed, effective prompts for
AI asset generation (images, storyboards, concept art).

You have access to:
- Story Graph context (characters, locations, scenes)
- Visual style references
- Character descriptions and appearances
- Scene descriptions and shot types

Guidelines:
- Create detailed, specific prompts optimized for Stable Diffusion/ComfyUI
- Include visual details: lighting, composition, style, mood
- Maintain consistency with established character designs and worldbuilding
- Use appropriate terminology for AI image generation
- Suggest negative prompts to avoid common issues
- Consider shot types (close-up, wide, etc.) for storyboards
- Reference visual style when available

Output format should be ready to use in ComfyUI workflow templates.
"""


class PromptAdvisor:
    """Asset generation prompt advisor agent."""

    def __init__(self, name: str = "prompt_advisor"):
        self.name = name
        self.role = "Prompt Advisor"
        self.system_prompt = SYSTEM_PROMPT

    def generate_character_prompt(
        self,
        character: dict[str, Any],
        style_reference: str = "",
        shot_type: str = "full body",
    ) -> dict[str, str]:
        """Generate a character generation prompt.

        Args:
            character: Character data with name, description, traits
            style_reference: Style description or reference path
            shot_type: Type of shot (close-up, full body, etc.)

        Returns:
            Dict with 'positive' and 'negative' prompt strings
        """
        positive = self._build_character_positive(character, style_reference, shot_type)
        negative = self._build_negative_prompt(style_reference)

        return {
            "positive": positive,
            "negative": negative,
            "shot_type": shot_type,
        }

    def generate_environment_prompt(
        self,
        location: dict[str, Any],
        style_reference: str = "",
        mood: str = "atmospheric",
    ) -> dict[str, str]:
        """Generate an environment/concept art prompt.

        Args:
            location: Location data with description, visual features
            style_reference: Style description or reference path
            mood: Desired mood (atmospheric, bright, dark, etc.)

        Returns:
            Dict with 'positive' and 'negative' prompt strings
        """
        positive = self._build_environment_positive(location, style_reference, mood)
        negative = self._build_negative_prompt(style_reference)

        return {
            "positive": positive,
            "negative": negative,
            "mood": mood,
        }

    def generate_storyboard_prompt(
        self,
        scene: dict[str, Any],
        shot: dict[str, Any],
        characters: list[dict[str, Any]] = None,
    ) -> dict[str, str]:
        """Generate a storyboard frame prompt.

        Args:
            scene: Scene description and context
            shot: Shot details (type, angle, movement)
            characters: Characters in the shot

        Returns:
            Dict with 'positive' and 'negative' prompt strings
        """
        characters = characters or []
        positive = self._build_storyboard_positive(scene, shot, characters)
        negative = self._build_negative_prompt()

        return {
            "positive": positive,
            "negative": negative,
            "shot_type": shot.get("type", "medium"),
            "shot_angle": shot.get("angle", "eye-level"),
        }

    def suggest_style(
        self,
        project_context: dict[str, Any],
        genre: str = "fantasy",
    ) -> str:
        """Suggest visual style for the project.

        Args:
            project_context: Project metadata and goals
            genre: Project genre

        Returns:
            Style recommendation text
        """
        prompt = f"""Project Context:
{self._format_context(project_context)}

Genre: {genre}

Suggest a visual style direction for this project. Consider:
- Art style (realistic, stylized, painterly, etc.)
- Color palette
- Lighting approach
- Composition style
- References to existing media

Provide specific, actionable style guidance."""

        return self._call_model(prompt)

    def refine_prompt(
        self,
        draft_prompt: str,
        feedback: str,
        target_medium: str = "stable_diffusion",
    ) -> str:
        """Refine a draft prompt based on feedback.

        Args:
            draft_prompt: Current draft prompt
            feedback: Feedback on what to improve
            target_medium: Target AI model (stable_diffusion, etc.)

        Returns:
            Refined prompt
        """
        prompt = f"""Draft Prompt:
"{draft_prompt}"

Feedback:
{feedback}

Target Medium: {target_medium}

Refine this prompt based on the feedback. Make it more effective for
AI image generation. Be specific about visual details."""

        return self._call_model(prompt)

    def _build_character_positive(
        self,
        character: dict[str, Any],
        style_reference: str,
        shot_type: str,
    ) -> str:
        """Build positive prompt for character generation."""
        parts = []

        # Shot type and composition
        parts.append(f"{shot_type} shot")

        # Character appearance
        name = character.get("name", "character")
        description = character.get("description", "")
        traits = character.get("traits", "")
        appearance = character.get("appearance", "")

        if appearance:
            parts.append(appearance)
        elif description:
            parts.append(description)

        if traits:
            parts.append(f"expressing {traits}")

        # Style reference
        if style_reference:
            parts.append(f"style: {style_reference}")

        # Quality tags
        parts.extend([
            "high quality",
            "detailed",
            "professional illustration",
        ])

        return ", ".join(parts)

    def _build_environment_positive(
        self,
        location: dict[str, Any],
        style_reference: str,
        mood: str,
    ) -> str:
        """Build positive prompt for environment generation."""
        parts = []

        # Mood
        parts.append(f"{mood} atmosphere")

        # Location description
        description = location.get("description", "")
        features = location.get("visual_features", "")
        name = location.get("name", "environment")

        if description:
            parts.append(description)
        if features:
            parts.append(features)

        # Style reference
        if style_reference:
            parts.append(f"style: {style_reference}")

        # Quality tags
        parts.extend([
            "wide shot",
            "detailed environment",
            "concept art",
            "professional illustration",
        ])

        return ", ".join(parts)

    def _build_storyboard_positive(
        self,
        scene: dict[str, Any],
        shot: dict[str, Any],
        characters: list[dict[str, Any]],
    ) -> str:
        """Build positive prompt for storyboard generation."""
        parts = []

        # Shot details
        shot_type = shot.get("type", "medium shot")
        shot_angle = shot.get("angle", "eye-level")
        parts.append(f"{shot_type}, {shot_angle} angle")

        # Scene description
        scene_desc = scene.get("description", "")
        if scene_desc:
            parts.append(scene_desc)

        # Characters in shot
        if characters:
            char_descs = []
            for char in characters[:3]:  # Limit to 3 characters
                name = char.get("name", "")
                appearance = char.get("appearance", "")
                if name and appearance:
                    char_descs.append(f"{name} ({appearance})")
                elif name:
                    char_descs.append(name)
            if char_descs:
                parts.append(f"featuring {' and '.join(char_descs)}")

        # Storyboard style
        parts.extend([
            "storyboard frame",
            "clear composition",
            "narrative clarity",
        ])

        return ", ".join(parts)

    def _build_negative_prompt(self, style_reference: str = "") -> str:
        """Build standard negative prompt."""
        parts = [
            "low quality",
            "blurry",
            "deformed",
            "disfigured",
            "bad anatomy",
            "extra limbs",
            "poorly drawn face",
            "mutated hands",
        ]

        if style_reference:
            parts.append(f"not matching style: {style_reference}")

        return ", ".join(parts)

    def _call_model(self, prompt: str) -> str:
        """Call the local model via API client."""
        try:
            response = simple_chat(
                system_prompt=self.system_prompt,
                user_message=prompt,
            )
            logger.info(f"PromptAdvisor response generated for: {prompt[:50]}...")
            return response
        except Exception as e:
            error_msg = f"PromptAdvisor error: {str(e)}"
            logger.error(error_msg)
            return f"Error generating response: {str(e)}"

    def _format_context(self, context: dict[str, Any]) -> str:
        """Format context for display."""
        lines = []
        for key, value in context.items():
            if isinstance(value, (list, dict)):
                lines.append(f"## {key.replace('_', ' ').title()}")
                lines.append(str(value))
            else:
                lines.append(f"{key.replace('_', ' ').title()}: {value}")
        return "\n\n".join(lines)
