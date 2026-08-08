"""Local starter harness (renamed to avoid shadowing the 'agent-framework' package).

Use this script to validate model connectivity and interact with the local
model/proxy. It replaces the previous `agent_framework.py` local module so
the installed `agent-framework` package can be imported without name collisions.
"""
import argparse
import os
from dotenv import load_dotenv

load_dotenv()

from api_client import health_check, simple_chat


def cmd_health(args):
    ok = health_check()
    print("Model reachable:", ok)


def cmd_chat(args):
    if args.prompt:
        prompt = args.prompt
    else:
        try:
            prompt = input("Prompt: ")
        except EOFError:
            return

    resp = simple_chat(prompt, model=args.model)
    print("\nResponse:\n", resp)


def cmd_interactive(args):
    print("Interactive chat. Type 'quit' or Ctrl-D to exit.")
    while True:
        try:
            prompt = input("You: ")
        except EOFError:
            print()
            break
        if not prompt or prompt.strip().lower() in ("quit", "exit"):
            break
        print("Assistant:")
        print(simple_chat(prompt, model=args.model))


def main():
    p = argparse.ArgumentParser(description="Agent framework starter/demo")
    sp = p.add_subparsers(dest="cmd")

    h = sp.add_parser("health", help="Run health check against model server")
    h.set_defaults(func=cmd_health)

    c = sp.add_parser("chat", help="Send a single prompt to the model")
    c.add_argument("-p", "--prompt", help="Prompt text (if omitted read from stdin)")
    c.add_argument("-m", "--model", default=os.getenv("PROXY_TARGET_MODEL") or os.getenv("MODEL") or "qwen", help="Model name to request")
    c.set_defaults(func=cmd_chat)

    i = sp.add_parser("interactive", help="Interactive chat loop")
    i.add_argument("-m", "--model", default=os.getenv("PROXY_TARGET_MODEL") or os.getenv("MODEL") or "qwen", help="Model name to request")
    i.set_defaults(func=cmd_interactive)

    args = p.parse_args()
    if not args.cmd:
        p.print_help()
        return
    args.func(args)


if __name__ == "__main__":
    main()
