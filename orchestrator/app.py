import os
from dotenv import load_dotenv

load_dotenv()  # Load .env file before any os.getenv() calls

import requests
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
from celery import Celery
from celery.result import AsyncResult

WORDPRESS_URL = os.getenv("WORDPRESS_URL", "http://wordpress:80")
WORDPRESS_USER = os.getenv("WP_USERNAME")
WORDPRESS_APP_PASSWORD = os.getenv("WP_APP_PASSWORD")
COMFYUI_URL = os.getenv("COMFY_URL", "http://127.0.0.1:8188")
REDIS_URL = os.getenv("REDIS_URL", "redis://redis:6379/0")

celery_app = Celery(
    "video_tasks",
    broker=REDIS_URL,
    backend=REDIS_URL,
)

app = FastAPI()

def wp_auth():
    return (WORDPRESS_USER, WORDPRESS_APP_PASSWORD)

def get_post(post_id: int):
    url = f"{WORDPRESS_URL}/wp-json/wp/v2/posts/{post_id}"
    resp = requests.get(url, auth=wp_auth())
    if not resp.ok:
        raise Exception(f"WordPress GET failed: {resp.status_code} {resp.text}")
    return resp.json()

def update_post_meta(post_id: int, meta: dict):
    url = f"{WORDPRESS_URL}/wp-json/wp/v2/posts/{post_id}"
    resp = requests.post(url, json={"meta": meta}, auth=wp_auth())
    if not resp.ok:
        raise Exception(f"WordPress update failed: {resp.status_code} {resp.text}")
    return resp.json()

class GenerateRequest(BaseModel):
    post_id: int

@app.post("/generate")
def generate(req: GenerateRequest):
    try:
        post = get_post(req.post_id)

        task = celery_app.send_task(
            "tasks.generate_video_task",
            args=[req.post_id]
        )

        meta = post.get("meta", {})
        meta["video_status"] = "queued"
        meta["video_job_id"] = task.id

        update_post_meta(req.post_id, meta)

        return {"job_id": task.id, "status": "queued"}

    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@app.get("/status/{job_id}")
def status(job_id: str):
    result = AsyncResult(job_id, app=celery_app)
    info = result.info or {}
    return {
        "job_id": job_id,
        "state": result.state,
        "progress": info.get("progress"),
        "message": info.get("message"),
        "result": info.get("result"),
    }
