"""
Agent Registry - Manages filmmaking agents and their handoffs.
"""

import yaml
import os
from typing import Dict, List, Optional, Any


class AgentRegistry:
    """
    Registry for managing filmmaking agents.
    
    Agents are defined as YAML frontmatter + markdown body files in the agents/ directory.
    The registry loads agent metadata and provides lookup, handoff resolution,
    and department-based filtering.
    """
    
    def __init__(self, agents_dir: str = None):
        """Initialize the registry by loading all agent files."""
        self.agents: Dict[str, dict] = {}
        self._agents_dir = agents_dir or os.path.dirname(__file__)
        self._load_agents()
    
    def _load_agents(self):
        """Load all agent files from the agents directory."""
        if not os.path.exists(self._agents_dir):
            return
        
        for filename in os.listdir(self._agents_dir):
            if filename.endswith('.agent.md'):
                filepath = os.path.join(self._agents_dir, filename)
                agent = self._parse_agent_file(filepath)
                if agent:
                    self.agents[agent['name'].lower()] = agent
    
    def _parse_agent_file(self, filepath: str) -> Optional[dict]:
        """Parse an agent file with YAML frontmatter and markdown body.

        Supports two layouts:
          1. Leading delimiter:  ---\\n<yaml>\\n---\\n<body>
          2. Bare YAML block:    <yaml>\\n---\\n<body>   (no leading ---)
        """
        try:
            with open(filepath, 'r') as f:
                content = f.read()

            if content.startswith('---'):
                # Layout 1: ---\n<yaml>\n---\n<body>
                parts = content.split('---', 2)
                if len(parts) >= 3:
                    yaml_text, body = parts[1], parts[2]
                else:
                    return None
            else:
                # Layout 2: <yaml>\n---\n<body>  (first '---' line is the split)
                lines = content.split('\n')
                sep_idx = next((i for i, ln in enumerate(lines) if ln.strip() == '---'), None)
                if sep_idx is None:
                    return None
                yaml_text = '\n'.join(lines[:sep_idx])
                body = '\n'.join(lines[sep_idx + 1:])

            frontmatter = yaml.safe_load(yaml_text)
            if not isinstance(frontmatter, dict):
                return None

            frontmatter['system_prompt'] = body.strip()
            frontmatter['file'] = filepath
            return frontmatter
        except Exception as e:
            print(f"Error loading agent file {filepath}: {e}")
            return None
    
    def get_agent(self, name: str) -> Optional[dict]:
        """Get an agent by name (case-insensitive)."""
        return self.agents.get(name.lower())
    
    def list_agents(self) -> List[dict]:
        """List all registered agents."""
        return list(self.agents.values())
    
    def get_agent_handoffs(self, name: str) -> List[dict]:
        """Get the handoff configurations for an agent."""
        agent = self.get_agent(name)
        if agent and 'handoffs' in agent:
            return agent['handoffs']
        return []
    
    def get_agents_by_department(self, department: str) -> List[dict]:
        """Get all agents in a department."""
        departments = {
            'production': [
                'producer', 'director', 'first_ad', 'second_ad',
                'executive_producer', 'line_producer', 'unit_production_manager',
                'production_coordinator', 'production_accountant'
            ],
            'camera': [
                'cinematographer', 'camera_operator', 'first_ac', 'second_ac', 'film_loader'
            ],
            'grip_electric': [
                'gaffer', 'key_grip', 'best_boy_gaffer', 'best_boy_grip',
                'dolly_grip', 'grip', 'electrician', 'generator_operator'
            ],
            'art': [
                'production_designer', 'art_director', 'set_decorator', 'prop_master',
                'set_dresser', 'previsualization_artist', 'movie_armorer'
            ],
            'sound': [
                'sound_mixer', 'boom_operator', 'sound_assistant'
            ],
            'wardrobe_makeup': [
                'costume_designer', 'wardrobe_supervisor', 'makeup_artist',
                'spfx_makeup_designer', 'hair_stylist', 'costume_coordinator'
            ],
            'post_production': [
                'editor', 'vfx_supervisor', 'dit', 'vfx_coordinator', 'on_set_editor'
            ],
            'script_story': [
                'screenwriter', 'script_supervisor', 'casting_director'
            ],
            'specialized': [
                'location_manager', 'stunt_coordinator', 'special_effects_supervisor',
                'transportation_coordinator'
            ],
        }
        
        if department.lower() not in departments:
            return []
        
        agent_names = departments[department.lower()]
        return [self.get_agent(name) for name in agent_names if self.get_agent(name)]
    
    def resolve_handoff(self, from_agent: str, to_agent: str) -> Optional[dict]:
        """Resolve a handoff configuration between two agents."""
        handoffs = self.get_agent_handoffs(from_agent)
        for handoff in handoffs:
            if handoff.get('agent', '').lower() == to_agent.lower():
                return handoff
        return None
