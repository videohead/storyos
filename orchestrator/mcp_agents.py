"""
MCP Server for StoryOS Agents.

Serves the multi-agent framework as MCP Components (Tools, Resources, Prompts)
for external AI tools like VS Code Copilot, Cursor, etc.
"""

import json
import os
import yaml
from typing import Any, Dict, List, Optional
from fastapi import HTTPException
from mcp.server import Server
from mcp.server.models import (
    Tool,
    Resource,
    Prompt,
)
from pydantic import BaseModel

# ── Agent Registry ──────────────────────────────────────────────────────────


class AgentRegistry:
    """
    Registry for managing filmmaking agents.
    
    Agents are defined as YAML frontmatter + markdown body files.
    """
    
    def __init__(self, agents_dir: str):
        self.agents: Dict[str, dict] = {}
        self.agents_dir = agents_dir
        self._load_agents()
    
    def _load_agents(self):
        """Load all agent files from the agents directory."""
        if not os.path.exists(self.agents_dir):
            print(f"Agents directory not found: {self.agents_dir}")
            return
        
        for filename in os.listdir(self.agents_dir):
            if filename.endswith('.agent.md'):
                filepath = os.path.join(self.agents_dir, filename)
                agent = self._parse_agent_file(filepath)
                if agent:
                    agent_name = agent.get('name', filename.replace('.agent.md', ''))
                    self.agents[agent_name.lower()] = agent
    
    def _parse_agent_file(self, filepath: str) -> Optional[dict]:
        """Parse an agent file with YAML frontmatter and markdown body."""
        try:
            with open(filepath, 'r') as f:
                content = f.read()
            
            if content.startswith('---'):
                parts = content.split('---', 2)
                if len(parts) >= 3:
                    frontmatter = yaml.safe_load(parts[1])
                    body = parts[2].strip()
                    
                    frontmatter['system_prompt'] = body
                    frontmatter['file'] = filepath
                    return frontmatter
            
            return None
        except Exception as e:
            print(f"Error loading agent file {filepath}: {e}")
            return None
    
    def get_agent(self, name: str) -> Optional[dict]:
        """Get an agent by name (case-insensitive)."""
        return self.agents.get(name.lower())
    
    def list_agents(self) -> List[dict]:
        """List all registered agents."""
        return list(self.agents.values())
    
    def get_agents_by_department(self, department: str) -> List[dict]:
        """Get all agents in a department."""
        departments = {
            'production': [
                'producer', 'director', 'first_ad', 'second_ad',
                'executive_producer', 'line_producer', 'unit_production_manager',
                'production_coordinator', 'production_accountant'
            ],
            'camera': [
                'cinematographer', 'camera_operator', 'gaffer', 'best_boy_gaffer',
                'grip', 'best_boy_grip', 'dolly_grip', 'key_grip'
            ],
            'sound': [
                'sound_mixer', 'sound_assistant', 'boom_operator'
            ],
            'art': [
                'production_designer', 'art_director', 'set_decorator',
                'set_dresser', 'prop_master', 'costume_designer',
                'costume_coordinator', 'wardrobe_supervisor', 'hair_stylist',
                'makeup_artist', 'spfx_makeup_designer'
            ],
            'post': [
                'editor', 'on_set_editor', 'vfx_supervisor', 'vfx_coordinator',
                'special_effects_supervisor', 'dit', 'film_loader'
            ],
            'script': [
                'screenwriter', 'script_supervisor', 'casting_director'
            ],
            'technical': [
                'previsualization_artist', 'transportation_coordinator',
                'generator_operator', 'electrician', 'production_designer'
            ],
        }
        
        agent_names = departments.get(department.lower(), [])
        return [
            agent for agent in self.agents.values()
            if agent.get('name', '').lower() in agent_names
        ]


# ── MCP Server ──────────────────────────────────────────────────────────────


class AgentRequest(BaseModel):
    """Request to invoke an agent."""
    agent: str
    prompt: str
    context: Optional[Dict[str, Any]] = None


class MCPAgentServer:
    """
    MCP Server that exposes StoryOS agents as MCP Components.
    
    This allows external AI tools (VS Code Copilot, Cursor, etc.) to
    invoke StoryOS agents through the MCP protocol.
    """
    
    def __init__(self, agents_dir: str):
        self.server = Server("storyos-agents")
        self.registry = AgentRegistry(agents_dir)
        self._setup_components()
    
    def _setup_components(self):
        """Set up MCP Tools, Resources, and Prompts."""
        
        # ── Tools ───────────────────────────────────────────────────────
        
        # Tool: invoke_agent
        @self.server.tool()
        async def invoke_agent(agent: str, prompt: str, context: str = "{}") -> str:
            """
            Invoke a StoryOS agent to process a request.
            
            Args:
                agent: The agent name (e.g., 'director', 'screenwriter', 'cinematographer')
                prompt: The prompt to send to the agent
                context: JSON string of additional context (optional)
            
            Returns:
                The agent's response
            """
            agent_data = self.registry.get_agent(agent)
            if not agent_data:
                available = ', '.join(self.registry.agents.keys())
                raise HTTPException(
                    status_code=404,
                    detail=f"Agent '{agent}' not found. Available: {available}"
                )
            
            # Parse context
            try:
                ctx = json.loads(context) if context else {}
            except json.JSONDecodeError:
                ctx = {}
            
            # Build the full prompt with system prompt
            system_prompt = agent_data.get('system_prompt', '')
            full_prompt = f"{system_prompt}\n\n---\n\nUser Request: {prompt}\n\nContext: {json.dumps(ctx, indent=2)}"
            
            # Return agent metadata and prompt (actual LLM call would happen here)
            response = {
                "agent": agent_data.get('name'),
                "description": agent_data.get('description', ''),
                "tools": agent_data.get('tools', []),
                "handoffs": agent_data.get('handoffs', []),
                "response": full_prompt,  # Placeholder - actual LLM call would go here
            }
            
            return json.dumps(response, indent=2)
        
        # Tool: list_agents
        @self.server.tool()
        async def list_agents(department: str = "") -> str:
            """
            List all available StoryOS agents, optionally filtered by department.
            
            Args:
                department: Filter by department (production, camera, sound, art, post, script, technical)
            
            Returns:
                JSON list of agents
            """
            if department:
                agents = self.registry.get_agents_by_department(department)
            else:
                agents = self.registry.list_agents()
            
            result = [
                {
                    "name": agent.get('name'),
                    "description": agent.get('description', ''),
                    "tools": agent.get('tools', []),
                    "handoffs": len(agent.get('handoffs', [])),
                }
                for agent in agents
            ]
            
            return json.dumps(result, indent=2)
        
        # Tool: get_agent_info
        @self.server.tool()
        async def get_agent_info(agent: str) -> str:
            """
            Get detailed information about a specific agent.
            
            Args:
                agent: The agent name
            
            Returns:
                JSON agent information including system prompt
            """
            agent_data = self.registry.get_agent(agent)
            if not agent_data:
                available = ', '.join(self.registry.agents.keys())
                raise HTTPException(
                    status_code=404,
                    detail=f"Agent '{agent}' not found. Available: {available}"
                )
            
            return json.dumps(agent_data, indent=2)
        
        # Tool: get_agent_handoffs
        @self.server.tool()
        async def get_agent_handoffs(agent: str) -> str:
            """
            Get the handoff configurations for an agent.
            
            Args:
                agent: The agent name
            
            Returns:
                JSON list of handoff configurations
            """
            agent_data = self.registry.get_agent(agent)
            if not agent_data:
                available = ', '.join(self.registry.agents.keys())
                raise HTTPException(
                    status_code=404,
                    detail=f"Agent '{agent}' not found. Available: {available}"
                )
            
            handoffs = agent_data.get('handoffs', [])
            return json.dumps(handoffs, indent=2)
        
        # ── Resources ───────────────────────────────────────────────────
        
        # Resource: agent_registry
        @self.server.resource()
        async def agent_registry() -> str:
            """
            Full agent registry as a resource.
            
            Returns:
                JSON of all agents
            """
            agents = self.registry.list_agents()
            return json.dumps(agents, indent=2)
        
        # Resource: agents_by_department
        @self.server.resource()
        async def agents_by_department(department: str) -> str:
            """
            Agents grouped by department.
            
            Args:
                department: The department name
            
            Returns:
                JSON of agents in the department
            """
            agents = self.registry.get_agents_by_department(department)
            return json.dumps(agents, indent=2)
        
        # ── Prompts ─────────────────────────────────────────────────────
        
        @self.server.prompt()
        async def agent_prompt(agent: str, user_prompt: str) -> str:
            """
            Get a formatted prompt for invoking an agent.
            
            Args:
                agent: The agent name
                user_prompt: The user's prompt
            
            Returns:
                Formatted prompt with agent's system prompt
            """
            agent_data = self.registry.get_agent(agent)
            if not agent_data:
                available = ', '.join(self.registry.agents.keys())
                raise HTTPException(
                    status_code=404,
                    detail=f"Agent '{agent}' not found. Available: {available}"
                )
            
            system_prompt = agent_data.get('system_prompt', '')
            formatted = f"{system_prompt}\n\n---\n\nUser Request: {user_prompt}"
            
            return formatted
    
    def get_server(self) -> Server:
        """Get the MCP server instance."""
        return self.server


# ── FastAPI Integration ─────────────────────────────────────────────────────


def create_mcp_agent_router(agents_dir: str):
    """
    Create a FastAPI router for MCP agent endpoints.
    
    This integrates the MCP server with the existing FastAPI orchestrator.
    """
    from fastapi import APIRouter
    
    router = APIRouter(prefix="/api/agents", tags=["agents"])
    mcp_server = MCPAgentServer(agents_dir)
    
    @router.get("/list")
    async def api_list_agents(department: str = ""):
        """List all agents, optionally filtered by department."""
        if department:
            agents = mcp_server.registry.get_agents_by_department(department)
        else:
            agents = mcp_server.registry.list_agents()
        
        return {
            "count": len(agents),
            "agents": [
                {
                    "name": agent.get('name'),
                    "description": agent.get('description', ''),
                    "tools": agent.get('tools', []),
                    "handoffs": len(agent.get('handoffs', [])),
                }
                for agent in agents
            ]
        }
    
    @router.get("/info/{agent_name}")
    async def api_get_agent_info(agent_name: str):
        """Get detailed information about an agent."""
        agent_data = mcp_server.registry.get_agent(agent_name)
        if not agent_data:
            available = ', '.join(mcp_server.registry.agents.keys())
            raise HTTPException(
                status_code=404,
                detail=f"Agent '{agent_name}' not found. Available: {available}"
            )
        
        return agent_data
    
    @router.post("/invoke")
    async def api_invoke_agent(request: AgentRequest):
        """Invoke an agent to process a request."""
        agent_data = mcp_server.registry.get_agent(request.agent)
        if not agent_data:
            available = ', '.join(mcp_server.registry.agents.keys())
            raise HTTPException(
                status_code=404,
                detail=f"Agent '{request.agent}' not found. Available: {available}"
            )
        
        ctx = request.context or {}
        system_prompt = agent_data.get('system_prompt', '')
        full_prompt = f"{system_prompt}\n\n---\n\nUser Request: {request.prompt}\n\nContext: {json.dumps(ctx, indent=2)}"
        
        return {
            "agent": agent_data.get('name'),
            "description": agent_data.get('description', ''),
            "tools": agent_data.get('tools', []),
            "handoffs": agent_data.get('handoffs', []),
            "prompt": full_prompt,
            "status": "ready",  # Placeholder - actual LLM call would go here
        }
    
    @router.get("/handoffs/{agent_name}")
    async def api_get_agent_handoffs(agent_name: str):
        """Get the handoff configurations for an agent."""
        agent_data = mcp_server.registry.get_agent(agent_name)
        if not agent_data:
            available = ', '.join(mcp_server.registry.agents.keys())
            raise HTTPException(
                status_code=404,
                detail=f"Agent '{agent_name}' not found. Available: {available}"
            )
        
        return agent_data.get('handoffs', [])
    
    @router.get("/departments")
    async def api_list_departments():
        """List all available departments."""
        departments = [
            'production',
            'camera',
            'sound',
            'art',
            'post',
            'script',
            'technical',
        ]
        return {"departments": departments}
    
    return router
