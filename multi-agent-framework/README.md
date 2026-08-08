A multi-agent chat oriented MAF project that connects:
VS Code Copilot Chat
To Microsoft Agent Framework
And runs spark-vllm-docker on a DGX Spark Using qwen3.6:35b-a3b-q4_K_M backend

Task 1 - 
Start the model at the Spark with the spark-run.md file and SSH
Wait for it to spin up
It's running Qwen/Qwen3-Coder-Next-FP8

Task 2 - 
Microsoft Agent Framework installation and setup for multi-agent conversational local LLM that is running at 10.0.0.34:11434 and will connect via VS Code Copilot chat
Getting started (quick):

1. Copy `.env.example` to `.env` and update values if needed.
2. Create a Python virtualenv and install requirements:

```bash
python -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
```

3. Run the starter script:

4. See `COPILOT_CONFIG.md` for notes on connecting VS Code Copilot Chat.

Docker / Lando usage
--------------------

Build and run with Docker Compose:

```bash
docker compose build --pull --no-cache
docker compose up
```

Run a one-off container (detached):

```bash
docker compose up -d
docker compose logs -f
```

Using Lando (requires Lando installed):

```bash
lando start
lando run
# or open a shell
lando shell
```

Notes:
- The container reads `.env` when started via `docker compose` so copy `.env.example` to `.env` first.
- The `docker-compose.yml` mounts the repository into the container for fast iteration.

Endpoint probing
----------------

If the model server's OpenAI-compatible endpoints are not responding, run the probe script which tries common paths and methods:

```bash
python tools/probe_endpoints.py --base http://10.0.0.34:11434
```

Share the output here and I can adapt the proxy or client to match the server's supported API shape.


# VSCode Copilot Chat Sample Configuration:
[
	{
		"name": "Sparkles",
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


