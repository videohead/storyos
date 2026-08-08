"""Post-Production Advisors — Editing, VFX, and DIT."""
from __future__ import annotations

import logging
from typing import Any

from api_client import simple_chat

logger = logging.getLogger(__name__)

__all__ = [
    "VFXSupervisorAdvisor",
    "VFXCoordinatorAdvisor",
    "OnSetEditorAdvisor",
    "DITAdvisor",
    "PrevisualizationArtistAdvisor",
]


class VFXSupervisorAdvisor:
    """VFX Supervisor advisor — visual effects strategy and oversight."""

    def __init__(self, name: str = "vfx_supervisor_advisor"):
        self.name = name
        self.role = "VFX Supervisor"
        self.system_prompt = """You are the VFX Supervisor Advisor for StoryOS, a filmmaking AI assistant.

Your role is to assist with visual effects strategy, planning, and oversight
throughout production. You ensure VFX shots are properly captured on set
and integrated seamlessly in post-production.

You have access to:
- Script and scene descriptions
- Shot lists and storyboards
- VFX shot tracking database
- Reference material and photography plans

Guidelines:
- Plan VFX requirements early in pre-production
- Ensure proper on-set capture for VFX integration
- Coordinate between on-set and post-production VFX teams
- Maintain visual consistency across all VFX shots
- Never compromise VFX quality for speed"""

    def plan_vfx_shots(self, scenes: list[dict[str, Any]]) -> str:
        """Plan VFX requirements for scenes.

        Args:
            scenes: List of scenes requiring VFX

        Returns:
            VFX shot plan and requirements
        """
        prompt = f"""Scenes Requiring VFX:
{self._format_scenes(scenes)}

Plan VFX shots by:
- Identifying all VFX requirements per scene
- Determining on-set capture needs (markers, tracking, HDRI, etc.)
- Planning VFX shot list and prioritization
- Estimating VFX complexity and timeline
- Coordinating with other departments on VFX dependencies"""
        return self._call_model(prompt)

    def _format_scenes(self, scenes: list[dict]) -> str:
        return "\n\n".join(
            f"## Scene {s.get('scene_number', '?')}\n"
            f"Description: {s.get('description', 'No description')}\n"
            f"VFX Requirements: {', '.join(s.get('vfx_requirements', []))}\n"
            f"Complexity: {s.get('vfx_complexity', 'Unknown')}"
            for s in scenes
        )

    def oversee_vfx_pipeline(self, vfx_shots: list[dict[str, Any]]) -> str:
        """Oversee VFX shot pipeline and progress.

        Args:
            vfx_shots: List of VFX shots with status

        Returns:
            Pipeline oversight recommendations
        """
        prompt = f"""VFX Shots Status:
{self._format_vfx_shots(vfx_shots)}

Oversee pipeline by:
- Tracking shot progress through stages
- Identifying bottlenecks or delays
- Coordinating review schedules
- Ensuring quality standards are met
- Managing vendor communications if applicable"""
        return self._call_model(prompt)

    def _format_vfx_shots(self, shots: list[dict]) -> str:
        return "\n\n".join(
            f"Shot {shot.get('shot_number', '?')}: {shot.get('status', 'Unknown')} - {shot.get('complexity', 'Unknown')}"
            for shot in shots[:20]  # Limit for readability
        )


class VFXCoordinatorAdvisor:
    """VFX Coordinator advisor — VFX pipeline coordination and tracking."""

    def __init__(self, name: str = "vfx_coordinator_advisor"):
        self.name = name
        self.role = "VFX Coordinator"
        self.system_prompt = """You are the VFX Coordinator Advisor for StoryOS, a filmmaking AI assistant.

Your role is to assist with VFX shot tracking, data management, and
coordination between on-set VFX capture and post-production VFX teams.

You have access to:
- VFX shot database and tracking system
- Reference material collection schedule
- Data management and backup systems
- Review and approval schedules

Guidelines:
- Maintain accurate shot tracking records
- Ensure all reference material is collected
- Manage VFX data efficiently
- Coordinate review schedules
- Never lose or corrupt VFX data"""

    def manage_vfx_data(self, shooting_day: dict[str, Any]) -> str:
        """Manage VFX data for a shooting day.

        Args:
            shooting_day: Day's shooting plan with VFX shots

        Returns:
            VFX data management plan
        """
        prompt = f"""Shooting Day with VFX:
{self._format_shooting_day(shooting_day)}

Manage VFX data by:
- Planning reference material collection (photos, measurements, HDRI)
- Setting up data backup systems
- Labeling and organizing VFX data
- Coordinating with camera department for VFX shots
- Ensuring all VFX data reaches post-production"""
        return self._call_model(prompt)

    def _format_shooting_day(self, day: dict) -> str:
        return f"""Date: {day.get('date', 'Unknown')}
VFX Shots Planned: {day.get('vfx_shots_planned', 'Unknown')}
Reference Material Needed: {', '.join(day.get('reference_material', []))}
VFX Department On Set: {day.get('vfx_on_set', False)}"""

    def track_vfx_shots(self, vfx_database: dict[str, Any]) -> str:
        """Track VFX shot progress and status.

        Args:
            vfx_database: VFX shot tracking database

        Returns:
            Shot tracking report and recommendations
        """
        prompt = f"""VFX Shot Database:
{self._format_database(vfx_database)}

Track shots by:
- Updating shot status as progress is made
- Identifying shots falling behind schedule
- Coordinating review and approval cycles
- Managing shot dependencies
- Preparing status reports for VFX Supervisor"""
        return self._call_model(prompt)

    def _format_database(self, db: dict) -> str:
        return f"""Total Shots: {db.get('total_shots', 'Unknown')}
Completed: {db.get('completed', 'Unknown')}
In Progress: {db.get('in_progress', 'Unknown')}
Pending: {db.get('pending', 'Unknown')}
Overdue: {db.get('overdue', 'Unknown')}"""


class OnSetEditorAdvisor:
    """On-Set Editor advisor — daily assembly and dailies preparation."""

    def __init__(self, name: str = "on_set_editor_advisor"):
        self.name = name
        self.role = "On-Set Editor"
        self.system_prompt = """You are the On-Set Editor Advisor for StoryOS, a filmmaking AI assistant.

Your role is to assist with assembling footage on set, preparing dailies,
and providing immediate editorial feedback to the Director.

You have access to:
- Daily footage and media files
- Shot list and scene information
- Editor's notes and preferences
- Continuity records from previous days

Guidelines:
- Prepare dailies quickly for next-day shooting
- Identify continuity issues immediately
- Maintain accurate media logs
- Provide useful assembly cuts for director review
- Never make final editorial decisions — provide options only"""

    def prepare_dailies(self, shooting_day: dict[str, Any]) -> str:
        """Prepare dailies from shooting day footage.

        Args:
            shooting_day: Day's shooting information and footage status

        Returns:
            Dailies preparation plan
        """
        prompt = f"""Shooting Day:
{self._format_shooting_day(shooting_day)}

Prepare dailies by:
- Assembling best takes for each scene
- Organizing footage by scene and shot
- Adding slate and scene information
- Creating quick cuts for director review
- Logging camera settings and notes"""
        return self._call_model(prompt)

    def _format_shooting_day(self, day: dict) -> str:
        return f"""Date: {day.get('date', 'Unknown')}
Scenes Shot: {', '.join(day.get('scenes_shot', []))}
Total Takes: {day.get('total_takes', 'Unknown')}
Selected Takes: {day.get('selected_takes', 'Unknown')}
Issues: {', '.join(day.get('issues', []))}"""

    def check_continuity(self, current_scenes: list[dict[str, Any]], previous_scenes: list[dict]) -> str:
        """Check continuity between current and previous shooting days.

        Args:
            current_scenes: Scenes shot today
            previous_scenes: Scenes shot previously

        Returns:
            Continuity report and recommendations
        """
        prompt = f"""Current Scenes:
{self._format_scenes(current_scenes)}

Previous Scenes:
{self._format_scenes(previous_scenes)}

Check continuity by:
- Comparing actor positions and blocking
- Checking prop and set continuity
- Reviewing costume continuity
- Identifying potential continuity errors
- Suggesting pickup shots if needed"""
        return self._call_model(prompt)


class DITAdvisor:
    """DIT (Digital Imaging Technician) advisor — color science and data management."""

    def __init__(self, name: str = "dit_advisor"):
        self.name = name
        self.role = "Digital Imaging Technician"
        self.system_prompt = """You are the DIT Advisor for StoryOS, a filmmaking AI assistant.

Your role is to assist with digital imaging, color science, data management,
and ensuring optimal image quality from capture to delivery.

You have access to:
- Camera raw data and color profiles
- LUT creation and management
- Data backup and verification systems
- Color grading references

Guidelines:
- Ensure optimal image quality from capture
- Manage color science consistently
- Maintain rigorous data backup protocols
- Support the Cinematographer's visual vision
- Never lose or compromise image data"""

    def manage_color_pipeline(self, camera_settings: dict[str, Any]) -> str:
        """Manage color pipeline and LUTs.

        Args:
            camera_settings: Camera color settings and profiles

        Returns:
            Color pipeline recommendations
        """
        prompt = f"""Camera Color Settings:
{self._format_camera_settings(camera_settings)}

Manage color pipeline by:
- Selecting appropriate color space and gamut
- Creating on-set LUTs for monitoring
- Ensuring consistent color across cameras
- Planning for post-production color grading
- Testing LUTs with Cinematographer"""
        return self._call_model(prompt)

    def _format_camera_settings(self, settings: dict) -> str:
        return f"""Camera Model: {settings.get('camera_model', 'Unknown')}
Color Profile: {settings.get('color_profile', 'Unknown')}
Resolution: {settings.get('resolution', 'Unknown')}
Frame Rate: {settings.get('frame_rate', 'Unknown')}
Log Profile: {settings.get('log_profile', 'Unknown')}"""

    def manage_data(self, shooting_day: dict[str, Any]) -> str:
        """Manage media data and backups.

        Args:
            shooting_day: Day's shooting information

        Returns:
            Data management plan
        """
        prompt = f"""Shooting Day Data:
{self._format_shooting_day(shooting_day)}

Manage data by:
- Planning backup strategy (3-2-1 rule)
- Verifying data integrity with checksums
- Organizing media with clear naming conventions
- Monitoring storage capacity
- Ensuring off-site backups are completed"""
        return self._call_model(prompt)

    def _format_shooting_day(self, day: dict) -> str:
        return f"""Estimated Data Size: {day.get('estimated_data', 'Unknown')}
Shots Count: {day.get('shots_count', 'Unknown')}
Backup Storage Available: {day.get('backup_storage', 'Unknown')}
Off-site Transfer: {day.get('offsite_transfer', False)}"""


class PrevisualizationArtistAdvisor:
    """Previsualization Artist advisor — 3D animated previews."""

    def __init__(self, name: str = "previsualization_artist_advisor"):
        self.name = name
        self.role = "Previsualization Artist"
        self.system_prompt = """You are the Previsualization Artist Advisor for StoryOS, a filmmaking AI assistant.

Your role is to assist with creating 3D animated previews of complex scenes
and sequences before filming, allowing the crew to plan shots and VFX.

You have access to:
- Script and scene descriptions
- Storyboards and animatics
- Camera and lens specifications
- VFX requirements documentation

Guidelines:
- Create clear, useful previs that communicates the intended shot
- Balance quality with turnaround time
- Iterate based on Director feedback
- Ensure previs is achievable on set
- Coordinate with Cinematographer and VFX Supervisor"""

    def create_previs(self, sequence: dict[str, Any]) -> str:
        """Create previsualization for a sequence.

        Args:
            sequence: Sequence description with shots and action

        Returns:
            Previs creation plan
        """
        prompt = f"""Sequence Details:
{self._format_sequence(sequence)}

Create previs by:
- Building simple 3D environments
- Animating camera movement and blocking
- Adding timing and pacing information
- Including VFX notes and requirements
- Creating versions for Director review"""
        return self._call_model(prompt)

    def _format_sequence(self, seq: dict) -> str:
        return f"""Sequence: {seq.get('sequence_number', 'Unknown')}
Scenes: {', '.join(seq.get('scenes', []))}
Complexity: {seq.get('complexity', 'Unknown')}
VFX Requirements: {', '.join(seq.get('vfx_requirements', []))}
Priority: {seq.get('priority', 'Normal')}"""

    def plan_camera_blocks(self, scene: dict[str, Any]) -> str:
        """Plan camera blocks for a scene in previs.

        Args:
            scene: Scene description with action and emotion

        Returns:
            Camera blocking plan for previs
        """
        prompt = f"""Scene:
{self._format_scene(scene)}

Plan camera blocking by:
- Establishing camera positions and angles
- Planning camera movement through the scene
- Coordinating with actor blocking
- Testing different visual approaches
- Ensuring coverage for editing"""
        return self._call_model(prompt)

    def _format_scene(self, scene: dict) -> str:
        return f"""Scene: {scene.get('scene_number', 'Unknown')}
Action: {scene.get('action', 'Unknown')}
Camera Movement: {scene.get('camera_movement', 'Unknown')}
Emotional Beat: {scene.get('emotional_beat', 'Unknown')}"""
