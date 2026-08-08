"""Camera Department Advisors — Cinematography and camera operations."""
from __future__ import annotations

import logging
from typing import Any

from api_client import simple_chat

logger = logging.getLogger(__name__)

__all__ = [
    "CameraOperatorAdvisor",
    "FirstACAdvisor",
    "SecondACAdvisor",
    "FilmLoaderAdvisor",
]


class CameraOperatorAdvisor:
    """Camera Operator advisor — camera operation and framing."""

    def __init__(self, name: str = "camera_operator_advisor"):
        self.name = name
        self.role = "Camera Operator"
        self.system_prompt = """You are the Camera Operator Advisor for StoryOS, a filmmaking AI assistant.

Your role is to assist with camera operation, framing, and executing the
camera movement and composition planned by the Cinematographer.

You have access to:
- Camera equipment specifications
- Lens catalogs and characteristics
- Camera movement plans
- Shot lists and storyboards

Guidelines:
- Execute the Cinematographer's visual vision
- Provide technical camera expertise
- Suggest camera positions and movements
- Consider camera stability and smoothness
- Never compromise image quality"""

    def execute_shot(self, shot_plan: dict[str, Any]) -> str:
        """Execute a shot according to plan.

        Args:
            shot_plan: Shot details including camera position, movement, lens

        Returns:
            Camera operation recommendations
        """
        prompt = f"""Shot Plan:
{self._format_shot(shot_plan)}

Execute this shot by:
- Selecting optimal camera position
- Planning camera movement execution
- Ensuring proper framing and composition
- Coordinating with grip department for support
- Maintaining shot consistency with previous takes"""
        return self._call_model(prompt)

    def _format_shot(self, shot: dict) -> str:
        return f"""Shot Number: {shot.get('shot_number', 'Unknown')}
Camera Movement: {shot.get('movement', 'Static')}
Lens: {shot.get('lens', 'Unknown')}
Framing: {shot.get('framing', 'Unknown')}
Notes: {shot.get('notes', '')}"""


class FirstACAdvisor:
    """First Assistant Camera advisor — focus pulling and camera setup."""

    def __init__(self, name: str = "first_ac_advisor"):
        self.name = name
        self.role = "First Assistant Camera"
        self.system_prompt = """You are the 1st AC Advisor for StoryOS, a filmmaking AI assistant.

Your role is to assist with focus pulling, camera setup, lens changes,
and maintaining camera performance throughout shooting.

You have access to:
- Camera and lens specifications
- Focus distance measurements
- Shot list and scene information
- Camera maintenance records

Guidelines:
- Maintain sharp focus at all times
- Prepare camera setups in advance
- Monitor camera performance and temperature
- Communicate with camera operator and 2nd AC
- Never compromise image sharpness"""

    def manage_focus(self, scene: dict[str, Any], talent: list[dict]) -> str:
        """Manage focus for a scene with moving talent.

        Args:
            scene: Scene description with camera movement
            talent: Actor positions and movement

        Returns:
            Focus management recommendations
        """
        prompt = f"""Scene Details:
{self._format_scene(scene)}

Talent Movement:
{self._format_talent(talent)}

Manage focus by:
- Calculating focus distances for talent positions
- Planning focus pulls for camera movement
- Marking focus points on follow focus
- Preparing for quick focus changes
- Accounting for depth of field limitations"""
        return self._call_model(prompt)

    def _format_scene(self, scene: dict) -> str:
        return f"""Camera Movement: {scene.get('camera_movement', 'Static')}
Lens: {scene.get('lens', 'Unknown')}
Aperture: {scene.get('aperture', 'Unknown')}
Shooting Format: {scene.get('format', 'Unknown')}"""

    def _format_talent(self, talent: list[dict]) -> str:
        return "\n\n".join(
            f"- {t.get('name', 'Actor')}: {t.get('movement', 'Static')}"
            for t in talent
        )


class SecondACAdvisor:
    """Second Assistant Camera advisor — slate, logs, and camera support."""

    def __init__(self, name: str = "second_ac_advisor"):
        self.name = name
        self.role = "Second Assistant Camera"
        self.system_prompt = """You are the 2nd AC Advisor for StoryOS, a filmmaking AI assistant.

Your role is to assist with camera department logistics including slate,
camera logs, media management, and supporting the 1st AC.

You have access to:
- Shot lists and scene information
- Media and storage inventory
- Camera settings and configurations
- Daily shooting reports

Guidelines:
- Maintain accurate camera reports
- Manage media and storage efficiently
- Prepare camera setups for 1st AC
- Ensure proper slate and scene information
- Never lose or corrupt footage"""

    def manage_camera_reports(self, shooting_day: dict[str, Any]) -> str:
        """Manage camera reports and logs.

        Args:
            shooting_day: Day's shooting information

        Returns:
            Camera report template and recommendations
        """
        prompt = f"""Shooting Day:
{self._format_shooting_day(shooting_day)}

Manage camera reports by:
- Preparing camera report templates
- Tracking takes and selections
- Logging camera settings for each shot
- Monitoring media usage
- Ensuring all footage is accounted for"""
        return self._call_model(prompt)

    def _format_shooting_day(self, day: dict) -> str:
        return f"""Date: {day.get('date', 'Unknown')}
Scenes: {', '.join(day.get('scenes', []))}
Shots Planned: {day.get('shots_planned', 'Unknown')}
Media Available: {day.get('media_available', 'Unknown')}"""


class FilmLoaderAdvisor:
    """Film Loader advisor — media management and camera maintenance."""

    def __init__(self, name: str = "film_loader_advisor"):
        self.name = name
        self.role = "Film Loader"
        self.system_prompt = """You are the Film Loader Advisor for StoryOS, a filmmaking AI assistant.

Your role is to assist with media management, camera maintenance, and
ensuring all camera equipment is ready for shooting.

You have access to:
- Camera equipment inventory
- Media storage and backup systems
- Camera maintenance schedules
- Footage verification reports

Guidelines:
- Ensure all media is properly backed up
- Maintain camera equipment in working order
- Verify footage integrity
- Manage media inventory efficiently
- Never lose or compromise footage"""

    def manage_media(self, shooting_day: dict[str, Any]) -> str:
        """Manage media for a shooting day.

        Args:
            shooting_day: Day's shooting information

        Returns:
            Media management plan
        """
        prompt = f"""Shooting Day:
{self._format_shooting_day(shooting_day)}

Manage media by:
- Preparing sufficient media for shooting day
- Setting up backup systems
- Planning media labeling and organization
- Ensuring media compatibility with cameras
- Monitoring media usage during shooting"""
        return self._call_model(prompt)

    def _format_shooting_day(self, day: dict) -> str:
        return f"""Scenes: {', '.join(day.get('scenes', []))}
Estimated Shots: {day.get('estimated_shots', 'Unknown')}
Media Available: {day.get('media_available', 'Unknown')}
Backup Systems: {day.get('backup_systems', 'Unknown')}"""
