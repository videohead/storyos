# StoryOS Multi-Agent Framework (MAF)

A filmmaking-focused multi-agent framework that connects VS Code Copilot Chat to a local LLM running on DGX Spark hardware. Built for StoryOS — an open-source storytelling OS built on WordPress and ComfyUI.

## Architecture

```
Creators → StoryOS → WordPress → Story Graph → MAF Advisors + Generation Engine → ComfyUI → Assets → Production → Editorial
```

The MAF provides AI agents that mirror real film crew positions, organized by department:

- **Production**: Executive Producer, Producer, Director, Line Producer, 1st AD, 2nd AD
- **Camera**: Cinematographer, Camera Operator, 1st AC, 2nd AC, Film Loader
- **Script/Story**: Screenwriter, Script Supervisor, Casting Director
- **Art Department**: Production Designer, Art Director, Set Decorator, Prop Master
- **Post-Production**: Editor, VFX Supervisor, DIT
- **Sound**: Sound Mixer, Boom Operator
- **Wardrobe/Makeup**: Costume Designer, Wardrobe Supervisor, Makeup Artist, SFX Makeup Designer
- **Grip & Electric**: Gaffer, Key Grip, Best Boy (Gaffer/Grip), Dolly Grip, Grip, Electrician, Generator Operator

## Quick Start

### Prerequisites

- DGX Spark or similar GPU hardware running vLLm
- Python 3.9+
- Docker/Lando (optional)

### Setup

1. Copy `.env.example` to `.env` and update values:

```bash
cp .env.example .env
```

2. Create a Python virtualenv and install requirements:

```bash
python -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
```

3. Start the model on DGX Spark (see `spark-run.md`)

4. The model runs at `10.0.0.34:11434` with OpenAI-compatible endpoints

### Running the Framework

```bash
# Health check
python local_agent_framework.py health

# Chat with the model
python local_agent_framework.py chat "Your prompt here"

# Interactive mode
python local_agent_framework.py interactive
```

## Agent System

The framework includes 32+ specialized filmmaking agents, each with:

- **YAML frontmatter**: Agent metadata (name, description, tools, model, handoffs)
- **Markdown body**: System prompt with role, responsibilities, knowledge, approach, and constraints

### Agent Registry

```python
from agents import get_agent, list_agents, get_agent_handoffs

# Get a specific agent
director = get_agent('director')

# List all agents
all_agents = list_agents()

# Get handoffs from an agent
handoffs = get_agent_handoffs('director')

# Get agents by department
camera_agents = get_agents_by_department('camera')
```

### Agent File Format

Each agent is defined in a `.agent.md` file:

```yaml
name: Director
description: Director. Creative vision and storytelling authority.
tools: ['codebase', 'fetch', 'usages', 'search']
model: ['YOUR MODEL HERE (copilot)']
handoffs:
  - label: Consult Cinematographer
    agent: Cinematographer
    prompt: Discuss visual approach for this scene
    send: true
---
You are the Director for StoryOS...
```

### Agent Handoffs

Agents can hand off tasks to other agents using the handoff system:

```python
from agents import resolve_handoff

handoff = resolve_handoff('director', 'cinematographer')
if handoff:
    prompt = handoff['prompt']
    target_agent = handoff['agent']
    # Use the handoff prompt to consult the target agent
```

## Integration with StoryOS

### WordPress Integration

The framework is designed to integrate with StoryOS's WordPress-based content model:

- **Projects**: Film/production projects
- **Story Worlds**: World-building and lore
- **Characters**: Character development and arcs
- **Scenes**: Scene breakdowns and descriptions
- **Shots**: Shot lists and compositions
- **Storyboards**: Visual storyboards
- **Assets**: Generated assets from ComfyUI

### ComfyUI Integration

Assets are generated through ComfyUI workflows, with agents able to:

- Generate concept art and storyboards
- Create character designs
- Produce location references
- Generate visual effects elements

### Advisor Pattern

Agents follow the advisor pattern used in StoryOS:

```python
class DirectorAdvisor:
    def __init__(self):
        self.name = "director"
        self.role = "Director"
        self.system_prompt = load_agent_prompt('director')
    
    def _call_model(self, prompt):
        return simple_chat(prompt, model="qwen")
    
    def review_scene(self, scene_context):
        prompt = f"Scene context: {scene_context}\n\nTask: Review this scene from a directorial perspective..."
        return self._call_model(prompt)
```

## Docker / Lando Usage

### Docker Compose

```bash
docker compose build --pull --no-cache
docker compose up
```

### Lando

```bash
lando start
lando run
# or open a shell
lando shell
```

## Endpoint Probing

If the model server's OpenAI-compatible endpoints are not responding:

```bash
python tools/probe_endpoints.py --base http://10.0.0.34:11434
```

## VS Code Copilot Chat Configuration

Add to your VS Code Copilot Chat settings:

```json
[
	{
		"name": "StoryOS Sparkles",
		"vendor": "customendpoint",
		"apiType": "chat-completions",
		"models": [
			{
				"id": "qwen3.6:35b-a3b-q4_K_M",
				"name": "qwen3.6:35b-a3b-q4_K_M",
				"url": "http://10.0.0.34:11434",
				"toolCalling": true,
				"vision": true,
				"maxInputTokens": 128000,
				"maxOutputTokens": 16000
			}
		]
	}
]
```

## Contributing

1. Create new agent files in `agents/` following the `.agent.md` format
2. Test agents with the local framework
3. Submit a pull request with your agent definition

## License

See the LICENSE file for details.

## Resources

- [StoryOS Architecture](../StoryOS_Architecture.md)
- [Content Model Specification](../Content_Model_Specification.md)
- [REST API Specification](../REST_API_Specification.md)
- [Story Graph Specification](../Story_Graph_Specification.md)


