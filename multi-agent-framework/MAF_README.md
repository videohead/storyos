# Microsoft Agent Framework (MAF) — Example & Run Instructions

This short README explains how to validate your local OpenAI-compatible
model/proxy and how to get started wiring the Microsoft Agent Framework
into this repository.

Files added
- [maf_example.py](maf_example.py): A scaffold that either runs a small
  simulated two-agent demo (using `api_client.simple_chat`) or detects the
  installed `agent-framework` package and points you to next steps.
 - [maf_integration.py](maf_integration.py): An adaptive integration scaffold
   that introspects the installed `agent-framework` package at runtime,
   attempts to map common `Agent`/`Orchestrator` APIs, and registers two
   simple agents that forward messages to the local model. Falls back to
   a local orchestrator demo if the package or API mapping isn't available.

Quick start (preferred inside your project's container or venv)

1. Copy `.env.example` to `.env` and adjust values if needed.

2. Create a virtualenv and install dependencies:

```bash
python -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
```

3. Optional — run the local proxy so clients can point to `http://localhost:11435/v1`:

```bash
# run proxy (reads .env for OPENAI_API_BASE / API keys)
python proxy/proxy.py
```

4. Run the example scaffold/demo:

```bash
# Run fallback simulated two-agent demo
python maf_example.py
```

5. Run the adaptive MAF integration (inside the built container where
  `agent-framework` is installed):

```bash
# one-off run using the container venv and installed requirements
docker compose run --rm app python maf_integration.py
```

If `agent-framework` is installed in the image, `maf_integration.py` will
attempt to adapt to its API and start an orchestrator. If it cannot map
the API, it prints discovered symbols and runs a local fallback demo.

Docker (recommended for portability)
----------------------------------

Build the image (this creates a venv inside the image at `/opt/venv`):

```bash
docker compose build --pull --no-cache
```

Run the app service (the repository is mounted into `/app`; the venv
remains inside the image so installed packages persist across hosts):

```bash
docker compose up app
```

Run the proxy service (exposes port `11435` on the host):

```bash
docker compose up proxy
```

You can combine them:

```bash
docker compose up --build
```

If the `agent-framework` package is installed, `maf_example.py` will detect
it and print a note; the repository does not assume a specific MAF API
surface in the scaffold to avoid breaking on different package versions.

Guidance for wiring a real Microsoft Agent Framework integration

- Ensure `OPENAI_API_BASE` and `OPENAI_API_KEY` in `.env` point to your
  OpenAI-compatible endpoint (the repo's `proxy` is useful for normalizing
  client requests).
- Follow the Microsoft Agent Framework overview and examples to define
  agents and orchestrators. Point MAF's HTTP client to the `OPENAI_API_BASE`.
- Typical steps:
  - Install `agent-framework` (already included in `requirements.txt`).
  - Create an agents configuration file or Python script that imports the
    MAF server/client classes and registers agents and tools.
  - Start the MAF orchestrator and verify it can call the local model
    using the same health endpoints you validated earlier.

If you want, I can:

- Scaffold a concrete MAF Python example that uses the real MAF API (I will
  read your installed package to ensure the code matches the actual API), or
- Wire the `proxy` to act as the `OPENAI_API_BASE` for Copilot Chat and
  provide a tested end-to-end script.
