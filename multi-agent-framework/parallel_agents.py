"""Parallel multi-agent runner for StoryOS.

Fans a task out to multiple filmmaking agents *simultaneously* against the
live vLLM server, or runs a sequential handoff pipeline. This is the fast
path for the "single agent is too slow" problem: instead of one agent
thinking for a long time, N agents work in parallel and their outputs are
merged.

Why this is fast
----------------
The running vLLM server batches concurrent sequences (verified: two
simultaneous requests finish in ~2.3s total vs ~2.1s for one). So dispatching
4 agents at once is roughly as fast as one agent, not 4x slower.

Usage
-----
Fan-out (same task to several agents, in parallel):

    python parallel_agents.py fan --task "Design the lighting for scene 12" \
        --agents director cinematographer gaffer

Pipeline (sequential handoffs: A -> B -> C):

    python parallel_agents.py pipeline --agents director cinematographer editor \
        --task "Break down scene 12 for production"

List agents:

    python parallel_agents.py list [--department camera]

Concurrency is controlled by --concurrency (default from AGENT_CONCURRENCY,
else 4). Keep it modest (2-8) to respect the server's KV-cache budget.

Only stdlib + `requests` + `python-dotenv` are used (already in
requirements.txt). No new dependencies.
"""
from __future__ import annotations

import argparse
import json
import os
import sys
import time
from concurrent.futures import ThreadPoolExecutor, as_completed
from dataclasses import dataclass, field
from typing import Any, Dict, List, Optional

from dotenv import load_dotenv

load_dotenv()

from agents.registry import AgentRegistry  # noqa: E402

API_BASE = (os.getenv("OPENAI_API_BASE") or os.getenv("API_BASE") or "http://localhost:11434/v1").rstrip("/")
API_KEY = os.getenv("OPENAI_API_KEY") or os.getenv("API_KEY") or "local-dev-key"
MODEL = os.getenv("PROXY_TARGET_MODEL") or os.getenv("MODEL") or "unsloth/Qwen3.8-27B-NVFP4"
DEFAULT_CONCURRENCY = int(os.getenv("AGENT_CONCURRENCY", "4"))
DEFAULT_TIMEOUT = int(os.getenv("AGENT_TIMEOUT", "180"))

_HEADERS = {"Authorization": f"Bearer {API_KEY}", "Content-Type": "application/json"}


# ── Model call ──────────────────────────────────────────────────────────────


def chat(system_prompt: str, user_prompt: str, *, model: str = MODEL,
         max_tokens: int = 2048, temperature: float = 0.4,
         timeout: int = DEFAULT_TIMEOUT) -> str:
    """Send one chat completion to the live vLLM server and return the text."""
    url = f"{API_BASE}/chat/completions"
    payload = {
        "model": model,
        "messages": [
            {"role": "system", "content": system_prompt},
            {"role": "user", "content": user_prompt},
        ],
        "max_tokens": max_tokens,
        "temperature": temperature,
        "stream": False,
    }
    import requests  # local import so --help works without the dep
    r = requests.post(url, headers=_HEADERS, json=payload, timeout=timeout)
    r.raise_for_status()
    j = r.json()
    try:
        return j["choices"][0]["message"]["content"]
    except (KeyError, IndexError, TypeError) as e:
        raise RuntimeError(f"Unexpected response shape: {json.dumps(j)[:500]}") from e


# ── Agent invocation ────────────────────────────────────────────────────────


@dataclass
class AgentResult:
    agent: str
    ok: bool
    text: str = ""
    error: str = ""
    seconds: float = 0.0
    meta: Dict[str, Any] = field(default_factory=dict)


def invoke_agent(registry: AgentRegistry, name: str, task: str, *,
                 context: Optional[str] = None, model: str = MODEL,
                 max_tokens: int = 2048, timeout: int = DEFAULT_TIMEOUT) -> AgentResult:
    """Load one agent's system prompt and run it against the model."""
    agent = registry.get_agent(name)
    if not agent:
        available = ", ".join(sorted(registry.agents.keys()))
        return AgentResult(agent=name, ok=False, error=f"Unknown agent '{name}'. Available: {available}")

    system_prompt = agent.get("system_prompt", "")
    user_prompt = task
    if context:
        user_prompt = f"{task}\n\nContext:\n{context}"

    t0 = time.time()
    try:
        text = chat(system_prompt, user_prompt, model=model, max_tokens=max_tokens, timeout=timeout)
        return AgentResult(agent=agent.get("name", name), ok=True, text=text,
                           seconds=round(time.time() - t0, 2),
                           meta={"description": agent.get("description", "")})
    except Exception as e:  # noqa: BLE001 - report, don't crash the batch
        return AgentResult(agent=agent.get("name", name), ok=False,
                           error=repr(e), seconds=round(time.time() - t0, 2))


# ── Orchestration ───────────────────────────────────────────────────────────


def fan_out(registry: AgentRegistry, task: str, agent_names: List[str], *,
            concurrency: int = DEFAULT_CONCURRENCY, context: Optional[str] = None,
            model: str = MODEL, max_tokens: int = 2048) -> List[AgentResult]:
    """Run many agents in parallel on the same task. Returns results in input order."""
    results: Dict[str, AgentResult] = {}

    def _run(name: str) -> AgentResult:
        return invoke_agent(registry, name, task, context=context, model=model, max_tokens=max_tokens)

    with ThreadPoolExecutor(max_workers=max(1, concurrency)) as pool:
        futures = {pool.submit(_run, n): n for n in agent_names}
        for fut in as_completed(futures):
            res = fut.result()
            results[res.agent] = res

    # Preserve the caller's ordering for stable output.
    order = {n.lower(): i for i, n in enumerate(agent_names)}
    ordered = sorted(results.values(), key=lambda r: order.get(r.agent.lower(), 999))
    return ordered


def pipeline(registry: AgentRegistry, agent_names: List[str], task: str, *,
             model: str = MODEL, max_tokens: int = 2048) -> List[AgentResult]:
    """Run agents sequentially; each agent sees the previous agent's output."""
    results: List[AgentResult] = []
    running_task = task
    for name in agent_names:
        res = invoke_agent(registry, name, running_task, model=model, max_tokens=max_tokens)
        results.append(res)
        if res.ok:
            # Feed the next agent the previous output as context.
            running_task = f"{task}\n\nPrevious stage ({res.agent}) produced:\n{res.text}"
        else:
            print(f"[pipeline] stopping at '{name}': {res.error}", file=sys.stderr)
            break
    return results


def merge_results(task: str, results: List[AgentResult], *, model: str = MODEL,
                  max_tokens: int = 2048) -> str:
    """Ask the model to synthesize the parallel outputs into one answer."""
    if not any(r.ok for r in results):
        return "(no successful agent outputs to merge)"
    blocks = []
    for r in results:
        if r.ok:
            blocks.append(f"### {r.agent}\n{r.text}")
    combined = "\n\n---\n\n".join(blocks)
    system = ("You are a senior production coordinator. Synthesize the department "
              "responses below into one clear, consolidated answer. Remove "
              "duplicates, resolve conflicts sensibly, and keep it actionable.")
    user = f"Task: {task}\n\nDepartment responses:\n\n{combined}"
    return chat(system, user, model=model, max_tokens=max_tokens)


# ── CLI ─────────────────────────────────────────────────────────────────────


def _print_results(results: List[AgentResult]) -> None:
    for r in results:
        status = "OK " if r.ok else "ERR"
        print(f"\n{'=' * 72}\n[{status}] {r.agent}  ({r.seconds}s)")
        print("-" * 72)
        print(r.text if r.ok else r.error)


def main() -> None:
    p = argparse.ArgumentParser(description="Parallel multi-agent runner for StoryOS")
    p.add_argument("--model", default=MODEL, help="Served model id")
    p.add_argument("--concurrency", type=int, default=DEFAULT_CONCURRENCY,
                   help="Max agents running at once (default %(default)s)")
    p.add_argument("--max-tokens", type=int, default=2048, help="Max tokens per agent")
    p.add_argument("--timeout", type=int, default=DEFAULT_TIMEOUT, help="Per-request timeout (s)")
    p.add_argument("--context", default=None, help="Extra context appended to the task")
    sub = p.add_subparsers(dest="cmd", required=True)

    # list
    lp = sub.add_parser("list", help="List available agents")
    lp.add_argument("--department", default=None, help="Filter by department")

    # fan
    fp = sub.add_parser("fan", help="Run many agents in parallel on one task")
    fp.add_argument("--task", required=True)
    fp.add_argument("--agents", nargs="+", required=True, help="Agent names (space separated)")
    fp.add_argument("--merge", action="store_true", help="Synthesize outputs into one answer")

    # pipeline
    pp = sub.add_parser("pipeline", help="Run agents sequentially with handoffs")
    pp.add_argument("--task", required=True)
    pp.add_argument("--agents", nargs="+", required=True, help="Ordered agent names")

    args = p.parse_args()
    registry = AgentRegistry()

    if args.cmd == "list":
        agents = registry.get_agents_by_department(args.department) if args.department else registry.list_agents()
        for a in agents:
            print(f"{a.get('name', '?'):<28} {a.get('description', '')[:70]}")
        return

    if args.cmd == "fan":
        t0 = time.time()
        results = fan_out(registry, args.task, args.agents,
                          concurrency=args.concurrency, context=args.context,
                          model=args.model, max_tokens=args.max_tokens)
        _print_results(results)
        print(f"\n{'=' * 72}\nFan-out of {len(args.agents)} agents "
              f"(concurrency={args.concurrency}) took {round(time.time() - t0, 2)}s wall.")
        if args.merge:
            print("\nMerged answer:\n" + "-" * 72)
            print(merge_results(args.task, results, model=args.model, max_tokens=args.max_tokens))
        return

    if args.cmd == "pipeline":
        t0 = time.time()
        results = pipeline(registry, args.agents, args.task, model=args.model, max_tokens=args.max_tokens)
        _print_results(results)
        print(f"\n{'=' * 72}\nPipeline of {len(args.agents)} agents took {round(time.time() - t0, 2)}s wall.")
        return


if __name__ == "__main__":
    main()
