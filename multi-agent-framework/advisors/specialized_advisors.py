"""Specialized Department Advisors — Stunts, SFX, locations, and transportation."""
from __future__ import annotations

import logging
from typing import Any

from api_client import simple_chat

logger = logging.getLogger(__name__)

__all__ = [
    "StuntCoordinatorAdvisor",
    "SpecialEffectsSupervisorAdvisor",
    "LocationManagerAdvisor",
    "TransportationCoordinatorAdvisor",
    "MovieArmorerAdvisor",
]


class StuntCoordinatorAdvisor:
    """Stunt Coordinator advisor — stunt planning and safety."""

    def __init__(self, name: str = "stunt_coordinator_advisor"):
        self.name = name
        self.role = "Stunt Coordinator"
        self.system_prompt = """You are the Stunt Coordinator Advisor for StoryOS, a filmmaking AI assistant.

Your role is to assist with stunt planning, choreography, safety protocols,
and coordinating stunt performers throughout production.

You have access to:
- Script and scene descriptions with stunt requirements
- Stunt performer information and capabilities
- Safety equipment and protocols
- Medical support resources

Guidelines:
- Prioritize safety above all else
- Plan stunts with clear choreography
- Ensure all stunt performers are properly equipped
- Coordinate with camera department for stunt shots
- Never compromise safety for dramatic effect"""

    def plan_stunt(self, scene: dict[str, Any]) -> str:
        """Plan a stunt sequence.

        Args:
            scene: Scene description with stunt requirements

        Returns:
            Stunt plan with safety protocols
        """
        prompt = f"""Scene Details:
{self._format_scene(scene)}

Plan stunt by:
- Breaking down stunt requirements
- Planning choreography and timing
- Identifying required safety equipment
- Assigning stunt performers
- Coordinating with camera and grip departments"""
        return self._call_model(prompt)

    def _format_scene(self, scene: dict) -> str:
        return f"""Scene: {scene.get('scene_number', 'Unknown')}
Stunt Type: {scene.get('stunt_type', 'Unknown')}
Complexity: {scene.get('complexity', 'Unknown')}
Stunt Performers Needed: {scene.get('stunt_performers', 'Unknown')}
Special Equipment: {', '.join(scene.get('special_equipment', []))}"""

    def ensure_safety(self, stunt_plan: dict[str, Any]) -> str:
        """Ensure safety protocols for a stunt.

        Args:
            stunt_plan: Planned stunt details

        Returns:
            Safety protocol plan
        """
        prompt = f"""Stunt Plan:
{self._format_stunt_plan(stunt_plan)}

Ensure safety by:
- Reviewing all safety protocols
- Checking equipment and rigging
- Confirming stunt performer readiness
- Planning medical support
- Establishing emergency procedures"""
        return self._call_model(prompt)

    def _format_stunt_plan(self, plan: dict) -> str:
        return f"""Stunt Type: {plan.get('type', 'Unknown')}
Height: {plan.get('height', 'Unknown')}
Speed: {plan.get('speed', 'Unknown')}
Safety Equipment: {', '.join(plan.get('safety_equipment', []))}
Medical Support: {plan.get('medical_support', 'Unknown')}"""


class SpecialEffectsSupervisorAdvisor:
    """Special Effects Supervisor advisor — practical on-set effects."""

    def __init__(self, name: str = "special_effects_supervisor_advisor"):
        self.name = name
        self.role = "Special Effects Supervisor"
        self.system_prompt = """You are the Special Effects Supervisor Advisor for StoryOS, a filmmaking AI assistant.

Your role is to assist with practical on-set special effects including
weather effects, pyrotechnics, mechanical effects, and atmospheric effects.

You have access to:
- Scene descriptions with SFX requirements
- Special effects equipment and materials
- Safety protocols for SFX operations
- Vendor and supplier information

Guidelines:
- Ensure all practical effects are safe and reliable
- Coordinate with VFX Supervisor on combined effects
- Plan SFX timing and triggers carefully
- Maintain safety protocols at all times
- Never compromise safety for effect quality"""

    def plan_sfx(self, scene: dict[str, Any]) -> str:
        """Plan practical special effects for a scene.

        Args:
            scene: Scene description with SFX requirements

        Returns:
            SFX plan with safety protocols
        """
        prompt = f"""Scene Details:
{self._format_scene(scene)}

Plan SFX by:
- Identifying practical effect requirements
- Selecting appropriate equipment and materials
- Planning effect timing and triggers
- Coordinating with camera department
- Establishing safety protocols"""
        return self._call_model(prompt)

    def _format_scene(self, scene: dict) -> str:
        return f"""Scene: {scene.get('scene_number', 'Unknown')}
SFX Type: {scene.get('sfx_type', 'Unknown')}
Intensity: {scene.get('intensity', 'Unknown')}
Duration: {scene.get('duration', 'Unknown')}
Safety Level: {scene.get('safety_level', 'Unknown')}"""

    def manage_pyrotechnics(self, scene: dict[str, Any]) -> str:
        """Manage pyrotechnic effects safely.

        Args:
            scene: Scene with pyrotechnic requirements

        Returns:
            Pyrotechnic management plan
        """
        prompt = f"""Scene:
{self._format_scene(scene)}

Manage pyrotechnics by:
- Planning explosive placement and timing
- Establishing safety perimeters
- Coordinating with safety officer
- Testing all devices before shooting
- Ensuring proper licensing and permits"""
        return self._call_model(prompt)

    def manage_weather_effects(self, scene: dict[str, Any]) -> str:
        """Manage weather effects for a scene.

        Args:
            scene: Scene with weather effect requirements

        Returns:
            Weather effects plan
        """
        prompt = f"""Scene:
{self._format_scene(scene)}

Manage weather effects by:
- Planning rain, wind, or fog effects
- Selecting appropriate equipment
- Coordinating with camera department
- Testing effects before shooting
- Monitoring effect consistency"""
        return self._call_model(prompt)


class LocationManagerAdvisor:
    """Location Manager advisor — location scouting and management."""

    def __init__(self, name: str = "location_manager_advisor"):
        self.name = name
        self.role = "Location Manager"
        self.system_prompt = """You are the Location Manager Advisor for StoryOS, a filmmaking AI assistant.

Your role is to assist with location scouting, permits, logistics, and
managing all location-related aspects of production.

You have access to:
- Location database and scouting reports
- Permit requirements and applications
- Location contact information
- Neighborhood and community relations

Guidelines:
- Find locations that serve the story
- Secure all necessary permits and permissions
- Maintain good relationships with locations
- Plan logistics efficiently
- Never let location issues delay shooting"""

    def scout_location(self, requirements: dict[str, Any]) -> str:
        """Scout locations for production needs.

        Args:
            requirements: Location requirements from script and department heads

        Returns:
            Location scouting report
        """
        prompt = f"""Location Requirements:
{self._format_requirements(requirements)}

Scout locations by:
- Identifying potential locations matching requirements
- Assessing location suitability for shooting
- Planning permit requirements
- Evaluating logistics and access
- Creating location reports with photos and measurements"""
        return self._call_model(prompt)

    def _format_requirements(self, req: dict) -> str:
        return f"""Scene Type: {req.get('scene_type', 'Unknown')}
Visual Style: {req.get('visual_style', 'Unknown')}
Space Requirements: {req.get('space', 'Unknown')}
Parking: {req.get('parking', 'Unknown')}
Power: {req.get('power', 'Unknown')}
Noise Constraints: {req.get('noise_constraints', 'Unknown')}"""

    def manage_location_logistics(self, location: dict[str, Any]) -> str:
        """Manage logistics for a location shoot.

        Args:
            location: Location details and shoot requirements

        Returns:
            Location logistics plan
        """
        prompt = f"""Location:
{self._format_location(location)}

Manage logistics by:
- Coordinating with First AD on schedule
- Planning crew and equipment access
- Arranging parking and holding areas
- Managing location fees and deposits
- Coordinating with neighbors and authorities"""
        return self._call_model(prompt)

    def _format_location(self, location: dict) -> str:
        return f"""Name: {location.get('name', 'Unknown')}
Shooting Dates: {location.get('shooting_dates', 'Unknown')}
Crew Size: {location.get('crew_size', 'Unknown')}
Equipment Needs: {', '.join(location.get('equipment_needs', []))}
Special Requirements: {', '.join(location.get('special_requirements', []))}"""


class TransportationCoordinatorAdvisor:
    """Transportation Coordinator advisor — cast and crew transportation."""

    def __init__(self, name: str = "transportation_coordinator_advisor"):
        self.name = name
        self.role = "Transportation Coordinator"
        self.system_prompt = """You are the Transportation Coordinator Advisor for StoryOS, a filmmaking AI assistant.

Your role is to assist with transportation logistics for cast and crew,
equipment transport, and vehicle management throughout production.

You have access to:
- Cast and crew call times
- Location and route information
- Vehicle fleet inventory
- Driver schedules and availability

Guidelines:
- Ensure all cast and crew arrive on time
- Plan efficient transportation routes
- Maintain vehicle fleet properly
- Coordinate with First AD on schedules
- Never let transportation issues delay shooting"""

    def manage_transportation(self, shooting_day: dict[str, Any]) -> str:
        """Manage transportation for a shooting day.

        Args:
            shooting_day: Day's schedule and transportation needs

        Returns:
            Transportation management plan
        """
        prompt = f"""Shooting Day:
{self._format_shooting_day(shooting_day)}

Manage transportation by:
- Scheduling vehicles for cast and crew
- Planning routes and departure times
- Coordinating with location manager
- Arranging equipment transport
- Managing driver schedules"""
        return self._call_model(prompt)

    def _format_shooting_day(self, day: dict) -> str:
        return f"""Location: {day.get('location', 'Unknown')}
Distance from Base: {day.get('distance', 'Unknown')}
Crew Count: {day.get('crew_count', 'Unknown')}
Cast Count: {day.get('cast_count', 'Unknown')}
Equipment Transport: {day.get('equipment_transport', 'Unknown')}"""

    def manage_fleet(self, fleet_info: dict[str, Any]) -> str:
        """Manage vehicle fleet and maintenance.

        Args:
            fleet_info: Fleet inventory and status

        Returns:
            Fleet management plan
        """
        prompt = f"""Fleet Status:
{self._format_fleet(fleet_info)}

Manage fleet by:
- Tracking vehicle maintenance schedules
- Planning vehicle assignments
- Monitoring fuel levels and costs
- Arranging repairs as needed
- Ensuring all vehicles are roadworthy"""
        return self._call_model(prompt)

    def _format_fleet(self, fleet: dict) -> str:
        return f"""Total Vehicles: {fleet.get('total_vehicles', 'Unknown')}
Available: {fleet.get('available', 'Unknown')}
In Maintenance: {fleet.get('maintenance', 'Unknown')}
Fuel Status: {fleet.get('fuel_status', 'Unknown')}"""


class MovieArmorerAdvisor:
    """Movie Armorer advisor — prop firearm safety and management."""

    def __init__(self, name: str = "movie_armorer_advisor"):
        self.name = name
        self.role = "Movie Armorer"
        self.system_prompt = """You are the Movie Armorer Advisor for StoryOS, a filmmaking AI assistant.

Your role is to assist with prop firearm safety, management, and
ensuring all weapon-related scenes are handled safely on set.

You have access to:
- Prop firearm inventory and status
- Safety protocols and regulations
- Scene requirements with weapon use
- Ammunition and blank specifications

Guidelines:
- Safety is absolutely paramount with all weapons
- Ensure all prop firearms are safe and properly handled
- Maintain detailed weapon logs
- Train cast and crew on weapon safety
- Never compromise safety with weapons under any circumstances"""

    def manage_weapons(self, scene: dict[str, Any]) -> str:
        """Manage prop firearms for a scene.

        Args:
            scene: Scene description with weapon requirements

        Returns:
            Weapon management plan with safety protocols
        """
        prompt = f"""Scene Details:
{self._format_scene(scene)}

Manage weapons by:
- Selecting appropriate prop firearms
- Ensuring all weapons are safe
- Planning weapon handling procedures
- Training cast on weapon safety
- Maintaining detailed weapon logs"""
        return self._call_model(prompt)

    def _format_scene(self, scene: dict) -> str:
        return f"""Scene: {scene.get('scene_number', 'Unknown')}
Weapons Required: {', '.join(scene.get('weapons_required', []))}
Ammunition Type: {scene.get('ammunition_type', 'Unknown')}
Cast Handling Weapons: {scene.get('cast_handling', False)}
Safety Level: {scene.get('safety_level', 'Unknown')}"""

    def ensure_weapon_safety(self, weapon_plan: dict[str, Any]) -> str:
        """Ensure safety protocols for weapon use.

        Args:
            weapon_plan: Planned weapon usage details

        Returns:
            Safety protocol plan
        """
        prompt = f"""Weapon Plan:
{self._format_weapon_plan(weapon_plan)}

Ensure safety by:
- Verifying all weapons are safe
- Establishing safety perimeters
- Training all cast and crew
- Planning weapon handling procedures
- Establishing emergency procedures"""
        return self._call_model(prompt)

    def _format_weapon_plan(self, plan: dict) -> str:
        return f"""Weapons: {', '.join(plan.get('weapons', []))}
Cast Trained: {plan.get('cast_trained', False)}
Safety Officer Present: {plan.get('safety_officer', False)}
Safety Briefing Done: {plan.get('safety_briefing', False)}"""
