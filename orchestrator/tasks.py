import os
import time
import requests
from celery import Celery

# ---------------------------------------------------------
# Environment variables
# ---------------------------------------------------------
WORDPRESS_URL = os.getenv("WORDPRESS_URL", "http://10.0.0.34")
WORDPRESS_USER = os.getenv("WORDPRESS_USER")
WORDPRESS_APP_PASSWORD = os.getenv("WORDPRESS_APP_PASSWORD")
COMFYUI_URL = os.getenv("COMFYUI_URL", "http://comfyui:8188")
REDIS_URL = os.getenv("REDIS_URL", "redis://redis:6379/0")

# ---------------------------------------------------------
# Celery app
# ---------------------------------------------------------
celery_app = Celery("tasks")
celery_app.conf.broker_url = REDIS_URL
celery_app.conf.result_backend = REDIS_URL

# ---------------------------------------------------------
# WordPress helpers
# ---------------------------------------------------------
def wp_auth():
    return (WORDPRESS_USER, WORDPRESS_APP_PASSWORD)

def upload_media_to_wordpress(filepath: str, filename: str):
    url = f"{WORDPRESS_URL}/wp-json/wp/v2/media"
    headers = {"Content-Disposition": f"attachment; filename={filename}"}

    with open(filepath, "rb") as f:
        resp = requests.post(url, headers=headers, data=f, auth=wp_auth())
        resp.raise_for_status()
        return resp.json()

# ---------------------------------------------------------
# ComfyUI helpers
# ---------------------------------------------------------
def submit_workflow(post_id: int):
    workflow = {
        "prompt": {
            "1": {
                "inputs": {
                    "text": f"Generate an image for WordPress post {post_id}"
                },
                "class_type": "CLIPTextEncode"
            },
            "2": {
                "inputs": {
                    "seed": 12345,
                    "steps": 20,
                    "cfg": 8.0,
                    "sampler_name": "euler",
                    "scheduler": "normal",
                    "denoise": 1.0,
                    "model": "sd15",
                    "positive": ["1"],
                    "negative": []
                },
                "class_type": "KSampler"
            }
        }
    }

    resp = requests.post(f"{COMFYUI_URL}/prompt", json=workflow)
    resp.raise_for_status()
    return resp.json()["prompt_id"]

def poll_comfyui(prompt_id: str):
    while True:
        resp = requests.get(f"{COMFYUI_URL}/history/{prompt_id}")
        if resp.status_code == 200:
            data = resp.json()
            if "outputs" in data:
                return data["outputs"]
        time.sleep(2)

# ---------------------------------------------------------
# Celery task
# ---------------------------------------------------------
@celery_app.task(bind=True, name="tasks.generate_video_task")
def generate_video_task(self, post_id: int):
    self.update_state(state="PROGRESS", meta={"message": "Submitting workflow..."})

    prompt_id = submit_workflow(post_id)

    self.update_state(state="PROGRESS", meta={"message": "Waiting for ComfyUI..."})
    outputs = poll_comfyui(prompt_id)

    # Assume first output is an image
    image_info = list(outputs.values())[0][0]
    image_path = image_info["filename"]

    self.update_state(state="PROGRESS", meta={"message": "Uploading to WordPress..."})
    wp_media = upload_media_to_wordpress(image_path, os.path.basename(image_path))

    return {
        "message": "Completed",
        "result": wp_media
    }
