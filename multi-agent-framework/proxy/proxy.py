"""Tiny proxy that normalizes OpenAI-compatible requests to your model server.

Usage: set `OPENAI_API_BASE` to the target model server (e.g. http://10.0.0.34:11434/v1)
Then run this proxy and point clients (Copilot, tools) at `http://<host>:11435/v1`.
"""
import os
import json
import time
from flask import Flask, request, Response
import requests

app = Flask(__name__)

TARGET_BASE = os.getenv("OPENAI_API_BASE") or os.getenv("API_BASE") or "http://10.0.0.34:11434/v1"
API_KEY = os.getenv("OPENAI_API_KEY") or os.getenv("API_KEY") or "local-dev-key"
HEADERS = {"Authorization": f"Bearer {API_KEY}", "Content-Type": "application/json"}

# Candidate target endpoints (will be tried in order)
TARGET_ENDPOINTS = [
    "/v1/responses",
    "/responses",
    "/v1/response",
    "/response",
    "/v1/chat/completions",
    "/v1/completions",
]


def build_target_url(base: str, ep: str) -> str:
    """Join base and endpoint ensuring we don't duplicate '/v1'.

    Examples:
      build_target_url('http://host:11434', '/v1/responses') -> 'http://host:11434/v1/responses'
      build_target_url('http://host:11434/v1', '/v1/responses') -> 'http://host:11434/v1/responses'
    """
    if not base:
        return ep
    b = base.rstrip("/")
    # If base already ends with /v1 and endpoint begins with /v1, avoid duplication
    if b.lower().endswith("/v1") and ep.startswith("/v1"):
        return b + ep[len("/v1"):]
    return b + ep


def try_forward(payload_json):
    attempts = []
    for ep in TARGET_ENDPOINTS:
        url = build_target_url(TARGET_BASE, ep)
        try:
            r = requests.post(url, headers=HEADERS, json=payload_json, timeout=60)
            attempts.append({"url": url, "status": r.status_code, "body": r.text[:400]})
            if r.status_code == 200:
                return r.status_code, r.headers.get("content-type", "application/json"), r.content
            # continue trying for 404/405/other
        except Exception as e:
            attempts.append({"url": url, "error": repr(e)})
            continue
    return None, None, {"attempts": attempts}


@app.route("/v1/<path:subpath>", methods=["POST", "GET", "OPTIONS"])
@app.route("/<path:subpath>", methods=["POST", "GET", "OPTIONS"])
def forward(subpath):
    # Accept incoming JSON or form data
    try:
        payload = request.get_json(force=True, silent=True)
    except Exception:
        payload = None

    # If no JSON, try to build one from form or raw data
    if not payload:
        if request.data:
            try:
                payload = json.loads(request.data.decode("utf-8"))
            except Exception:
                payload = {"input": request.data.decode("utf-8")}
        else:
            payload = {"input": request.args.get("input")}

    # Normalize incoming request into vllm /v1/responses shape.
    # vllm expects at least {"model": "MODEL_NAME", "input": "..."}
    send_payload = {}
    # Model selection: prefer explicit model, else use override env
    model = payload.get("model") or os.getenv("PROXY_TARGET_MODEL")
    if model:
        send_payload["model"] = model

    # If OpenAI chat-style messages provided, use last user message as input
    if isinstance(payload.get("messages"), list) and payload.get("messages"):
        # prefer the last message
        last = payload["messages"][-1]
        send_payload["input"] = last.get("content") if isinstance(last, dict) else str(last)
    elif "prompt" in payload:
        send_payload["input"] = payload.get("prompt")
    elif "input" in payload:
        send_payload["input"] = payload.get("input")
    else:
        # fallback: stringify payload
        send_payload["input"] = json.dumps(payload)

    # vllm-specific: avoid tool calls unless specified
    if "tool_choice" not in send_payload:
        send_payload["tool_choice"] = payload.get("tool_choice", "none")

    # Determine if client requested streaming (SSE)
    stream_req = False
    try:
        incoming_json = request.get_json(silent=True)
        if isinstance(incoming_json, dict) and incoming_json.get("stream"):
            stream_req = True
    except Exception:
        incoming_json = None
    if request.args.get("stream") == "true":
        stream_req = True

    status, ctype, content = try_forward(send_payload)
    if status == 200:
        # Try to parse the target server JSON
        try:
            j = json.loads(content)
        except Exception:
            # If not JSON, forward raw content; if streaming requested, emit SSE
            if stream_req:
                def gen_raw():
                    yield f"data: {json.dumps({'text': content.decode() if isinstance(content, (bytes, bytearray)) else str(content)})}\n\n"
                    yield "data: [DONE]\n\n"
                return Response(gen_raw(), status=200, content_type="text/event-stream")
            return Response(response=content, status=200, content_type=ctype)

        # extract assistant text from vllm /responses shape
        assistant_text = None
        if isinstance(j, dict):
            out = j.get("output")
            if isinstance(out, list) and out:
                first = out[0]
                content_list = first.get("content")
                if isinstance(content_list, list) and content_list:
                    first_item = content_list[0]
                    if isinstance(first_item, dict) and first_item.get("text"):
                        assistant_text = first_item.get("text")
                    elif isinstance(first_item, str):
                        assistant_text = first_item

        if assistant_text is None:
            assistant_text = json.dumps(j)

        # If streaming requested, emit SSE with OpenAI-style delta then [DONE]
        if stream_req:
            def event_stream():
                delta = {"choices": [{"delta": {"content": assistant_text}, "index": 0}]}
                yield f"data: {json.dumps(delta)}\n\n"
                yield "data: [DONE]\n\n"

            return Response(event_stream(), status=200, content_type="text/event-stream")

        # Build OpenAI Chat Completions compatible response
        chat_resp = {
            "id": j.get("id", "resp_proxy"),
            "object": "chat.completion",
            "created": int(time.time()),
            "model": send_payload.get("model") or os.getenv("PROXY_TARGET_MODEL"),
            "choices": [
                {
                    "index": 0,
                    "message": {"role": "assistant", "content": assistant_text},
                    "finish_reason": "stop",
                }
            ],
        }

        return Response(response=json.dumps(chat_resp), status=200, content_type="application/json")

    # Return diagnostic JSON
    return Response(response=json.dumps(content), status=502, content_type="application/json")


if __name__ == "__main__":
    port = int(os.getenv("PROXY_PORT", "8080"))
    app.run(host="0.0.0.0", port=port)
