# Write a WordPress post, create a corresponding asset with ComfyUI

WordPress user generates a post with SCF fields for specific data -fps, vertical resolution, horizontal resolution, positive prompt, negative prompt, pose example (image), style example (image).
User clicks "Generate" button (see plugin) which send a POST to Python server
 Python server reads the post and sends the SCF field data to ComfyUI (via Comfy's API). 
 Once the video is generated, the Python server can use a WordPress user account to upload it back to the WordPress server and update the original post. That way, there's no http wait or other PHP session configuration needed.

Putting Python in the middle solves many of the pain points that comes from trying to make WordPress → PHP → MCP → ComfyUI behave synchronously over long‑running GPU jobs.

Production‑grade, async, resilient, and easy to extend.
WordPress (Post + SCF fields)
        ↓ REST API: /generate (fast, returns immediately)
Python Orchestrator (FastAPI)
        ↓ Celery Task Queue (Redis or RabbitMQ)
Celery Worker(s)
        ↓ ComfyUI API (GPU workflow)
ComfyUI (Docker + GPU)
        ↓ Output file
Celery Worker
        ↓ WordPress REST API (media upload + post update)
WordPress (final video embedded)

PHP request timeouts, FastCGI limits, Nginx proxy buffering, and MCP’s synchronous nature all fight a single-app architecture.

SCF fields (ACF or MetaBox or native custom fields):

fps
resolution_x
resolution_y
positive_prompt
negative_prompt
pose_image (media ID)
style_image (media ID)
status (pending / processing / done / error)
video_url (final output)
use style image as the starting frame
use style image as the ending frame

When the user clicks “Generate Video”, you:
set status = pending
send a REST request to your Python server:

Zero PHP timeouts
WordPress only sends a quick REST request.

Fully asynchronous
Python handles long GPU jobs without blocking anything.

Easy to scale
You can run multiple Python workers or multiple ComfyUI GPU nodes.

Easy debugging
Python logs everything:
workflow JSON
ComfyUI responses
WordPress upload responses

Easy to extend
You can add:
image generation
audio generation
text generation
batch jobs
cron-based regeneration

How you’ll use this from Python:

Load this JSON as a template.

Replace placeholders:

__POSITIVE_PROMPT__, __NEGATIVE_PROMPT__

__FPS__, __RESOLUTION_X__, __RESOLUTION_Y__, __NUM_FRAMES__

__POSE_IMAGE_PATH__, __STYLE_IMAGE_PATH__

__OUTPUT_VIDEO_PATH__ (e.g. /outputs/video/post-123.mp4)

POST it to ComfyUI’s API endpoint for workflows (depends on your ComfyUI API setup).

You’ll likely adjust type and inputs to match your actual nodes (e.g. Stable Video Diffusion, ControlNet, etc.), but this gives you a clear structure.


# Starting and Stopping
docker compose up -d
docker compose up
docker compose down

# First Time Build

1. Copy `.env.example` to `.env` (if one exists) and fill in your values:
   ```bash
   cp .env.example .env   # adjust WP_USERNAME, WP_DATABASE_PASSWORD, DB_NAME, etc.
   ```
2. Create the data directories that WordPress and MariaDB will persist to:
   ```bash
   mkdir -p /opt/wp-comfy/wordpress /opt/wp-comfy/db-data
   ```
3. If you have an existing database dump, place it at `backup.sql` (it is mounted into the db container on first start).
4. Bring up all services (ComfyUI is commented out by default — uncomment in `docker-compose.yaml` when you have a GPU node):
   ```bash
   docker compose up -d
   ```
5. Verify everything is running:
   ```bash
   docker compose ps
   ```
6. Visit WordPress at `http://localhost` and configure your plugins / SCF fields.

# Rebuild safely (don't overwrite wordpress and its db)

Your WordPress files live in `/opt/wp-comfy/wordpress` and the database in `/opt/wp-comfy/db-data` — both are mounted as volumes, so they survive container rebuilds.

To rebuild without losing data:

1. Rebuild only the services that changed (e.g. the orchestrator):
   ```bash
   docker compose up -d --build python-orchestrator celery-worker
   ```
2. To rebuild everything (safe — volumes are preserved):
   ```bash
   docker compose down
   docker compose up -d --build
   ```
3. **Never** delete the `wordpress` or `db-data` volume directories, and **never** mount a fresh `backup.sql` over an already-initialized database unless you want to wipe it.

To reset the database (destructive — wipes all WordPress data):
```bash
docker compose down
rm -rf /opt/wp-comfy/db-data/*
docker compose up -d   # backup.sql will re-initialize the DB






