"""Filmmaking Advisors — AI agents for digital filmmaking.

Provides specialized advisors organized by film production department:
- Production: Producer, Director, UPM, Line Producer, ADs
- Camera: Cinematographer, Camera Operator, ACs, Film Loader
- Script/Story: Screenwriter, Script Supervisor, Casting Director
- Art: Production Designer, Art Director, Set Decorator, Prop Master
- Post-Production: Editor, VFX Supervisor, VFX Coordinator, On-Set Editor
- Sound: Sound Mixer, Boom Operator, Sound Assistant
- Wardrobe/Makeup: Costume Designer, Wardrobe Supervisor, Makeup Artist, SPFX
- Grip & Electric: Gaffer, Key Grip, Best Boys, Dolly Grip, Electrician
- Additional: Location Manager, Stunt Coordinator, Special Effects, Transportation

Each advisor follows the StoryOS adapter pattern with:
- System prompt defining role and responsibilities
- Specialized methods for domain-specific tasks
- Integration with MAF api_client for model calls
"""
from __future__ import annotations

import logging
from typing import Any

from api_client import simple_chat

logger = logging.getLogger(__name__)

__all__ = [
    # Production
    "ProducerAdvisor",
    "DirectorAdvisor",
    "FirstADAdvisor",
    "SecondADAdvisor",
    "ExecutiveProducerAdvisor",
    "LineProducerAdvisor",
    "UnitProductionManagerAdvisor",
    "ProductionCoordinatorAdvisor",
    "ProductionAccountantAdvisor",
    "LocationManagerAdvisor",
    "TransportationCoordinatorAdvisor",
    # Camera
    "CinematographerAdvisor",
    "CameraOperatorAdvisor",
    "FirstACAdvisor",
    "SecondACAdvisor",
    "FilmLoaderAdvisor",
    # Script/Story
    "ScreenwriterAdvisor",
    "ScriptSupervisorAdvisor",
    "CastingDirectorAdvisor",
    # Art
    "ProductionDesignerAdvisor",
    "ArtDirectorAdvisor",
    "SetDecoratorAdvisor",
    "PropMasterAdvisor",
    "SetDresserAdvisor",
    # Post-Production
    "EditorAdvisor",
    "VFXSupervisorAdvisor",
    "VFXCoordinatorAdvisor",
    "OnSetEditorAdvisor",
    "DITAdvisor",
    "PrevisualizationArtistAdvisor",
    # Sound
    "SoundMixerAdvisor",
    "BoomOperatorAdvisor",
    "SoundAssistantAdvisor",
    # Wardrobe/Makeup
    "CostumeDesignerAdvisor",
    "WardrobeSupervisorAdvisor",
    "MakeupArtistAdvisor",
    "SPFXMakeupDesignerAdvisor",
    "HairStylistAdvisor",
    "CostumeCoordinatorAdvisor",
    # Grip & Electric
    "GafferAdvisor",
    "KeyGripAdvisor",
    "BestBoyGafferAdvisor",
    "BestBoyGripAdvisor",
    "DollyGripAdvisor",
    "GripAdvisor",
    "ElectricianAdvisor",
    "GeneratorOperatorAdvisor",
    # Specialized
    "StuntCoordinatorAdvisor",
    "SpecialEffectsSupervisorAdvisor",
    "MovieArmorerAdvisor",
]


# ============================================================================
# Production Department
# ============================================================================

class ProducerAdvisor:
    """Producer advisor — creative and business oversight of the production."""

    def __init__(self, name: str = "producer_advisor"):
        self.name = name
        self.role = "Producer"
        self.system_prompt = """You are the Producer Advisor for StoryOS, a filmmaking AI assistant.

Your role is to guide creators through the creative and business aspects of film production.
You oversee the entire production from development through delivery, balancing creative vision
with practical constraints.

You have access to:
- Story World and narrative context
- Budget and schedule information
- Department heads and crew information
- Asset generation status (via ComfyUI integration)

Guidelines:
- Provide strategic creative guidance
- Balance creative vision with practical constraints
- Coordinate between departments
- Make decisions that serve the story
- Never override the creator's final creative decisions
- Be specific and actionable in your recommendations"""

    def _call_model(self, prompt: str) -> str:
        return simple_chat(prompt, model="qwen")

    def develop_story_idea(self, idea: str, context: dict[str, Any] = None) -> str:
        """Develop a story idea into a viable production concept.

        Args:
            idea: Initial story concept or logline
            context: Additional context (genre, budget, target audience)

        Returns:
            Development recommendations
        """
        prompt = f"""Story Idea: {idea}

Context: {context or 'Not specified'}

Develop this story idea by:
- Evaluating narrative strength and commercial viability
- Suggesting thematic development
- Identifying potential production challenges
- Recommending next development steps
- Considering budget implications"""
        return self._call_model(prompt)

    def coordinate_departments(self, departments: list[dict[str, Any]]) -> str:
        """Coordinate between production departments.

        Args:
            departments: List of department status reports

        Returns:
            Coordination recommendations
        """
        prompt = f"""Department Status Reports:
{self._format_departments(departments)}

Coordinate these departments by:
- Identifying dependencies and conflicts
- Suggesting communication improvements
- Flagging potential bottlenecks
- Recommending resource reallocation"""
        return self._call_model(prompt)

    def _format_departments(self, departments: list[dict]) -> str:
        return "\n\n".join(
            f"## {dept.get('name', 'Unknown')}\n{dept.get('report', 'No report')}"
            for dept in departments
        )


class DirectorAdvisor:
    """Director advisor — creative vision and storytelling guidance."""

    def __init__(self, name: str = "director_advisor"):
        self.name = name
        self.role = "Director"
        self.system_prompt = """You are the Director Advisor for StoryOS, a filmmaking AI assistant.

Your role is to assist the director with creative vision, storytelling, and
artistic decisions throughout production. You help translate the script into
visual language and guide all creative departments.

You have access to:
- Script and story context
- Storyboards and visual references
- Character descriptions and arcs
- Department creative proposals

Guidelines:
- Support the director's creative vision
- Provide artistic and technical guidance
- Help visualize scenes before shooting
- Coordinate creative decisions across departments
- Never make final creative decisions — recommend and explain
- Be specific about visual storytelling techniques"""

    def visualize_scene(self, scene_description: str, shot_list: list[dict] = None) -> str:
        """Help visualize a scene and plan shots.

        Args:
            scene_description: Scene description from script
            shot_list: Proposed shot list

        Returns:
            Visual planning recommendations
        """
        prompt = f"""Scene Description:
{scene_description}

Proposed Shot List:
{shot_list or 'Not yet planned'}

Help visualize this scene by:
- Suggesting camera angles and movements
- Recommending lighting approaches
- Planning actor blocking
- Identifying key emotional beats
- Suggesting visual metaphors or symbolism"""
        return self._call_model(prompt)

    def guide_performance(self, character: dict[str, Any], scene_context: str) -> str:
        """Guide actor performance for a specific scene.

        Args:
            character: Character description and arc information
            scene_context: Scene context and emotional beats

        Returns:
            Performance guidance recommendations
        """
        prompt = f"""Character:
{self._format_character(character)}

Scene Context:
{scene_context}

Provide performance guidance by:
- Analyzing character motivation in this scene
- Suggesting emotional approach
- Recommending physicality and movement
- Identifying subtext and subtextual beats
- Suggesting interactions with other characters"""
        return self._call_model(prompt)

    def _format_character(self, character: dict) -> str:
        return f"""Name: {character.get('name', 'Unknown')}
Description: {character.get('description', 'No description')}
Arc: {character.get('arc', 'No arc defined')}
Traits: {', '.join(character.get('traits', []))}"""


class FirstADAdvisor:
    """First Assistant Director advisor — set operations and scheduling."""

    def __init__(self, name: str = "first_ad_advisor"):
        self.name = name
        self.role = "First Assistant Director"
        self.system_prompt = """You are the First AD Advisor for StoryOS, a filmmaking AI assistant.

Your role is to assist with set operations, scheduling, and maintaining
the production schedule. You manage the daily call sheet, coordinate
cast and crew, and ensure efficient set operations.

You have access to:
- Production schedule and call sheets
- Cast and crew availability
- Location information
- Weather and environmental data

Guidelines:
- Prioritize efficiency and safety on set
- Maintain clear communication with all departments
- Keep production on schedule
- Manage transitions between setups
- Never compromise safety for speed"""

    def create_call_sheet(self, shooting_day: dict[str, Any]) -> str:
        """Create a daily call sheet.

        Args:
            shooting_day: Day's shooting plan with scenes, cast, locations

        Returns:
            Call sheet with call times and schedule
        """
        prompt = f"""Shooting Day Plan:
{self._format_shooting_day(shooting_day)}

Create a call sheet by:
- Determining call times for cast and crew
- Ordering scenes based on location and cast availability
- Allocating time for setups and rehearsals
- Including meal break requirements
- Adding location and parking information"""
        return self._call_model(prompt)

    def manage_schedule(self, schedule: dict[str, Any]) -> str:
        """Manage and optimize the production schedule.

        Args:
            schedule: Production schedule with scenes and locations

        Returns:
            Schedule optimization recommendations
        """
        prompt = f"""Production Schedule:
{self._format_schedule(schedule)}

Optimize this schedule by:
- Identifying scheduling conflicts
- Suggesting scene reordering for efficiency
- Accounting for cast availability
- Considering location changeovers
- Building in buffer time"""
        return self._call_model(prompt)

    def _format_shooting_day(self, day: dict) -> str:
        return f"""Date: {day.get('date', 'Unknown')}
Location: {day.get('location', 'Unknown')}
Scenes: {', '.join(day.get('scenes', []))}
Cast Required: {', '.join(day.get('cast', []))}
Weather: {day.get('weather', 'Unknown')}"""

    def _format_schedule(self, schedule: dict) -> str:
        return f"""Total Days: {schedule.get('total_days', 'Unknown')}
Shooting Days: {schedule.get('shooting_days', 'Unknown')}
Locations: {', '.join(schedule.get('locations', []))}
Cast Count: {schedule.get('cast_count', 'Unknown')}"""


class LineProducerAdvisor:
    """Line Producer advisor — budget management and logistics."""

    def __init__(self, name: str = "line_producer_advisor"):
        self.name = name
        self.role = "Line Producer"
        self.system_prompt = """You are the Line Producer Advisor for StoryOS, a filmmaking AI assistant.

Your role is to assist with budget management, logistics, and day-to-day
production operations. You ensure the production stays within budget
while meeting creative requirements.

You have access to:
- Production budget and department budgets
- Expense tracking and invoices
- Crew and equipment contracts
- Location agreements

Guidelines:
- Maintain strict budget awareness
- Find cost-effective solutions
- Flag budget overruns immediately
- Balance cost with quality requirements
- Never authorize spending beyond approved budgets"""

    def manage_budget(self, budget: dict[str, Any], expenses: list[dict] = None) -> str:
        """Manage and track production budget.

        Args:
            budget: Production budget breakdown
            expenses: List of expenses incurred

        Returns:
            Budget status and recommendations
        """
        prompt = f"""Production Budget:
{self._format_budget(budget)}

Expenses Incurred:
{expenses or 'No expenses yet'}

Manage this budget by:
- Tracking spending against allocations
- Identifying departments over budget
- Suggesting cost-saving measures
- Forecasting final production cost
- Flagging items requiring Producer approval"""
        return self._call_model(prompt)

    def _format_budget(self, budget: dict) -> str:
        return "\n\n".join(
            f"{dept}: ${alloc:,.2f}"
            for dept, alloc in budget.get("departments", {}).items()
        )


class CinematographerAdvisor:
    """Cinematographer advisor — visual style and lighting guidance."""

    def __init__(self, name: str = "cinematographer_advisor"):
        self.name = name
        self.role = "Cinematographer"
        self.system_prompt = """You are the Cinematographer Advisor for StoryOS, a filmmaking AI assistant.

Your role is to assist with visual style, lighting design, camera movement,
and overall look of the production. You work closely with the Director to
realize the visual vision.

You have access to:
- Script and scene descriptions
- Storyboards and visual references
- Camera and lighting equipment information
- Location photos and measurements

Guidelines:
- Support the Director's visual vision
- Provide technical lighting and camera expertise
- Suggest creative visual solutions
- Consider efficiency of lighting setups
- Never compromise image quality for convenience"""

    def design_lighting(self, scene: dict[str, Any], location: dict[str, Any]) -> str:
        """Design lighting for a scene.

        Args:
            scene: Scene description and mood
            location: Location details and constraints

        Returns:
            Lighting design recommendations
        """
        prompt = f"""Scene Description:
{self._format_scene(scene)}

Location Details:
{self._format_location(location)}

Design lighting by:
- Establishing mood and atmosphere
- Selecting key light direction and quality
- Planning practical lights in the set
- Considering natural light sources
- Accounting for camera movement requirements
- Planning for efficiency between setups"""
        return self._call_model(prompt)

    def plan_camera_movement(self, scene: dict[str, Any]) -> str:
        """Plan camera movement for a scene.

        Args:
            scene: Scene description with action and emotion

        Returns:
            Camera movement plan
        """
        prompt = f"""Scene Description:
{self._format_scene(scene)}

Plan camera movement by:
- Selecting appropriate camera movement (handheld, dolly, steadicam, etc.)
- Planning camera positions and angles
- Coordinating with grip and electric departments
- Considering lens choices
- Balancing creative impact with setup time"""
        return self._call_model(prompt)

    def _format_scene(self, scene: dict) -> str:
        return f"""Scene: {scene.get('scene_number', 'Unknown')}
Description: {scene.get('description', 'No description')}
Mood: {scene.get('mood', 'Unknown')}
Time of Day: {scene.get('time_of_day', 'Unknown')}"""

    def _format_location(self, location: dict) -> str:
        return f"""Name: {location.get('name', 'Unknown')}
Size: {location.get('size', 'Unknown')}
Features: {', '.join(location.get('features', []))}
Constraints: {', '.join(location.get('constraints', []))}"""


class EditorAdvisor:
    """Editor advisor — editorial strategy and storytelling."""

    def __init__(self, name: str = "editor_advisor"):
        self.name = name
        self.role = "Editor"
        self.system_prompt = """You are the Editor Advisor for StoryOS, a filmmaking AI assistant.

Your role is to assist with editorial strategy, storytelling through editing,
and maintaining narrative flow. You help shape the performance and pacing
through editorial choices.

You have access to:
- All filmed footage and dailies
- Script and shot list
- Director's notes and preferences
- Assembly cut and rough cut materials

Guidelines:
- Serve the story through editing
- Maintain narrative continuity
- Suggest pacing improvements
- Identify coverage gaps
- Never make final editorial decisions — recommend and explain"""

    def plan_editing_strategy(self, script: dict[str, Any], footage: list[dict]) -> str:
        """Plan editorial strategy for the production.

        Args:
            script: Script breakdown
            footage: Available footage and coverage

        Returns:
            Editorial strategy recommendations
        """
        prompt = f"""Script Breakdown:
{self._format_script(script)}

Available Footage:
{self._format_footage(footage)}

Plan editorial strategy by:
- Identifying key scenes and beats
- Suggesting editing approach (continuous, intercut, etc.)
- Planning for coverage gaps
- Considering pacing and rhythm
- Identifying potential editorial challenges"""
        return self._call_model(prompt)

    def _format_script(self, script: dict) -> str:
        return f"""Scenes: {len(script.get('scenes', []))}
Act Structure: {script.get('structure', 'Unknown')}
Genre: {script.get('genre', 'Unknown')}
Target Runtime: {script.get('runtime', 'Unknown')}"""

    def _format_footage(self, footage: list[dict]) -> str:
        return "\n\n".join(
            f"Scene {f.get('scene', '?')}: {f.get('coverage', 'Unknown')} takes"
            for f in footage[:10]  # Limit to first 10 for readability
        )


class ScreenwriterAdvisor:
    """Screenwriter advisor — script development and dialogue."""

    def __init__(self, name: str = "screenwriter_advisor"):
        self.name = name
        self.role = "Screenwriter"
        self.system_prompt = """You are the Screenwriter Advisor for StoryOS, a filmmaking AI assistant.

Your role is to assist with script development, dialogue writing, scene
construction, and narrative structure. You help craft compelling screenplays
that are both creative and producible.

You have access to:
- Current script draft
- Character descriptions and arcs
- Scene descriptions
- Story world information

Guidelines:
- Support the writer's creative vision
- Provide constructive script feedback
- Suggest dialogue improvements
- Identify plot holes or inconsistencies
- Consider producibility of scenes
- Never rewrite without understanding the creator's intent"""

    def develop_scene(self, scene_info: dict[str, Any]) -> str:
        """Develop a scene from outline to script format.

        Args:
            scene_info: Scene details including purpose, characters, location

        Returns:
            Scene development recommendations
        """
        prompt = f"""Scene Information:
{self._format_scene_info(scene_info)}

Develop this scene by:
- Writing compelling dialogue
- Establishing character motivations
- Creating dramatic tension
- Including necessary action lines
- Considering pacing within the larger script"""
        return self._call_model(prompt)

    def _format_scene_info(self, scene: dict) -> str:
        return f"""Scene Number: {scene.get('scene_number', 'Unknown')}
Location: {scene.get('location', 'Unknown')}
Characters: {', '.join(scene.get('characters', []))}
Purpose: {scene.get('purpose', 'Unknown')}
Conflict: {scene.get('conflict', 'Unknown')}"""


# ============================================================================
# Helper functions for common formatting
# ============================================================================

def format_asset(asset: dict[str, Any]) -> str:
    """Format an asset for display in advisor prompts."""
    return f"""Type: {asset.get('type', 'Unknown')}
Name: {asset.get('name', 'Unknown')}
Description: {asset.get('description', 'No description')}
Status: {asset.get('status', 'Unknown')}
Metadata: {asset.get('metadata', {})}"""


def format_character(character: dict[str, Any]) -> str:
    """Format a character for display in advisor prompts."""
    return f"""Name: {character.get('name', 'Unknown')}
Description: {character.get('description', 'No description')}
Arc: {character.get('arc', 'No arc defined')}
Traits: {', '.join(character.get('traits', []))}
Relationships: {', '.join(character.get('relationships', []))}"""


def format_location(location: dict[str, Any]) -> str:
    """Format a location for display in advisor prompts."""
    return f"""Name: {location.get('name', 'Unknown')}
Description: {location.get('description', 'No description')}
Features: {', '.join(location.get('features', []))}
Availability: {location.get('availability', 'Unknown')}"""
