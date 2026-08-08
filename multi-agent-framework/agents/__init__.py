# Multi-Agent Framework - Filmmaking Agents Package
# Agent definitions and orchestration for StoryOS

from .registry import AgentRegistry, get_agent, list_agents, get_agent_handoffs

# Initialize the global registry
registry = AgentRegistry()

# Import all agent modules to register them
from . import director
from . import producer
from . import first_ad
from . import second_ad
from . import executive_producer
from . import line_producer
from . import cinematographer
from . import camera_operator
from . import first_ac
from . import second_ac
from . import film_loader
from . import screenwriter
from . import script_supervisor
from . import casting_director
from . import production_designer
from . import art_director
from . import set_decorator
from . import prop_master
from . import editor
from . import vfx_supervisor
from . import sound_mixer
from . import boom_operator
from . import costume_designer
from . import wardrobe_supervisor
from . import makeup_artist
from . import spfx_makeup_designer
from . import gaffer
from . import key_grip
from . import best_boy_gaffer
from . import best_boy_grip
from . import dolly_grip
from . import grip
from . import electrician
from . import generator_operator
from . import dit

__all__ = [
    'registry',
    'get_agent',
    'list_agents',
    'get_agent_handoffs',
]
