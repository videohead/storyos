"""Wardrobe and Makeup Department Advisors — Costume, makeup, and hair."""
from __future__ import annotations

import logging
from typing import Any

from api_client import simple_chat

logger = logging.getLogger(__name__)

__all__ = [
    "CostumeDesignerAdvisor",
    "WardrobeSupervisorAdvisor",
    "MakeupArtistAdvisor",
    "SPFXMakeupDesignerAdvisor",
    "HairStylistAdvisor",
    "CostumeCoordinatorAdvisor",
]


class CostumeDesignerAdvisor:
    """Costume Designer advisor — costume design and character visualization."""

    def __init__(self, name: str = "costume_designer_advisor"):
        self.name = name
        self.role = "Costume Designer"
        self.system_prompt = """You are the Costume Designer Advisor for StoryOS, a filmmaking AI assistant.

Your role is to assist with costume design, character visualization through
clothing, and maintaining costume continuity throughout the production.

You have access to:
- Character descriptions and arcs
- Script and scene descriptions
- Period and style references
- Costume budget information

Guidelines:
- Support character development through costume
- Maintain period and style accuracy
- Consider actor movement and camera requirements
- Balance creativity with practical constraints
- Never compromise storytelling through costume choices"""

    def design_costume(self, character: dict[str, Any], scene_context: dict[str, Any]) -> str:
        """Design costumes for a character.

        Args:
            character: Character description and arc
            scene_context: Scene details and context

        Returns:
            Costume design recommendations
        """
        prompt = f"""Character Details:
{self._format_character(character)}

Scene Context:
{self._format_scene(scene_context)}

Design costumes by:
- Establishing character visual identity
- Selecting colors, fabrics, and styles
- Considering character arc and costume changes
- Accounting for scene requirements (action, weather, etc.)
- Planning for continuity across scenes"""
        return self._call_model(prompt)

    def _format_character(self, character: dict) -> str:
        return f"""Name: {character.get('name', 'Unknown')}
Description: {character.get('description', 'No description')}
Personality: {', '.join(character.get('personality', []))}
Arc: {character.get('arc', 'No arc defined')}"""

    def _format_scene(self, scene: dict) -> str:
        return f"""Scene: {scene.get('scene_number', 'Unknown')}
Location: {scene.get('location', 'Unknown')}
Action Level: {scene.get('action_level', 'Unknown')}
Weather: {scene.get('weather', 'Unknown')}"""


class WardrobeSupervisorAdvisor:
    """Wardrobe Supervisor advisor — wardrobe logistics and continuity."""

    def __init__(self, name: str = "wardrobe_supervisor_advisor"):
        self.name = name
        self.role = "Wardrobe Supervisor"
        self.system_prompt = """You are the Wardrobe Supervisor Advisor for StoryOS, a filmmaking AI assistant.

Your role is to assist with wardrobe logistics, continuity, maintenance,
and managing the wardrobe department staff.

You have access to:
- Costume inventory and status
- Shooting schedule and call times
- Wardrobe continuity documentation
- Repair and maintenance schedules

Guidelines:
- Ensure all costumes are ready and in good condition
- Maintain detailed continuity records
- Coordinate with costume designer on changes
- Manage wardrobe department staff
- Never let wardrobe issues delay shooting"""

    def manage_wardrobe(self, shooting_day: dict[str, Any]) -> str:
        """Manage wardrobe for a shooting day.

        Args:
            shooting_day: Day's shooting schedule with cast and scenes

        Returns:
            Wardrobe management plan
        """
        prompt = f"""Shooting Day:
{self._format_shooting_day(shooting_day)}

Manage wardrobe by:
- Preparing costumes for each actor
- Scheduling fittings and adjustments
- Planning for costume changes between scenes
- Setting up wardrobe stations on set
- Monitoring costume condition throughout shooting"""
        return self._call_model(prompt)

    def _format_shooting_day(self, day: dict) -> str:
        return f"""Date: {day.get('date', 'Unknown')}
Scenes: {', '.join(day.get('scenes', []))}
Cast: {', '.join(day.get('cast', []))}
Costume Changes: {day.get('costume_changes', 'Unknown')}"""


class MakeupArtistAdvisor:
    """Makeup Artist advisor — makeup design and application."""

    def __init__(self, name: str = "makeup_artist_advisor"):
        self.name = name
        self.role = "Makeup Artist"
        self.system_prompt = """You are the Makeup Artist Advisor for StoryOS, a filmmaking AI assistant.

Your role is to assist with makeup design, application planning, and
maintaining makeup continuity throughout the production.

You have access to:
- Character descriptions and makeup requirements
- Scene descriptions and lighting information
- Makeup product inventories
- Continuity documentation

Guidelines:
- Support character development through makeup
- Ensure makeup works on camera
- Maintain makeup continuity between scenes
- Use safe, actor-approved products
- Never compromise actor health or safety"""

    def design_makeup(self, character: dict[str, Any], scene: dict[str, Any]) -> str:
        """Design makeup for a character.

        Args:
            character: Character description
            scene: Scene details and requirements

        Returns:
            Makeup design recommendations
        """
        prompt = f"""Character Details:
{self._format_character(character)}

Scene Requirements:
{self._format_scene(scene)}

Design makeup by:
- Establishing base makeup look
- Planning special effects or character makeup
- Considering lighting and camera requirements
- Accounting for scene conditions (sweat, rain, etc.)
- Planning for continuity across shooting days"""
        return self._call_model(prompt)

    def _format_character(self, character: dict) -> str:
        return f"""Name: {character.get('name', 'Unknown')}
Age Range: {character.get('age_range', 'Unknown')}
Skin Tone: {character.get('skin_tone', 'Unknown')}
Special Requirements: {', '.join(character.get('special_requirements', []))}"""

    def _format_scene(self, scene: dict) -> str:
        return f"""Scene: {scene.get('scene_number', 'Unknown')}
Conditions: {scene.get('conditions', 'Normal')}
Close-ups: {scene.get('closeups', False)}
Special Effects: {scene.get('sfx_makeup', False)}"""


class SPFXMakeupDesignerAdvisor:
    """SPFX Makeup Designer advisor — special effects makeup."""

    def __init__(self, name: str = "spfx_makeup_designer_advisor"):
        self.name = name
        self.role = "Special Effects Makeup Designer"
        self.system_prompt = """You are the SPFX Makeup Designer Advisor for StoryOS, a filmmaking AI assistant.

Your role is to assist with special effects makeup including prosthetics,
wounds, aging, creature design, and other non-standard makeup requirements.

You have access to:
- Character and scene descriptions
- SPFX makeup references and techniques
- Material and supply inventories
- Makeup application schedules

Guidelines:
- Create convincing special effects makeup
- Ensure makeup is camera-ready
- Maintain continuity for SPFX makeup
- Use safe materials on actors
- Plan application and touch-up schedules"""

    def design_spfx_makeup(self, character: dict[str, Any], requirements: dict[str, Any]) -> str:
        """Design special effects makeup.

        Args:
            character: Character requiring SPFX makeup
            requirements: Specific SPFX requirements

        Returns:
            SPFX makeup design plan
        """
        prompt = f"""Character:
{self._format_character(character)}

SPFX Requirements:
{self._format_requirements(requirements)}

Design SPFX makeup by:
- Selecting appropriate techniques (prosthetics, paint, etc.)
- Planning material selection
- Creating application schedule
- Planning for touch-ups and continuity
- Considering camera testing requirements"""
        return self._call_model(prompt)

    def _format_character(self, character: dict) -> str:
        return f"""Name: {character.get('name', 'Unknown')}
Application Area: {character.get('application_area', 'Unknown')}
Duration: {character.get('wear_duration', 'Unknown')}"""

    def _format_requirements(self, req: dict) -> str:
        return f"""Type: {req.get('type', 'Unknown')}
Complexity: {req.get('complexity', 'Unknown')}
Shooting Days: {req.get('shooting_days', 'Unknown')}
Touch-up Frequency: {req.get('touchup_frequency', 'Unknown')}"""


class HairStylistAdvisor:
    """Hair Stylist advisor — hair design and maintenance."""

    def __init__(self, name: str = "hair_stylist_advisor"):
        self.name = name
        self.role = "Hair Stylist"
        self.system_prompt = """You are the Hair Stylist Advisor for StoryOS, a filmmaking AI assistant.

Your role is to assist with hair design, styling, and maintenance for
all cast members throughout the production.

You have access to:
- Character descriptions and hair requirements
- Scene descriptions and conditions
- Hair product and supply inventories
- Styling schedule and call times

Guidelines:
- Support character through hair design
- Maintain hair continuity between scenes
- Consider camera and lighting requirements
- Use safe products on actors
- Plan styling and touch-up schedules"""

    def design_hair(self, character: dict[str, Any], scene: dict[str, Any]) -> str:
        """Design hair for a character.

        Args:
            character: Character description
            scene: Scene details and conditions

        Returns:
            Hair design recommendations
        """
        prompt = f"""Character:
{self._format_character(character)}

Scene Requirements:
{self._format_scene(scene)}

Design hair by:
- Establishing character hair style
- Planning for scene conditions (wind, rain, action, etc.)
- Considering period and style accuracy
- Planning for continuity across shooting days
- Scheduling touch-ups between takes"""
        return self._call_model(prompt)

    def _format_character(self, character: dict) -> str:
        return f"""Name: {character.get('name', 'Unknown')}
Hair Type: {character.get('hair_type', 'Unknown')}
Wigs Required: {character.get('wigs_required', False)}"""

    def _format_scene(self, scene: dict) -> str:
        return f"""Scene: {scene.get('scene_number', 'Unknown')}
Conditions: {scene.get('conditions', 'Normal')}
Action Level: {scene.get('action_level', 'Unknown')}"""


class CostumeCoordinatorAdvisor:
    """Costume Coordinator advisor — costume logistics and inventory."""

    def __init__(self, name: str = "costume_coordinator_advisor"):
        self.name = name
        self.role = "Costume Coordinator"
        self.system_prompt = """You are the Costume Coordinator Advisor for StoryOS, a filmmaking AI assistant.

Your role is to assist with costume logistics, inventory management,
fittings coordination, and supporting the Costume Designer and Wardrobe team.

You have access to:
- Costume inventory and acquisition status
- Fitting schedules and actor availability
- Costume maintenance and repair records
- Rental and purchase orders

Guidelines:
- Maintain accurate costume inventory
- Coordinate fittings efficiently
- Track costume acquisitions and rentals
- Support wardrobe department logistics
- Never let costume logistics delay shooting"""

    def coordinate_costumes(self, shooting_schedule: dict[str, Any]) -> str:
        """Coordinate costumes for shooting schedule.

        Args:
            shooting_schedule: Schedule with scenes and cast

        Returns:
            Costume coordination plan
        """
        prompt = f"""Shooting Schedule:
{self._format_schedule(shooting_schedule)}

Coordinate costumes by:
- Tracking costume status for each scene
- Scheduling fittings for new or returning actors
- Monitoring costume repairs and alterations
- Planning costume transportation between locations
- Ensuring all costumes are ready for shooting days"""
        return self._call_model(prompt)

    def _format_schedule(self, schedule: dict) -> str:
        return f"""Shooting Days: {schedule.get('shooting_days', 'Unknown')}
New Costumes Needed: {schedule.get('new_costumes', 'Unknown')}
Fittings Scheduled: {schedule.get('fittings', 'Unknown')}
Locations: {', '.join(schedule.get('locations', []))}"""
