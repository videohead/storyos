import os
from dotenv import load_dotenv

load_dotenv()

broker_url = os.getenv("REDIS_URL", "redis://redis:6379/0")
result_backend = os.getenv("REDIS_URL", "redis://redis:6379/0")
task_track_started = True
worker_max_tasks_per_child = 100
