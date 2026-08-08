"""Probe common OpenAI-compatible endpoints and HTTP methods on a model server.

Usage:
  python tools/probe_endpoints.py --base http://10.0.0.34:11434

This script prints a short diagnostic for each tried URL and method.
"""
import argparse
import requests
import json
from urllib.parse import urljoin


ENDPOINT_PATHS = [
    "/v1/models",
    "/v1/chat/completions",
    "/v1/completions",
    "/v1/response",
    "/v1/responses",
    "/v1/predict",
    "/predict",
    "/v1",
    "/",
]

DEFAULT_HEADERS = {"Content-Type": "application/json"}

CHAT_PAYLOAD = {"model": "qwen", "messages": [{"role": "user", "content": "Hello"}], "max_tokens": 16}
COMPLETE_PAYLOAD = {"model": "qwen", "prompt": "Hello", "max_tokens": 16}
GENERIC_PAYLOAD = {"input": "Hello"}


def try_request(method, url, json_payload=None, headers=None):
    try:
        if method == "GET":
            r = requests.get(url, headers=headers, timeout=10)
        else:
            r = requests.post(url, headers=headers, json=json_payload, timeout=20)
        snippet = r.text[:500].replace("\n", " ")
        return r.status_code, snippet
    except Exception as e:
        return None, repr(e)


def main():
    p = argparse.ArgumentParser()
    p.add_argument("--base", default="http://10.0.0.34:11434", help="Base URL for the model server")
    p.add_argument("--api-key", default=None, help="API key to send as Bearer token")
    args = p.parse_args()

    base = args.base.rstrip("/")
    headers = DEFAULT_HEADERS.copy()
    if args.api_key:
        headers["Authorization"] = f"Bearer {args.api_key}"

    print(f"Probing {base} for common OpenAI-compatible endpoints...\n")

    for path in ENDPOINT_PATHS:
        url = base + path
        # Try GET
        status, body = try_request("GET", url, None, headers)
        print(f"GET {url} -> status={status} body_snippet={body!r}")

        # Try POST with chat payload
        status, body = try_request("POST", url, CHAT_PAYLOAD, headers)
        print(f"POST(chat) {url} -> status={status} body_snippet={body!r}")

        # Try POST with completion payload
        status, body = try_request("POST", url, COMPLETE_PAYLOAD, headers)
        print(f"POST(complete) {url} -> status={status} body_snippet={body!r}")

        # Try POST with generic payload
        status, body = try_request("POST", url, GENERIC_PAYLOAD, headers)
        print(f"POST(generic) {url} -> status={status} body_snippet={body!r}\n")

    print("Probe finished.")


if __name__ == "__main__":
    main()
