"""Grip and Electric Department Advisors — Lighting and rigging."""
from __future__ import annotations

import logging
from typing import Any

from api_client import simple_chat

logger = logging.getLogger(__name__)

__all__ = [
    "GafferAdvisor",
    "KeyGripAdvisor",
    "BestBoyGafferAdvisor",
    "BestBoyGripAdvisor",
    "DollyGripAdvisor",
    "GripAdvisor",
    "ElectricianAdvisor",
    "GeneratorOperatorAdvisor",
]


class GafferAdvisor:
    """Gaffer advisor — chief lighting technician and electrician."""

    def __init__(self, name: str = "gaffer_advisor"):
        self.name = name
        self.role = "Gaffer"
        self.system_prompt = """You are the Gaffer Advisor for StoryOS, a filmmaking AI assistant.

Your role is to assist with lighting design, electrical planning, and
managing the electric department to achieve the Cinematographer's vision.

You have access to:
- Lighting equipment inventory and capabilities
- Scene descriptions and mood requirements
- Camera and lens specifications
- Power distribution plans

Guidelines:
- Support the Cinematographer's visual vision through lighting
- Ensure all electrical work is safe and code-compliant
- Plan power distribution efficiently
- Manage the electric department staff
- Never compromise safety for lighting effects"""

    def design_lighting(self, scene: dict[str, Any], camera_plan: dict[str, Any]) -> str:
        """Design lighting for a scene.

        Args:
            scene: Scene description and mood
            camera_plan: Camera positions and lens choices

        Returns:
            Lighting design plan
        """
        prompt = f"""Scene Details:
{self._format_scene(scene)}

Camera Plan:
{self._format_camera(camera_plan)}

Design lighting by:
- Establishing lighting mood and style
- Selecting appropriate fixtures and modifiers
- Planning light placement and angles
- Accounting for camera positions and movement
- Considering natural light sources and continuity"""
        return self._call_model(prompt)

    def _format_scene(self, scene: dict) -> str:
        return f"""Scene: {scene.get('scene_number', 'Unknown')}
Time of Day: {scene.get('time_of_day', 'Unknown')}
Mood: {', '.join(scene.get('mood', []))}
Interior/Exterior: {scene.get('type', 'Unknown')}"""

    def _format_camera(self, camera: dict) -> str:
        return f"""Camera Movement: {camera.get('movement', 'Static')}
Lens: {camera.get('lens', 'Unknown')}
Aperture: {camera.get('aperture', 'Unknown')}
ISO: {camera.get('iso', 'Unknown')}"""

    def plan_power_distribution(self, location: dict[str, Any]) -> str:
        """Plan electrical power distribution for a location.

        Args:
            location: Location details and power availability

        Returns:
            Power distribution plan
        """
        prompt = f"""Location:
{self._format_location(location)}

Plan power distribution by:
- Assessing available power sources
- Calculating total power requirements
- Planning cable runs and distribution
- Identifying need for generators
- Ensuring code compliance and safety"""
        return self._call_model(prompt)

    def _format_location(self, location: dict) -> str:
        return f"""Name: {location.get('name', 'Unknown')}
Power Available: {location.get('power_available', 'Unknown')}
Power Type: {location.get('power_type', 'Unknown')}
Layout: {location.get('layout', 'Unknown')}"""


class KeyGripAdvisor:
    """Key Grip advisor — chief rigging and equipment technician."""

    def __init__(self, name: str = "key_grip_advisor"):
        self.name = name
        self.role = "Key Grip"
        self.system_prompt = """You are the Key Grip Advisor for StoryOS, a filmmaking AI assistant.

Your role is to assist with rigging, camera support equipment, and
managing the grip department to support camera and lighting needs.

You have access to:
- Grip equipment inventory and capabilities
- Rigging plans and structural requirements
- Camera support equipment specifications
- Safety protocols and procedures

Guidelines:
- Ensure all rigging is safe and secure
- Support camera and lighting department needs
- Manage grip department staff
- Plan equipment setups efficiently
- Never compromise safety for convenience"""

    def plan_rigging(self, setup: dict[str, Any]) -> str:
        """Plan rigging for camera or lighting setups.

        Args:
            setup: Setup requirements and location details

        Returns:
            Rigging plan and safety considerations
        """
        prompt = f"""Setup Requirements:
{self._format_setup(setup)}

Plan rigging by:
- Identifying structural support points
- Selecting appropriate rigging equipment
- Planning load distribution
- Ensuring safety compliance
- Coordinating with electric department for combined setups"""
        return self._call_model(prompt)

    def _format_setup(self, setup: dict) -> str:
        return f"""Type: {setup.get('type', 'Unknown')}
Weight: {setup.get('weight', 'Unknown')}
Location: {setup.get('location', 'Unknown')}
Height: {setup.get('height', 'Unknown')}
Duration: {setup.get('duration', 'Unknown')}"""

    def manage_camera_support(self, camera_plan: dict[str, Any]) -> str:
        """Manage camera support equipment.

        Args:
            camera_plan: Camera movement and positioning plans

        Returns:
            Camera support equipment plan
        """
        prompt = f"""Camera Plan:
{self._format_camera(camera_plan)}

Manage camera support by:
- Selecting appropriate tripods, dollies, or rigs
- Planning dolly track layout if needed
- Ensuring smooth camera movement
- Coordinating with Dolly Grip
- Testing all support equipment"""
        return self._call_model(prompt)

    def _format_camera(self, camera: dict) -> str:
        return f"""Camera Weight: {camera.get('camera_weight', 'Unknown')}
Movement Type: {camera.get('movement_type', 'Unknown')}
Support Required: {camera.get('support_required', 'Unknown')}
Operator Position: {camera.get('operator_position', 'Unknown')}"""


class BestBoyGafferAdvisor:
    """Best Boy Gaffer advisor — chief assistant to the Gaffer."""

    def __init__(self, name: str = "best_boy_gaffer_advisor"):
        self.name = name
        self.role = "Best Boy Gaffer"
        self.system_prompt = """You are the Best Boy Gaffer Advisor for StoryOS, a filmmaking AI assistant.

Your role is to assist with managing the electric department staff,
equipment logistics, and supporting the Gaffer's lighting plans.

You have access to:
- Electric department staff schedules
- Lighting equipment inventory and status
- Cable and accessory inventories
- Equipment rental and return schedules

Guidelines:
- Manage electric department efficiently
- Ensure all equipment is ready and tested
- Track equipment rentals and returns
- Support the Gaffer's lighting plans
- Never let equipment issues delay shooting"""

    def manage_electrics(self, shooting_day: dict[str, Any]) -> str:
        """Manage electric department for a shooting day.

        Args:
            shooting_day: Day's shooting schedule and lighting needs

        Returns:
            Electric department management plan
        """
        prompt = f"""Shooting Day:
{self._format_shooting_day(shooting_day)}

Manage electrics by:
- Preparing equipment for each scene
- Scheduling electricians for each department
- Tracking equipment setup and breakdown
- Monitoring power usage throughout the day
- Coordinating with Best Boy Grip for combined setups"""
        return self._call_model(prompt)

    def _format_shooting_day(self, day: dict) -> str:
        return f"""Scenes: {', '.join(day.get('scenes', []))}
Lighting Complexity: {day.get('lighting_complexity', 'Unknown')}
Electricians Needed: {day.get('electricians_needed', 'Unknown')}
Power Requirements: {day.get('power_requirements', 'Unknown')}"""

    def manage_equipment(self, equipment_list: list[dict[str, Any]]) -> str:
        """Manage lighting equipment inventory and logistics.

        Args:
            equipment_list: List of equipment needed

        Returns:
            Equipment management plan
        """
        prompt = f"""Equipment Requirements:
{self._format_equipment(equipment_list)}

Manage equipment by:
- Checking equipment availability
- Scheduling equipment pickups and returns
- Tracking equipment condition
- Managing spare parts and consumables
- Coordinating with rental houses if needed"""
        return self._call_model(prompt)

    def _format_equipment(self, equipment: list[dict]) -> str:
        return "\n".join(
            f"- {e.get('type', 'Unknown')}: {e.get('quantity', 0)} units - {e.get('status', 'Unknown')}"
            for e in equipment
        )


class BestBoyGripAdvisor:
    """Best Boy Grip advisor — chief assistant to the Key Grip."""

    def __init__(self, name: str = "best_boy_grip_advisor"):
        self.name = name
        self.role = "Best Boy Grip"
        self.system_prompt = """You are the Best Boy Grip Advisor for StoryOS, a filmmaking AI assistant.

Your role is to assist with managing the grip department staff,
equipment logistics, and supporting the Key Grip's rigging plans.

You have access to:
- Grip equipment inventory and status
- Grip department staff schedules
- Equipment rental and return schedules
- Rigging material inventories

Guidelines:
- Manage grip department efficiently
- Ensure all equipment is ready and tested
- Track equipment rentals and returns
- Support the Key Grip's plans
- Never let equipment issues delay shooting"""

    def manage_grips(self, shooting_day: dict[str, Any]) -> str:
        """Manage grip department for a shooting day.

        Args:
            shooting_day: Day's shooting schedule and grip needs

        Returns:
            Grip department management plan
        """
        prompt = f"""Shooting Day:
{self._format_shooting_day(shooting_day)}

Manage grips by:
- Preparing equipment for each scene
- Scheduling gripers for each department
- Tracking equipment setup and breakdown
- Coordinating with Best Boy Gaffer
- Managing grip department staff assignments"""
        return self._call_model(prompt)

    def _format_shooting_day(self, day: dict) -> str:
        return f"""Scenes: {', '.join(day.get('scenes', []))}
Rigging Complexity: {day.get('rigging_complexity', 'Unknown')}
Grippers Needed: {day.get('grippers_needed', 'Unknown')}
Special Equipment: {', '.join(day.get('special_equipment', []))}"""

    def manage_equipment(self, equipment_list: list[dict[str, Any]]) -> str:
        """Manage grip equipment inventory and logistics.

        Args:
            equipment_list: List of grip equipment needed

        Returns:
            Equipment management plan
        """
        prompt = f"""Equipment Requirements:
{self._format_equipment(equipment_list)}

Manage equipment by:
- Checking equipment availability
- Scheduling equipment pickups and returns
- Tracking equipment condition
- Managing rigging materials
- Coordinating with rental houses if needed"""
        return self._call_model(prompt)

    def _format_equipment(self, equipment: list[dict]) -> str:
        return "\n".join(
            f"- {e.get('type', 'Unknown')}: {e.get('quantity', 0)} units - {e.get('status', 'Unknown')}"
            for e in equipment
        )


class DollyGripAdvisor:
    """Dolly Grip advisor — dolly track laying and camera movement."""

    def __init__(self, name: str = "dolly_grip_advisor"):
        self.name = name
        self.role = "Dolly Grip"
        self.system_prompt = """You are the Dolly Grip Advisor for StoryOS, a filmmaking AI assistant.

Your role is to assist with dolly track layout, camera movement planning,
and ensuring smooth camera motion for tracking shots.

You have access to:
- Shot list and camera movement plans
- Dolly and track equipment specifications
- Location floor plans and conditions
- Camera operator preferences

Guidelines:
- Ensure smooth, reliable camera movement
- Lay track accurately for planned movements
- Coordinate with camera operator and Key Grip
- Maintain track safety at all times
- Never compromise movement quality for speed"""

    def plan_dolly_movement(self, shot: dict[str, Any]) -> str:
        """Plan dolly movement for a shot.

        Args:
            shot: Shot description with camera movement

        Returns:
            Dolly movement plan
        """
        prompt = f"""Shot Details:
{self._format_shot(shot)}

Plan dolly movement by:
- Mapping camera path and speed
- Planning track layout and joints
- Coordinating with camera operator
- Accounting for location conditions
- Testing movement before shooting"""
        return self._call_model(prompt)

    def _format_shot(self, shot: dict) -> str:
        return f"""Shot: {shot.get('shot_number', 'Unknown')}
Movement Type: {shot.get('movement_type', 'Unknown')}
Duration: {shot.get('duration', 'Unknown')}
Speed: {shot.get('speed', 'Unknown')}
Floor Conditions: {shot.get('floor_conditions', 'Unknown')}"""

    def lay_track(self, location: dict[str, Any], track_plan: dict[str, Any]) -> str:
        """Plan track laying for a location.

        Args:
            location: Location details and conditions
            track_plan: Planned track layout

        Returns:
            Track laying plan
        """
        prompt = f"""Location:
{self._format_location(location)}

Track Plan:
{self._format_track_plan(track_plan)}

Lay track by:
- Surveying location conditions
- Planning track segments and joints
- Ensuring smooth transitions
- Securing track safely
- Testing with dolly before shooting"""
        return self._call_model(prompt)

    def _format_location(self, location: dict) -> str:
        return f"""Floor Type: {location.get('floor_type', 'Unknown')}
Obstacles: {', '.join(location.get('obstacles', []))}
Space Available: {location.get('space', 'Unknown')}"""

    def _format_track_plan(self, plan: dict) -> str:
        return f"""Track Length: {plan.get('track_length', 'Unknown')}
Curve Radius: {plan.get('curve_radius', 'Unknown')}
Speed Profile: {plan.get('speed_profile', 'Unknown')}"""


class GripAdvisor:
    """Grip advisor — general grip work and equipment operation."""

    def __init__(self, name: str = "grip_advisor"):
        self.name = name
        self.role = "Grip"
        self.system_prompt = """You are the Grip Advisor for StoryOS, a filmmaking AI assistant.

Your role is to assist with general grip work, equipment setup, and
supporting the camera and lighting departments on set.

You have access to:
- Grip equipment inventory
- Setup requirements for each scene
- Location information
- Department schedules

Guidelines:
- Support camera and lighting departments efficiently
- Set up equipment quickly and safely
- Maintain all grip equipment
- Communicate with Key Grip and Best Boy Grip
- Never compromise safety for speed"""

    def assist_setup(self, scene: dict[str, Any]) -> str:
        """Assist with grip setup for a scene.

        Args:
            scene: Scene requirements and setup needs

        Returns:
            Grip setup plan
        """
        prompt = f"""Scene Setup:
{self._format_scene(scene)}

Assist setup by:
- Preparing required grip equipment
- Setting up flags, nets, and diffusion
- Assisting with camera support
- Supporting lighting department
- Maintaining clean and safe set"""
        return self._call_model(prompt)

    def _format_scene(self, scene: dict) -> str:
        return f"""Scene: {scene.get('scene_number', 'Unknown')}
Grip Needs: {', '.join(scene.get('grip_needs', []))}
Setup Complexity: {scene.get('setup_complexity', 'Unknown')}"""


class ElectricianAdvisor:
    """Electrician advisor — lighting technician and electrical work."""

    def __init__(self, name: str = "electrician_advisor"):
        self.name = name
        self.role = "Electrician"
        self.system_prompt = """You are the Electrician Advisor for StoryOS, a filmmaking AI assistant.

Your role is to assist with lighting setup, electrical work, and
supporting the Gaffer's lighting design on set.

You have access to:
- Lighting equipment inventory
- Electrical distribution plans
- Scene lighting requirements
- Safety protocols

Guidelines:
- Set up lights according to Gaffer's plan
- Run cable safely and efficiently
- Monitor electrical loads
- Maintain all lighting equipment
- Never compromise electrical safety"""

    def setup_lights(self, scene: dict[str, Any]) -> str:
        """Setup lights for a scene.

        Args:
            scene: Scene lighting requirements

        Returns:
            Light setup plan
        """
        prompt = f"""Scene Lighting:
{self._format_scene(scene)}

Setup lights by:
- Positioning fixtures according to plan
- Running power and cable safely
- Fitting lamps and modifiers
- Testing light levels and quality
- Coordinating with Gaffer on adjustments"""
        return self._call_model(prompt)

    def _format_scene(self, scene: dict) -> str:
        return f"""Scene: {scene.get('scene_number', 'Unknown')}
Lights Needed: {scene.get('lights_needed', 'Unknown')}
Power Requirements: {scene.get('power_requirements', 'Unknown')}
Special Effects: {', '.join(scene.get('special_effects', []))}"""

    def manage_cable(self, location: dict[str, Any]) -> str:
        """Manage cable runs and distribution.

        Args:
            location: Location details and cable needs

        Returns:
            Cable management plan
        """
        prompt = f"""Location:
{self._format_location(location)}

Manage cable by:
- Planning cable routes
- Running cable safely and securely
- Using appropriate cable types and lengths
- Protecting cable from damage
- Maintaining clean cable runs"""
        return self._call_model(prompt)

    def _format_location(self, location: dict) -> str:
        return f"""Layout: {location.get('layout', 'Unknown')}
Power Source: {location.get('power_source', 'Unknown')}
Cable Routes: {', '.join(location.get('cable_routes', []))}"""


class GeneratorOperatorAdvisor:
    """Generator Operator advisor — power generation and management."""

    def __init__(self, name: str = "generator_operator_advisor"):
        self.name = name
        self.role = "Generator Operator"
        self.system_prompt = """You are the Generator Operator Advisor for StoryOS, a filmmaking AI assistant.

Your role is to assist with generator operation, power management, and
ensuring reliable electrical power for all department needs.

You have access to:
- Generator specifications and capabilities
- Power distribution plans
- Fuel supply information
- Maintenance schedules

Guidelines:
- Ensure reliable power generation
- Monitor generator performance
- Manage fuel supplies efficiently
- Maintain generators regularly
- Never let power failure interrupt shooting"""

    def manage_generators(self, shooting_day: dict[str, Any]) -> str:
        """Manage generators for a shooting day.

        Args:
            shooting_day: Day's power requirements

        Returns:
            Generator management plan
        """
        prompt = f"""Shooting Day Power:
{self._format_shooting_day(shooting_day)}

Manage generators by:
- Sizing generators for power requirements
- Planning fuel supply and refueling
- Monitoring generator performance
- Coordinating with Gaffer on power distribution
- Maintaining generators throughout the day"""
        return self._call_model(prompt)

    def _format_shooting_day(self, day: dict) -> str:
        return f"""Total Power Needed: {day.get('total_power', 'Unknown')}
Generator Capacity: {day.get('generator_capacity', 'Unknown')}
Fuel Available: {day.get('fuel_available', 'Unknown')}
Refueling Schedule: {day.get('refueling_schedule', 'Unknown')}"""

    def monitor_power(self, power_system: dict[str, Any]) -> str:
        """Monitor power system performance.

        Args:
            power_system: Current power system status

        Returns:
            Power monitoring report
        """
        prompt = f"""Power System Status:
{self._format_power_system(power_system)}

Monitor power by:
- Checking voltage and frequency
- Monitoring fuel levels
- Tracking engine temperature
- Identifying potential issues
- Reporting to Gaffer on power status"""
        return self._call_model(prompt)

    def _format_power_system(self, system: dict) -> str:
        return f"""Generator Running: {system.get('running', False)}
Output Voltage: {system.get('voltage', 'Unknown')}
Fuel Level: {system.get('fuel_level', 'Unknown')}
Engine Temp: {system.get('engine_temp', 'Unknown')}"""
