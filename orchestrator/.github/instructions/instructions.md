**Overview**

Architecture (high level):

WordPress (Post + SCF fields)
        ↓ REST API: POST /generate (fast, returns immediately)
Python Orchestrator (FastAPI) — /opt/wp-comfy/orchestrator/app.py
        ↓ Celery Task Queue (Redis)
Celery Worker(s) — /opt/wp-comfy/orchestrator/tasks.py
        ↓ ComfyUI API (GPU workflow)
ComfyUI (Docker + GPU)
        ↓ Output file
Celery Worker uploads media and updates WordPress via REST API
WordPress (final video embedded)

---

**Repository/Service locations**

- Orchestrator app: /opt/wp-comfy/orchestrator/app.py
- Celery tasks: /opt/wp-comfy/orchestrator/tasks.py
- Orchestrator Docker build context: /opt/wp-comfy/orchestrator (used by docker-compose)
- Docker Compose (project root): /opt/wp-comfy/docker-compose.yaml

From docker-compose.yaml the main services are:

- `wordpress`: image `wordpress:7.0-php8.2-apache`, webroot mounted from `/opt/wp-comfy/wordpress`, port 80 host mapped
- `db`: image `mariadb:10.6`, data persisted to `/opt/wp-comfy/db-data`, initial dump `backup.sql`
- `redis`: image `redis:7`, used as Celery broker/backend
- `python-orchestrator`: built from `./orchestrator` Dockerfile, exposes port 8000 (FastAPI)
- `celery-worker`: built from same orchestrator context and runs the Celery worker

---

**Important environment variables (used in orchestrator)**

- `WORDPRESS_URL` (default in code: `http://wordpress:80` / Lando: `http://appserver`)
- `WP_USERNAME` / `WORDPRESS_USER`
- `WP_APP_PASSWORD` / `WORDPRESSAPPPASSWORD`
- `COMFYUI_URL` (default `http://127.0.0.1:8188` or `http://comfyui:8188` when in-compose)
- `REDIS_URL` (default `redis://redis:6379/0`)
- DB-related vars are in compose for the `db` service (MARIADB_*)

Add these to `.env` for local docker-compose runs or to your Lando environment when using Lando.

---

**FastAPI endpoints to test**

- POST /generate  — accepts JSON: `{ "post_id": <int> }`. Behavior:
  - Calls `get_post(post_id)` which GETs WordPress `/wp-json/wp/v2/posts/{post_id}`
  - Sends a Celery task with name `tasks.generate_video_task` via `celery_app.send_task`
  - Updates the post meta (via `update_post_meta`) with `video_status=queued` and `video_job_id`
  - Returns `{ "job_id": <task.id>, "status": "queued" }`

- GET /status/{job_id} — queries Celery `AsyncResult` and returns state, progress, message, result

---

**Celery task & helper functions (test targets)**

Files: `tasks.py` exposes these functions and the Celery task:

- `submit_workflow(post_id)` — POSTs a workflow JSON to `COMFYUI_URL` `/prompt`, expects `{"prompt_id": "..."}`
- `poll_comfyui(prompt_id)` — polls `COMFYUI_URL/history/{prompt_id}` until the response contains `outputs` and returns them
- `upload_media_to_wordpress(filepath, filename)` — POSTs binary media to WordPress `/wp-json/wp/v2/media` and returns the media JSON
- `generate_video_task(self, post_id)` — the Celery task that calls the above helpers and updates state

Test these individually (unit tests) and then in combination (integration-like tests with mocks).

---

**Suggested unit tests and approaches**

1) Unit tests for WordPress helpers
   - `test_get_post_success` / `test_get_post_failure`: mock `requests.get` to return success JSON or a failing status and assert `get_post` behavior.
   - `test_update_post_meta`: mock `requests.post` and assert it's called with the correct URL, auth, and JSON payload.

2) Unit tests for ComfyUI helpers
   - `test_submit_workflow`: mock `requests.post` to return `{"prompt_id": "abc"}` and assert the returned prompt id.
   - `test_poll_comfyui`: mock `requests.get` sequence: first 202/empty, then 200 with `{"outputs": {...}}` and assert returned outputs.

3) Unit tests for upload helper
   - `test_upload_media_to_wordpress`: create a small temporary file, mock `requests.post` to return a media JSON and assert return value.

4) Unit test for `generate_video_task`
   - Patch `submit_workflow`, `poll_comfyui`, and `upload_media_to_wordpress` to return predictable values and call `generate_video_task` directly (use Celery task instance or call the underlying function). Assert final return structure and State updates via `self.update_state` can be captured by passing a dummy object or by running Celery in eager mode.

5) Endpoint tests for FastAPI
   - Use `fastapi.testclient.TestClient` to exercise POST `/generate` and GET `/status/{job_id}`.
   - Patch `get_post` to return a post JSON, patch `celery_app.send_task` to return an object with `id` attribute (or monkeypatch to return a simple object), and patch `update_post_meta` to record its call. Assert response body and that `update_post_meta` was called with `video_status=queued`.

Mocking frameworks and helpers:

- Use `pytest` + `pytest-mock` (mocker) or `unittest.mock.patch` for `requests` functions and Celery send/AsyncResult.
- Use `responses` or `requests-mock` for HTTP request mocking if preferred.
- For Celery, set `celery_app.conf.task_always_eager = True` in tests, or monkeypatch `celery_app.send_task` to return a fake task object: `type('T', (), {'id': 'fake-id'})()`.

---

**Sample payloads and responses**

- POST /generate request body:

  { "post_id": 123 }

- Sample ComfyUI prompt POST response (submit_workflow expects):

  { "prompt_id": "prompt-abc123" }

- Sample ComfyUI history response (poll_comfyui expects `outputs`):

  {
    "outputs": {
      "1": [
        { "filename": "/outputs/img-1.png", "other": "..." }
      ]
    }
  }

- Sample WordPress media upload response:

  { "id": 456, "source_url": "http://wordpress/wp-content/uploads/img-1.png" }

---

**Where to place tests**

- Suggested test package: `/opt/wp-comfy/orchestrator/tests/`
- Example test modules: `test_app.py`, `test_tasks.py`, `test_helpers.py`

---

**How to run tests locally**

1. Install test deps (from orchestrator/requirements.txt) or a test-only `requirements-dev.txt` including `pytest`, `pytest-mock`, `requests-mock`, `responses`.

2. From project root (or orchestrator folder):

```bash
python -m venv .venv
source .venv/bin/activate
pip install -r orchestrator/requirements.txt
pip install pytest pytest-mock responses
pytest orchestrator/tests -q
```

3. If tests need Celery in eager mode, set in your test conftest or test setup:

```python
from orchestrator import tasks
tasks.celery_app.conf.task_always_eager = True
```

Or monkeypatch `celery_app.send_task` to return a fake task with `id`.

---

**Quick integration guidance**

- To run the full stack locally with docker-compose (as described in README):

```bash
cp .env.example .env   # fill values
mkdir -p /opt/wp-comfy/wordpress /opt/wp-comfy/db-data
docker compose up -d --build
```

- Visit WordPress at `http://localhost` and the orchestrator at `http://localhost:8000` (or as mapped in your environment).

When writing tests, prefer unit tests with mocked HTTP calls and only create a small number of integration tests that spin up containers (use CI or a developer machine for those).

---

The overrides.volumes syntax doesn't work for custom services in Lando — it only works for recipe defaults. For a custom service, volumes must be placed directly under the service definition. Let me fix this properly:
