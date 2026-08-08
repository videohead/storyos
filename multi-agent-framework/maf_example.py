"""MAF example scaffold and local-demo for the agent-framework repo.

This file provides two modes:
- If the `agent-framework` package is installed, it prints guidance for wiring
  the Microsoft Agent Framework to this project (see MAF_README.md).
- Otherwise it runs a small simulated two-agent demo that uses the existing
  `api_client.simple_chat` helper so you can validate your model/proxy.

Run:
  python maf_example.py

Notes:
  - This script intentionally avoids assuming the exact MAF Python API so
    it remains safe to run even if the installed package has a different
    import path or surface area. The README contains instructions for a
    real MAF integration.
"""
import os
from dotenv import load_dotenv

load_dotenv()

from api_client import simple_chat

try:
    import agent_framework  # type: ignore
    HAS_MAF = True
except Exception:
    HAS_MAF = False


def get_response(prompt: str, model: str | None = None) -> str:
    """Send prompt to local model via api_client.simple_chat."""
    return simple_chat(prompt, model=model or os.getenv("PROXY_TARGET_MODEL") or os.getenv("MODEL") or "qwen")


def simulated_agents_demo(turns: int = 4) -> None:
    """Run a tiny simulated conversation between two named agents.

    This demonstrates how messages flow through the local model and lets
    you validate OpenAI-compatible endpoints and the proxy behavior.
    """
    print("Starting simulated two-agent demo (local model via api_client)")
    prompt = "Agent A: Propose a concise deployment checklist for a model server."
    for i in range(turns):
        print(f"\n=== Turn {i+1} ===")
        print("Prompt to model:\n", prompt)
        resp = get_response(prompt)
        print("Model reply:\n", resp)
        # Create a brief followup prompt that imitates the other agent
        prompt = f"Agent {'B' if i % 2 == 0 else 'A'}: {resp}\nContinue the conversation in one short paragraph." 


def main() -> None:
    if HAS_MAF:
        print("Detected 'agent-framework' package installed.")
        print("See MAF_README.md for example wiring instructions and next steps.")
        return

    print("'agent-framework' package not found — running fallback demo.")
    simulated_agents_demo()


if __name__ == "__main__":
    main()
