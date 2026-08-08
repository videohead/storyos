"""Sound Department Advisors — Production sound and audio."""
from __future__ import annotations

import logging
from typing import Any

from api_client import simple_chat

logger = logging.getLogger(__name__)

__all__ = [
    "SoundMixerAdvisor",
    "BoomOperatorAdvisor",
    "SoundAssistantAdvisor",
]


class SoundMixerAdvisor:
    """Sound Mixer advisor — production sound recording and mixing."""

    def __init__(self, name: str = "sound_mixer_advisor"):
        self.name = name
        self.role = "Sound Mixer"
        self.system_prompt = """You are the Sound Mixer Advisor for StoryOS, a filmmaking AI assistant.

Your role is to assist with production sound recording, audio monitoring,
and ensuring clean, usable audio is captured on set.

You have access to:
- Sound equipment specifications
- Scene and location information
- Cast and crew schedules
- Environmental noise data

Guidelines:
- Prioritize audio quality above all else
- Monitor for unwanted noise and interference
- Communicate audio issues immediately
- Plan microphone placement for each scene
- Never compromise audio quality for convenience"""

    def plan_audio_capture(self, scene: dict[str, Any], location: dict[str, Any]) -> str:
        """Plan audio capture for a scene.

        Args:
            scene: Scene description with dialogue and action
            location: Location details and environmental info

        Returns:
            Audio capture plan and recommendations
        """
        prompt = f"""Scene Details:
{self._format_scene(scene)}

Location Information:
{self._format_location(location)}

Plan audio capture by:
- Selecting appropriate microphones (lavalier, boom, etc.)
- Planning microphone placement
- Identifying potential noise sources
- Suggesting audio monitoring strategies
- Accounting for camera movement and actor positioning"""
        return self._call_model(prompt)

    def _format_scene(self, scene: dict) -> str:
        return f"""Scene: {scene.get('scene_number', 'Unknown')}
Dialogue Heavy: {scene.get('dialogue_heavy', False)}
Action Level: {scene.get('action_level', 'Unknown')}
Number of Actors: {scene.get('actor_count', 'Unknown')}"""

    def _format_location(self, location: dict) -> str:
        return f"""Name: {location.get('name', 'Unknown')}
Indoor/Outdoor: {location.get('type', 'Unknown')}
Noise Sources: {', '.join(location.get('noise_sources', []))}
Acoustics: {location.get('acoustics', 'Unknown')}"""


class BoomOperatorAdvisor:
    """Boom Operator advisor — boom microphone operation and placement."""

    def __init__(self, name: str = "boom_operator_advisor"):
        self.name = name
        self.role = "Boom Operator"
        self.system_prompt = """You are the Boom Operator Advisor for StoryOS, a filmmaking AI assistant.

Your role is to assist with boom microphone operation, placement, and
movement to capture optimal dialogue and ambient sound.

You have access to:
- Scene blocking and camera plans
- Microphone and equipment specifications
- Actor positioning information
- Shot list and camera movement plans

Guidelines:
- Keep boom out of frame at all times
- Follow actor movement smoothly
- Maintain consistent audio levels
- Coordinate with camera department
- Never compromise audio quality for comfort"""

    def position_boom(self, scene: dict[str, Any], camera_plan: dict[str, Any]) -> str:
        """Plan boom microphone positioning.

        Args:
            scene: Scene description with actor blocking
            camera_plan: Camera positions and movement

        Returns:
            Boom positioning recommendations
        """
        prompt = f"""Scene Details:
{self._format_scene(scene)}

Camera Plan:
{self._format_camera(camera_plan)}

Position the boom by:
- Identifying optimal boom positions for each actor
- Planning boom movement with camera movement
- Avoiding frame intrusions
- Accounting for actor movement patterns
- Suggesting alternative mics when boom is insufficient"""
        return self._call_model(prompt)

    def _format_scene(self, scene: dict) -> str:
        return f"""Actors: {', '.join(scene.get('actors', []))}
Movement: {scene.get('movement', 'Static')}
Dialogue Density: {scene.get('dialogue_density', 'Unknown')}"""

    def _format_camera(self, camera: dict) -> str:
        return f"""Camera Movement: {camera.get('movement', 'Static')}
Frame Size: {camera.get('frame_size', 'Unknown')}
Lens: {camera.get('lens', 'Unknown')}"""


class SoundAssistantAdvisor:
    """Sound Assistant advisor — sound equipment setup and maintenance."""

    def __init__(self, name: str = "sound_assistant_advisor"):
        self.name = name
        self.role = "Sound Assistant"
        self.system_prompt = """You are the Sound Assistant Advisor for StoryOS, a filmmaking AI assistant.

Your role is to assist with sound equipment setup, cable management,
battery maintenance, and supporting the Sound Mixer and Boom Operator.

You have access to:
- Sound equipment inventory
- Cable and accessory inventory
- Battery charging status
- Equipment maintenance schedules

Guidelines:
- Ensure all equipment is ready before call time
- Manage cables cleanly and safely
- Monitor battery levels throughout the day
- Maintain equipment inventory
- Never let equipment failure interrupt shooting"""

    def prepare_equipment(self, shooting_day: dict[str, Any]) -> str:
        """Prepare sound equipment for a shooting day.

        Args:
            shooting_day: Day's shooting schedule and requirements

        Returns:
            Equipment preparation plan
        """
        prompt = f"""Shooting Day:
{self._format_shooting_day(shooting_day)}

Prepare equipment by:
- Checking and charging all batteries
- Testing all microphones and wireless systems
- Preparing cable runs and management
- Setting up equipment for each scene
- Preparing backup equipment"""
        return self._call_model(prompt)

    def _format_shooting_day(self, day: dict) -> str:
        return f"""Scenes: {', '.join(day.get('scenes', []))}
Locations: {', '.join(day.get('locations', []))}
Special Audio Needs: {', '.join(day.get('special_audio', []))}"""
