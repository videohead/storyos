"""API client and connection/health utilities for the multi-agent demo.

This module centralizes the HTTP connection logic so the configuration
file can remain focused on agent definitions.
"""
import os
import requests
from dotenv import load_dotenv

load_dotenv()

API_BASE = os.getenv("OPENAI_API_BASE") or os.getenv("API_BASE")
API_KEY = os.getenv("OPENAI_API_KEY") or os.getenv("API_KEY") or "local-dev-key"

HEADERS = {"Authorization": f"Bearer {API_KEY}", "Content-Type": "application/json"}


def _build_url(base: str, path: str) -> str:
    if not base:
        return path
    b = base.rstrip("/")
    if b.lower().endswith("/v1") and path.startswith("/v1"):
        return b + path[len("/v1"):]
    return b + path


def health_check():
    # Try both /models and /v1/models so API_BASE can be set with or without /v1
    candidates = ["/models", "/v1/models"]
    last_err = None
    for p in candidates:
        url = _build_url(API_BASE, p)
        try:
            r = requests.get(url, headers=HEADERS, timeout=5)
            print(f"health: tried {url} -> status={r.status_code}")
            if r.status_code == 200:
                return True
            last_err = requests.exceptions.HTTPError(f"{r.status_code} for {url}")
        except Exception as e:
            print(f"health check attempt failed for {url}:", e)
            last_err = e
            continue

    print("health check failed; last error:", last_err)
    return False


def simple_chat(prompt, model="qwen"):
    # Prefer vllm-style /v1/responses endpoint which expects {model, input}
    endpoints = [
        ("/v1/responses", {"model": model, "input": prompt, "tool_choice": "none"}),
        ("/v1/response", {"model": model, "input": prompt}),
        ("/v1/chat/completions", {"model": model, "messages": [{"role": "user", "content": prompt}], "max_tokens": 512}),
        ("/v1/completions", {"model": model, "prompt": prompt, "max_tokens": 512}),
    ]

    last_err = None
    attempts = []
    def build_url(base: str, path: str) -> str:
        if not base:
            return path
        b = base.rstrip("/")
        if b.lower().endswith("/v1") and path.startswith("/v1"):
            return b + path[len("/v1"):]
        return b + path

    for path, payload in endpoints:
        url = build_url(API_BASE, path)
        entry = {"url": url, "status": None, "body": None, "error": None}
        try:
            r = requests.post(url, headers=HEADERS, json=payload, timeout=60)
            entry["status"] = r.status_code
            text = r.text
            entry["body"] = text[:400]
        except Exception as e:
            entry["error"] = repr(e)
            attempts.append(entry)
            last_err = e
            continue

        attempts.append(entry)

        if r.status_code == 404:
            last_err = requests.exceptions.HTTPError(f"404 for {url}")
            continue

        if r.status_code == 405:
            last_err = requests.exceptions.HTTPError(f"405 Method Not Allowed for {url}")
            continue

        try:
            r.raise_for_status()
        except Exception as e:
            last_err = e
            continue

        j = None
        try:
            j = r.json()
        except Exception:
            return entry["body"]

        if isinstance(j, dict):
            if "output" in j and isinstance(j["output"], list) and j["output"]:
                first_out = j["output"][0]
                content = first_out.get("content")
                if isinstance(content, list) and content:
                    for c in content:
                        if isinstance(c, dict) and c.get("text"):
                            return c.get("text")
                        if isinstance(c, str):
                            return c
                return str(first_out)

            choices = j.get("choices")
            if choices and isinstance(choices, list):
                first = choices[0]
                if isinstance(first, dict) and first.get("message") and first["message"].get("content"):
                    return first["message"]["content"]
                if isinstance(first, dict) and first.get("text"):
                    return first["text"]

            if "result" in j:
                return str(j["result"])

        return str(j)

    diag_lines = [f"Tried {len(attempts)} endpoints:"]
    for a in attempts:
        s = f"- {a['url']} -> status={a['status']}"
        if a.get("error"):
            s += f" error={a['error']}"
        if a.get("body"):
            s += f" body_snippet={a['body']!r}"
        diag_lines.append(s)
    if last_err:
        diag_lines.append(f"Last error: {repr(last_err)}")
    return "\n".join(diag_lines)
