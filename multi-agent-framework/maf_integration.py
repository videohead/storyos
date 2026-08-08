"""Adaptive Microsoft Agent Framework integration scaffold.

This script attempts to detect the installed `agent-framework` package at
runtime, adapt to common API shapes (Agent/Orchestrator/Client), and
register two simple agents that forward messages to the local model via
`api_client.simple_chat`.

If the installed package's API cannot be automatically adapted, the
script prints discovered symbols and falls back to a local simulated
orchestrator so you can still validate agents end-to-end.

Run (inside container/image where `agent-framework` is installed):

  python maf_integration.py

The script is defensive: it will not crash if the package is missing or
has an unexpected interface.
"""
from __future__ import annotations

import importlib
import inspect
import os
from dotenv import load_dotenv
from typing import Any, Callable

load_dotenv()

from api_client import simple_chat


OPENAI_API_BASE = os.getenv("OPENAI_API_BASE") or os.getenv("API_BASE")
OPENAI_API_KEY = os.getenv("OPENAI_API_KEY") or os.getenv("API_KEY")


def discover_package(name: str = "agent_framework") -> Any | None:
    try:
        pkg = importlib.import_module(name)
        print(f"Discovered package: {name}")
        return pkg
    except Exception as e:
        print(f"Package '{name}' not importable: {e}")
        return None


def find_symbol(module: Any, keywords: list[str]) -> Any | None:
    for attr in dir(module):
        for kw in keywords:
            if kw.lower() in attr.lower():
                try:
                    val = getattr(module, attr)
                    if inspect.isclass(val) or inspect.isfunction(val):
                        return val
                except Exception:
                    continue
    return None


def make_agent_instance(AgentClass: Any, name: str, handler: Callable[[str], str]) -> Any:
    # Try common constructor patterns
    try:
        return AgentClass(name=name, handler=handler)
    except Exception:
        pass
    try:
        return AgentClass(name, handler)
    except Exception:
        pass
    try:
        inst = AgentClass(name)
        # try to attach handler via common APIs
        if hasattr(inst, "set_handler"):
            inst.set_handler(handler)
        elif hasattr(inst, "on_message"):
            inst.on_message = handler
        elif hasattr(inst, "handle"):
            inst.handle = handler
        else:
            # last resort: set attribute
            setattr(inst, "handler", handler)
        return inst
    except Exception:
        raise RuntimeError(f"Unable to instantiate agent with {AgentClass}")


def make_orchestrator_instance(OrchClass: Any, agents: list[Any]) -> Any:
    try:
        return OrchClass(agents=agents)
    except Exception:
        pass
    try:
        return OrchClass(agents)
    except Exception:
        pass
    try:
        inst = OrchClass()
        if hasattr(inst, "register"):
            for a in agents:
                inst.register(a)
        elif hasattr(inst, "add_agent"):
            for a in agents:
                inst.add_agent(a)
        return inst
    except Exception:
        raise RuntimeError(f"Unable to instantiate orchestrator with {OrchClass}")


def simple_agent_handler_factory(name: str, model: str | None = None) -> Callable[[str], str]:
    def handler(msg: str) -> str:
        prompt = f"From {name}: {msg}\nRespond concisely."
        return simple_chat(prompt, model=model)

    return handler


def fallback_local_orchestrator():
    print("Running fallback local orchestrator (no MAF API available).")
    a_handler = simple_agent_handler_factory("AgentA")
    b_handler = simple_agent_handler_factory("AgentB")

    seed = "AgentA: Provide a short deployment checklist for a model server."
    current = seed
    for i in range(4):
        if i % 2 == 0:
            out = a_handler(current)
            print(f"AgentA -> {out}\n")
            current = f"AgentB: Please respond to AgentA's message: {out}"
        else:
            out = b_handler(current)
            print(f"AgentB -> {out}\n")
            current = f"AgentA: Please respond to AgentB's message: {out}"


def main():
    pkg = discover_package("agent_framework")
    if not pkg:
        print("No 'agent_framework' package found; run inside your container image where requirements are installed.")
        fallback_local_orchestrator()
        return

    # Attempt to find Agent and Orchestrator/Orchestration classes
    AgentClass = find_symbol(pkg, ["Agent", "SimpleAgent"])
    OrchClass = find_symbol(pkg, ["Orchestrator", "Orchestration", "Manager"])

    print("Discovered symbols:")
    print("  AgentClass:", AgentClass)
    print("  OrchClass:", OrchClass)

    if not AgentClass or not OrchClass:
        print("Could not automatically map core classes. Available module attrs listed below:")
        print([a for a in dir(pkg) if not a.startswith("__")])
        print("Falling back to local orchestrator demo.")
        fallback_local_orchestrator()
        return

    # Create two agents using adaptive instantiation and register with orchestrator
    try:
        a_handler = simple_agent_handler_factory("AgentA")
        b_handler = simple_agent_handler_factory("AgentB")

        a_inst = make_agent_instance(AgentClass, "agent-a", a_handler)
        b_inst = make_agent_instance(AgentClass, "agent-b", b_handler)

        orch = make_orchestrator_instance(OrchClass, [a_inst, b_inst])

        print("Starting orchestrator...")
        if hasattr(orch, "start"):
            orch.start()
        elif hasattr(orch, "run"):
            orch.run()
        else:
            print("Orchestrator started but no 'start' or 'run' method found. Listing methods:")
            print([m for m in dir(orch) if not m.startswith("__")])
            print("You can interact with agent instances programmatically from here.")
    except Exception as e:
        print("Adaptive orchestration failed:", e)
        print("Falling back to local orchestrator demo.")
        fallback_local_orchestrator()


if __name__ == "__main__":
    main()
