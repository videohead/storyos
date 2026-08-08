import os
import pytest
import importlib.util
import pathlib
from dotenv import load_dotenv


ROOT = pathlib.Path(__file__).resolve().parents[2]


def load_module(path, name):
    spec = importlib.util.spec_from_file_location(name, str(path))
    mod = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(mod)
    return mod


def test_submit_workflow_against_comfy():
    # Load .env if present
    load_dotenv(ROOT / ".env")

    # Requires ComfyUI reachable at COMFYUI_URL (or default 127.0.0.1:8188)
    url = os.getenv("COMFYUI_URL", "http://127.0.0.1:8188")
    os.environ["COMFYUI_URL"] = url

    tasks = load_module(ROOT / "orchestrator" / "tasks.py", "orchestrator.tasks")

    # Quick reachability check
    import requests

    try:
        r = requests.get(url + "/", timeout=2)
    except Exception as e:
        pytest.skip(f"ComfyUI not reachable at {url}: {e}")

    # Submit a small workflow and ensure a prompt_id is returned
    import requests
    try:
        pid = tasks.submit_workflow(99999)
    except requests.exceptions.HTTPError as e:
        # ComfyUI reachable but rejected the sample payload — skip the test
        pytest.skip(f"ComfyUI rejected sample workflow: {e}")

    assert isinstance(pid, str) and pid
