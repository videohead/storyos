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


def test_generate_endpoint_e2e():
    # Load environment from .env if present
    load_dotenv(ROOT / ".env")

    WORDPRESS_URL = os.getenv("WORDPRESS_URL")
    WP_USERNAME = os.getenv("WP_USERNAME") or os.getenv("WORDPRESS_USER")
    WP_APP_PASSWORD = os.getenv("WP_APP_PASSWORD") or os.getenv("WORDPRESSAPPPASSWORD")

    if not (WORDPRESS_URL and WP_USERNAME and WP_APP_PASSWORD):
        pytest.skip("E2E WordPress test skipped: missing WORDPRESS_URL/credentials in env")

    # Try a reachability check for WordPress
    import requests

    try:
        r = requests.get(WORDPRESS_URL, timeout=2)
        r.raise_for_status()
    except Exception as e:
        pytest.skip(f"WordPress not reachable at {WORDPRESS_URL}: {e}")

    # This will call real WordPress /wp-json endpoints through the orchestrator FastAPI
    app_mod = load_module(ROOT / "orchestrator" / "app.py", "orchestrator.app")
    from fastapi.testclient import TestClient

    client = TestClient(app_mod.app)

    # The README author noted post id=20 exists
    resp = client.post("/generate", json={"post_id": 20})
    assert resp.status_code == 200
    data = resp.json()
    assert "job_id" in data and data["status"] == "queued"
