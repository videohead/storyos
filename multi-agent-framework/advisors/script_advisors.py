"""Script and Story Department Advisors — Writing and continuity."""
from __future__ import annotations

import logging
from typing import Any

from api_client import simple_chat

logger = logging.getLogger(__name__)

__all__ = [
    "ScriptSupervisorAdvisor",
    "CastingDirectorAdvisor",
]


class ScriptSupervisorAdvisor:
    """Script Supervisor advisor — continuity and script documentation."""

    def __init__(self, name: str = "script_supervisor_advisor"):
        self.name = name
        self.role = "Script Supervisor"
        self.system_prompt = """You are the Script Supervisor Advisor for StoryOS, a filmmaking AI assistant.

Your role is to assist with continuity tracking, script documentation,
and ensuring consistency across all shots and scenes.

You have access to:
- Script and scene descriptions
- Shot list and coverage plans
- Continuity records from previous shots
- Actor dialogue and performance notes

Guidelines:
- Maintain accurate continuity records
- Track all script and dialogue changes
- Monitor continuity between shots and scenes
- Document all takes and selections
- Never let continuity errors reach final cut"""

    def check_continuity(self, current_shot: dict[str, Any], previous_shot: dict[str, Any]) -> str:
        """Check continuity between shots.

        Args:
            current_shot: Current shot details
            previous_shot: Previous shot for comparison

        Returns:
            Continuity report and recommendations
        """
        prompt = f"""Current Shot:
{self._format_shot(current_shot)}

Previous Shot:
{self._format_shot(previous_shot)}

Check continuity by:
- Comparing actor positions and blocking
- Checking prop and set continuity
- Reviewing costume continuity
- Monitoring eye-line continuity
- Tracking screen direction"""
        return self._call_model(prompt)

    def _format_shot(self, shot: dict) -> str:
        return f"""Shot: {shot.get('shot_number', 'Unknown')}
Scene: {shot.get('scene_number', 'Unknown')}
Take: {shot.get('take', 'Unknown')}
Actor Positions: {shot.get('actor_positions', 'Unknown')}
Props: {', '.join(shot.get('props', []))}
Dialogue: {shot.get('dialogue', 'Unknown')}"""

    def document_script(self, scene: dict[str, Any]) -> str:
        """Document script and dialogue for a scene.

        Args:
            scene: Scene details and script information

        Returns:
            Script documentation plan
        """
        prompt = f"""Scene:
{self._format_scene(scene)}

Document script by:
- Tracking all dialogue and delivery
- Noting script changes and improvisations
- Recording take selections
- Documenting timing and pacing
- Maintaining accurate script notes"""
        return self._call_model(prompt)

    def _format_scene(self, scene: dict) -> str:
        return f"""Scene: {scene.get('scene_number', 'Unknown')}
Pages: {scene.get('pages', 'Unknown')}
Dialogue Heavy: {scene.get('dialogue_heavy', False)}
Action Level: {scene.get('action_level', 'Unknown')}
Special Notes: {', '.join(scene.get('special_notes', []))}"""

    def track_takes(self, scene: dict[str, Any]) -> str:
        """Track takes and selections for a scene.

        Args:
            scene: Scene shooting information

        Returns:
            Take tracking report
        """
        prompt = f"""Scene:
{self._format_scene(scene)}

Track takes by:
- Recording all takes for each shot
- Noting selected takes (circle takes)
- Documenting director preferences
- Tracking performance variations
- Maintaining clear take records"""
        return self._call_model(prompt)


class CastingDirectorAdvisor:
    """Casting Director advisor — casting and audition coordination."""

    def __init__(self, name: str = "casting_director_advisor"):
        self.name = name
        self.role = "Casting Director"
        self.system_prompt = """You are the Casting Director Advisor for StoryOS, a filmmaking AI assistant.

Your role is to assist with casting decisions, audition coordination,
and finding the right actors for all roles in the production.

You have access to
- Character descriptions and requirements
- Actor profiles and reels
- Audition schedules and results
- Casting trends and market analysis

Guidelines:
- Find actors who embody the character
- Consider chemistry between cast members
- Balance artistic vision with practical constraints
- Maintain inclusive casting practices
- Never compromise casting quality for convenience"""

    def cast_role(self, character: dict[str, Any]) -> str:
        """Cast a specific role.

        Args:
            character: Character description and requirements

        Returns:
            Casting recommendations
        """
        prompt = f"""Character:
{self._format_character(character)}

Cast role by:
- Identifying ideal actor characteristics
- Suggesting actor types and specific candidates
- Planning audition material
- Considering chemistry with existing cast
- Balancing talent with availability and budget"""
        return self._call_model(prompt)

    def _format_character(self, character: dict) -> str:
        return f"""Name: {character.get('name', 'Unknown')}
Age Range: {character.get('age_range', 'Unknown')}
Description: {character.get('description', 'No description')}
Personality: {', '.join(character.get('personality', []))}
Physical Requirements: {', '.join(character.get('physical_requirements', []))}
Special Skills: {', '.join(character.get('special_skills', []))}"""

    def plan_auditions(self, roles: list[dict[str, Any]]) -> str:
        """Plan audition sessions for multiple roles.

        Args:
            roles: List of roles to be cast

        Returns:
            Audition planning document
        """
        prompt = f"""Roles to Cast:
{self._format_roles(roles)}

Plan auditions by:
- Scheduling audition sessions
- Preparing sides and material
- Planning chemistry reads
- Coordinating with director and producers
- Managing actor availability"""
        return self._call_model(prompt)

    def _format_roles(self, roles: list[dict]) -> str:
        return "\n\n".join(
            f"## {r.get('character_name', 'Unknown')}\n"
            f"Age: {r.get('age_range', 'Unknown')}\n"
            f"Description: {r.get('description', 'No description')}\n"
            f"Priority: {r.get('priority', 'Normal')}"
            for r in roles
        )

    def check_chemistry(self, cast_options: list[dict[str, Any]]) -> str:
        """Check chemistry between potential cast members.

        Args:
            cast_options: Different casting combinations

        Returns:
            Chemistry analysis and recommendations
        """
        prompt = f"""Casting Options:
{self._format_cast_options(cast_options)}

Check chemistry by:
- Analyzing potential pairings
- Considering visual compatibility
- Evaluating acting style matches
- Planning chemistry reads
- Recommending final casting choices"""
        return self._call_model(prompt)

    def _format_cast_options(self, options: list[dict]) -> str:
        return "\n\n".join(
            f"## Option {i+1}\n" + "\n".join(f"- {actor.get('role', '')}: {actor.get('actor', 'Unknown')}" for actor in cast)
            for i, cast in enumerate(options)
        )
