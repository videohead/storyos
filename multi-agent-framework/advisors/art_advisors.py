"""Art Department Advisors — Production design and visual environment."""
from __future__ import annotations

import logging
from typing import Any

from api_client import simple_chat

logger = logging.getLogger(__name__)

__all__ = [
    "ProductionDesignerAdvisor",
    "ArtDirectorAdvisor",
    "SetDecoratorAdvisor",
    "PropMasterAdvisor",
    "SetDresserAdvisor",
]


class ProductionDesignerAdvisor:
    """Production Designer advisor — overall visual design of production."""

    def __init__(self, name: str = "production_designer_advisor"):
        self.name = name
        self.role = "Production Designer"
        self.system_prompt = """You are the Production Designer Advisor for StoryOS, a filmmaking AI assistant.

Your role is to assist with the overall visual design of the production,
including locations, sets, color palettes, and the visual world of the story.
You work closely with the Director and Cinematographer to establish the visual language.

You have access to:
- Script and scene descriptions
- Storyboards and visual references
- Location photos and measurements
- Set construction plans

Guidelines:
- Support the Director's visual vision
- Create cohesive visual world
- Balance creativity with budget constraints
- Consider camera requirements in design
- Never compromise storytelling for aesthetics"""

    def design_world(self, script: dict[str, Any], genre: str, period: str) -> str:
        """Design the visual world of the production.

        Args:
            script: Script with scene descriptions
            genre: Genre of the production
            period: Time period (historical, contemporary, futuristic)

        Returns:
            Visual design direction and recommendations
        """
        prompt = f"""Script Overview:
{self._format_script(script)}

Genre: {genre}
Period: {period}

Design the visual world by:
- Establishing color palette and mood
- Defining architectural style
- Selecting materials and textures
- Creating design references for each location
- Considering how design supports character arcs"""
        return self._call_model(prompt)

    def plan_locations(self, locations: list[dict[str, Any]]) -> str:
        """Plan location design and modifications.

        Args:
            locations: List of locations with descriptions

        Returns:
            Location design plan
        """
        prompt = f"""Locations:
{self._format_locations(locations)}

Plan location design by:
- Identifying required modifications
- Suggesting dressing and decoration
- Planning for camera movement
- Considering lighting requirements
- Estimating preparation time"""
        return self._call_model(prompt)

    def _format_script(self, script: dict) -> str:
        return f"""Scenes: {len(script.get('scenes', []))}
Locations Needed: {len(script.get('locations', []))}
Key Visual Elements: {', '.join(script.get('visual_elements', []))}"""

    def _format_locations(self, locations: list[dict]) -> str:
        return "\n\n".join(
            f"## {loc.get('name', 'Unknown')}\n{loc.get('description', 'No description')}"
            for loc in locations
        )


class ArtDirectorAdvisor:
    """Art Director advisor — execution of production design."""

    def __init__(self, name: str = "art_director_advisor"):
        self.name = name
        self.role = "Art Director"
        self.system_prompt = """You are the Art Director Advisor for StoryOS, a filmmaking AI assistant.

Your role is to assist with executing the Production Designer's vision,
managing the art department staff, and ensuring sets are built correctly
and on schedule.

You have access to:
- Set construction plans
- Art department staff assignments
- Material and supply inventories
- Construction schedules

Guidelines:
- Execute the Production Designer's vision accurately
- Manage art department resources efficiently
- Ensure safety in set construction
- Maintain quality standards
- Never cut corners that affect visual quality"""

    def manage_construction(self, set_plans: list[dict[str, Any]]) -> str:
        """Manage set construction and preparation.

        Args:
            set_plans: Set construction plans and specifications

        Returns:
            Construction management plan
        """
        prompt = f"""Set Plans:
{self._format_set_plans(set_plans)}

Manage construction by:
- Prioritizing set construction schedule
- Assigning art department staff
- Coordinating with location manager
- Monitoring material availability
- Planning for inspections and approvals"""
        return self._call_model(prompt)

    def _format_set_plans(self, plans: list[dict]) -> str:
        return "\n\n".join(
            f"## {plan.get('set_name', 'Unknown')}\n"
            f"Size: {plan.get('size', 'Unknown')}\n"
            f"Status: {plan.get('status', 'Unknown')}\n"
            f"Notes: {plan.get('notes', '')}"
            for plan in plans
        )


class SetDecoratorAdvisor:
    """Set Decorator advisor — set dressing and decoration."""

    def __init__(self, name: str = "set_decorator_advisor"):
        self.name = name
        self.role = "Set Decorator"
        self.system_prompt = """You are the Set Decorator Advisor for StoryOS, a filmmaking AI assistant.

Your role is to assist with selecting and placing all decorative items
on set, creating lived-in environments that support the story and period.

You have access to:
- Set design plans
- Prop and decoration inventories
- Rental house catalogs
- Period reference materials

Guidelines:
- Create authentic, lived-in environments
- Support character and story through decoration
- Consider camera framing and focus
- Maintain period accuracy
- Never clutter sets beyond what serves the story"""

    def decorate_set(self, set_info: dict[str, Any]) -> str:
        """Plan set decoration and dressing.

        Args:
            set_info: Set details including size, period, character info

        Returns:
            Decoration plan and item list
        """
        prompt = f"""Set Information:
{self._format_set_info(set_info)}

Plan set decoration by:
- Selecting appropriate furniture and decor
- Establishing character through belongings
- Planning period-appropriate details
- Considering camera angles and visibility
- Creating visual hierarchy in the frame"""
        return self._call_model(prompt)

    def _format_set_info(self, set_info: dict) -> str:
        return f"""Set Name: {set_info.get('set_name', 'Unknown')}
Period: {set_info.get('period', 'Unknown')}
Character: {set_info.get('character', 'Unknown')}
Mood: {set_info.get('mood', 'Unknown')}
Size: {set_info.get('size', 'Unknown')}"""


class PropMasterAdvisor:
    """Prop Master advisor — prop acquisition and management."""

    def __init__(self, name: str = "prop_master_advisor"):
        self.name = name
        self.role = "Prop Master"
        self.system_prompt = """You are the Prop Master Advisor for StoryOS, a filmmaking AI assistant.

Your role is to assist with identifying, acquiring, and managing all props
used in the production. You ensure props are available, functional, and
consistent with the production design.

You have access to:
- Script breakdown and prop list
- Prop inventory and rental catalogs
- Prop construction capabilities
- Vendor and rental house contacts

Guidelines:
- Ensure all props are available when needed
- Maintain prop continuity between scenes
- Consider safety for all props
- Coordinate with Production Designer on prop appearance
- Never use unsafe or illegal props"""

    def manage_props(self, script: dict[str, Any]) -> str:
        """Manage prop acquisition and organization.

        Args:
            script: Script with prop requirements

        Returns:
            Prop management plan
        """
        prompt = f"""Script Requirements:
{self._format_script(script)}

Manage props by:
- Creating detailed prop list from script
- Categorizing props (handheld, set, consumable, etc.)
- Planning acquisition (buy, rent, build, borrow)
- Scheduling prop preparation and maintenance
- Assigning props to scenes and shoots"""
        return self._call_model(prompt)

    def _format_script(self, script: dict) -> str:
        return f"""Scenes: {len(script.get('scenes', []))}
Prop Categories: {', '.join(script.get('prop_categories', []))}
Special Props: {', '.join(script.get('special_props', []))}"""


class SetDresserAdvisor:
    """Set Dresser advisor — final set preparation and maintenance."""

    def __init__(self, name: str = "set_dresser_advisor"):
        self.name = name
        self.role = "Set Dresser"
        self.system_prompt = """You are the Set Dresser Advisor for StoryOS, a filmmaking AI assistant.

Your role is to assist with the final preparation of sets, arranging all
items to create the visual environment, and maintaining set appearance
during shooting.

You have access to:
- Set decoration plans
- Set dressing schedule
- Available set dressing items
- Shot lists and camera positions

Guidelines:
- Execute the Set Decorator's vision
- Maintain set appearance throughout shooting
- Adapt quickly to camera and director needs
- Ensure all items are secure and safe
- Never place items that interfere with camera or lighting"""

    def dress_set(self, set_requirements: dict[str, Any]) -> str:
        """Plan set dressing execution.

        Args:
            set_requirements: Set dressing requirements and plans

        Returns:
            Set dressing execution plan
        """
        prompt = f"""Set Requirements:
{self._format_requirements(set_requirements)}

Plan set dressing by:
- Arranging furniture and decor according to plan
- Adding lived-in details and imperfections
- Ensuring items are camera-ready
- Planning for quick adjustments between setups
- Maintaining continuity between shooting days"""
        return self._call_model(prompt)

    def _format_requirements(self, req: dict) -> str:
        return f"""Set: {req.get('set_name', 'Unknown')}
Priority: {req.get('priority', 'Normal')}
Deadline: {req.get('deadline', 'Unknown')}
Special Requirements: {req.get('special_requirements', 'None')}"""
