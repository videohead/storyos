"""Story Advisor — narrative development assistance.

Provides guidance on:
- Story analysis
- Character review
- Plot consistency
- Worldbuilding support
- Story arc analysis
"""
from __future__ import annotations

import logging
import os
from typing import Any

from api_client import simple_chat

logger = logging.getLogger(__name__)

SYSTEM_PROMPT = """You are the Story Advisor for StoryOS, a collaborative storytelling platform.

Your role is to assist creators with narrative development while respecting that
humans make final creative decisions.

You have access to:
- The Story Graph (canonical story context)
- Character descriptions and arcs
- Scene descriptions and plot flow
- Worldbuilding notes

Guidelines:
- Provide constructive feedback and suggestions
- Identify plot holes, inconsistencies, or pacing issues
- Suggest character development opportunities
- Maintain awareness of the overall story arc
- Never override creator decisions — recommend and explain
- Be specific and actionable in your feedback

When analyzing story elements, reference specific scenes, characters, or plot points.
"""


class StoryAdvisor:
    """Narrative development advisor agent."""

    def __init__(self, name: str = "story_advisor"):
        self.name = name
        self.role = "Story Advisor"
        self.system_prompt = SYSTEM_PROMPT

    def analyze_story(
        self,
        story_context: dict[str, Any],
        question: str,
    ) -> str:
        """Analyze story elements and provide feedback.

        Args:
            story_context: Dict with keys like characters, scenes, worldbuilding
            question: Specific question or analysis request

        Returns:
            Analysis response from the model
        """
        prompt = f"""Story Context:
{self._format_context(story_context)}

Question: {question}

Provide a thorough analysis focusing on narrative coherence, character development,
and plot consistency."""

        return self._call_model(prompt)

    def review_character(
        self,
        character_data: dict[str, Any],
        scene_context: list[dict[str, Any]],
    ) -> str:
        """Review a character's development across scenes.

        Args:
            character_data: Character description, traits, arc
            scene_context: List of scenes the character appears in

        Returns:
            Character review feedback
        """
        prompt = f"""Character Data:
{self._format_character(character_data)}

Scene Context:
{self._format_scenes(scene_context)}

Review this character's development, consistency, and arc.
Suggest improvements for stronger characterization."""

        return self._call_model(prompt)

    def check_consistency(
        self,
        story_graph: dict[str, Any],
        focus_area: str = "all",
    ) -> str:
        """Check story consistency across the graph.

        Args:
            story_graph: Story Graph context dict
            focus_area: 'characters', 'plot', 'worldbuilding', or 'all'

        Returns:
            Consistency report
        """
        prompt = f"""Story Graph:
{self._format_context(story_graph)}

Focus Area: {focus_area}

Identify any inconsistencies in:
- Character traits and behavior
- Plot logic and causality
- Worldbuilding rules
- Timeline and sequencing

Provide specific examples of any issues found."""

        return self._call_model(prompt)

    def suggest_arc_improvements(
        self,
        current_arc: list[dict[str, Any]],
        target_emotion: str = "satisfying",
    ) -> str:
        """Suggest improvements for story arc pacing.

        Args:
            current_arc: List of story beats
            target_emotion: Desired emotional tone

        Returns:
            Arc improvement suggestions
        """
        prompt = f"""Current Story Arc:
{self._format_arc(current_arc)}

Target Emotional Tone: {target_emotion}

Analyze the pacing and emotional flow of this arc.
Suggest specific improvements for greater impact."""

        return self._call_model(prompt)

    def _call_model(self, prompt: str) -> str:
        """Call the local model via API client."""
        try:
            response = simple_chat(
                system_prompt=self.system_prompt,
                user_message=prompt,
            )
            logger.info(f"StoryAdvisor response generated for: {prompt[:50]}...")
            return response
        except Exception as e:
            error_msg = f"StoryAdvisor error: {str(e)}"
            logger.error(error_msg)
            return f"Error generating response: {str(e)}"

    def _format_context(self, context: dict[str, Any]) -> str:
        """Format story context for display."""
        lines = []
        for key, value in context.items():
            if isinstance(value, (list, dict)):
                lines.append(f"## {key.replace('_', ' ').title()}")
                lines.append(str(value))
            else:
                lines.append(f"{key.replace('_', ' ').title()}: {value}")
        return "\n\n".join(lines)

    def _format_character(self, character: dict[str, Any]) -> str:
        """Format character data for display."""
        lines = [
            f"Name: {character.get('name', 'Unknown')}",
            f"Description: {character.get('description', 'N/A')}",
            f"Traits: {character.get('traits', 'N/A')}",
            f"Arc: {character.get('arc', 'N/A')}",
        ]
        return "\n".join(lines)

    def _format_scenes(self, scenes: list[dict[str, Any]]) -> str:
        """Format scene list for display."""
        lines = []
        for i, scene in enumerate(scenes, 1):
            lines.append(f"Scene {i}: {scene.get('description', 'No description')}")
        return "\n".join(lines)

    def _format_arc(self, arc: list[dict[str, Any]]) -> str:
        """Format story arc beats for display."""
        lines = []
        for i, beat in enumerate(arc, 1):
            lines.append(f"{i}. {beat.get('description', beat.get('beat', 'Beat'))}")
        return "\n".join(lines)
