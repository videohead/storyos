"""Queue manager for StoryOS orchestrator.

Provides:
- Task cancellation
- Task prioritization
- Active/pending task listing
- Rate limiting per WordPress post
- Task registry with metadata
"""

from __future__ import annotations

import logging
import time
from typing import Any, Optional

from celery import Celery
from celery.result import AsyncResult

logger = logging.getLogger(__name__)


class TaskRegistry:
    """Registry to track task metadata beyond Celery's built-in tracking."""

    def __init__(self):
        self._tasks: dict[str, dict[str, Any]] = {}

    def register(
        self,
        job_id: str,
        post_id: int,
        workflow: str,
        entity_type: str = "post",
        custom_params: Optional[dict[str, Any]] = None,
    ):
        """Register a task with metadata."""
        self._tasks[job_id] = {
            "job_id": job_id,
            "post_id": post_id,
            "entity_type": entity_type,
            "workflow": workflow,
            "custom_params": custom_params or {},
            "status": "queued",
            "created_at": time.time(),
            "started_at": None,
            "completed_at": None,
            "cancelled": False,
            "error": None,
        }
        logger.info("Registered task %s for post %d (workflow: %s)", job_id, post_id, workflow)

    def update_status(self, job_id: str, status: str, error: Optional[str] = None):
        """Update task status."""
        if job_id in self._tasks:
            self._tasks[job_id]["status"] = status
            if error:
                self._tasks[job_id]["error"] = error
            if status == "started":
                self._tasks[job_id]["started_at"] = time.time()
            elif status in ("completed", "failed", "cancelled"):
                self._tasks[job_id]["completed_at"] = time.time()
            logger.debug("Updated task %s status to %s", job_id, status)

    def cancel(self, job_id: str) -> bool:
        """Mark a task for cancellation."""
        if job_id in self._tasks:
            self._tasks[job_id]["cancelled"] = True
            self._tasks[job_id]["status"] = "cancelled"
            logger.info("Task %s marked for cancellation", job_id)
            return True
        logger.warning("Task %s not found for cancellation", job_id)
        return False

    def get(self, job_id: str) -> Optional[dict[str, Any]]:
        """Get task metadata."""
        return self._tasks.get(job_id)

    def list_tasks(
        self,
        status_filter: Optional[str] = None,
        post_id: Optional[int] = None,
        workflow: Optional[str] = None,
        limit: int = 50,
        offset: int = 0,
    ) -> list[dict[str, Any]]:
        """List tasks with optional filters."""
        tasks = list(self._tasks.values())

        # Apply filters
        if status_filter:
            tasks = [t for t in tasks if t["status"] == status_filter]
        if post_id is not None:
            tasks = [t for t in tasks if t["post_id"] == post_id]
        if workflow:
            tasks = [t for t in tasks if t["workflow"] == workflow]

        # Sort by creation time (newest first)
        tasks.sort(key=lambda t: t["created_at"], reverse=True)

        # Apply pagination
        return tasks[offset : offset + limit]

    def total(self, status_filter: Optional[str] = None) -> int:
        """Count total tasks, optionally filtered by status."""
        if status_filter:
            return sum(1 for t in self._tasks.values() if t["status"] == status_filter)
        return len(self._tasks)


class RateLimiter:
    """Rate limiter to prevent duplicate generations for the same post."""

    def __init__(self, max_concurrent: int = 1, window_seconds: int = 60):
        self.max_concurrent = max_concurrent
        self.window_seconds = window_seconds
        self._active: dict[int, list[float]] = {}

    def is_allowed(self, post_id: int) -> tuple[bool, str]:
        """Check if a new task is allowed for the given post.

        Returns:
            Tuple of (allowed, reason)
        """
        now = time.time()

        # Clean old entries outside the window
        if post_id in self._active:
            self._active[post_id] = [
                t for t in self._active[post_id]
                if now - t < self.window_seconds
            ]

        # Check concurrent limit
        active_count = len(self._active.get(post_id, []))
        if active_count >= self.max_concurrent:
            return False, f"Max {self.max_concurrent} concurrent tasks per post exceeded"

        # Check for recent duplicate
        if post_id in self._active and self._active[post_id]:
            last_time = max(self._active[post_id])
            if now - last_time < self.window_seconds:
                return False, f"Recent task for post {post_id} within {self.window_seconds}s window"

        # Allow and record
        if post_id not in self._active:
            self._active[post_id] = []
        self._active[post_id].append(now)

        return True, "Allowed"

    def release(self, post_id: int):
        """Release a post from the rate limiter (task completed)."""
        if post_id in self._active:
            # Remove the oldest entry
            self._active[post_id].pop(0)


class QueueManager:
    """Manages the task queue with registry, rate limiting, and cancellation."""

    def __init__(self, celery_app: Celery, max_concurrent_per_post: int = 1):
        self.celery_app = celery_app
        self.registry = TaskRegistry()
        self.rate_limiter = RateLimiter(max_concurrent=max_concurrent_per_post)

    def submit_task(
        self,
        task_name: str,
        post_id: int,
        workflow: str = "base",
        entity_type: str = "post",
        custom_params: Optional[dict[str, Any]] = None,
    ) -> Optional[str]:
        """Submit a task to the queue with rate limiting.

        Returns:
            Job ID if submitted, None if rate limited
        """
        # Check rate limit
        allowed, reason = self.rate_limiter.is_allowed(post_id)
        if not allowed:
            logger.warning("Task submission rejected for post %d: %s", post_id, reason)
            return None

        # Send task to Celery
        task = self.celery_app.send_task(
            task_name,
            args=[post_id, workflow, custom_params],
        )

        # Register with metadata
        self.registry.register(
            job_id=task.id,
            post_id=post_id,
            workflow=workflow,
            entity_type=entity_type,
            custom_params=custom_params,
        )

        logger.info("Submitted task %s for post %d", task.id, post_id)
        return task.id

    def cancel_task(self, job_id: str) -> tuple[bool, str]:
        """Cancel a task by job ID.

        Returns:
            Tuple of (success, message)
        """
        # Check registry
        task_meta = self.registry.get(job_id)
        if not task_meta:
            return False, f"Task {job_id} not found in registry"

        if task_meta["status"] in ("completed", "failed", "cancelled"):
            return False, f"Task {job_id} is already {task_meta['status']}"

        # Mark for cancellation in registry
        self.registry.cancel(job_id)

        # Try to revoke in Celery (only works for pending tasks)
        try:
            self.celery_app.control.revoke(job_id, terminate=False)
            logger.info("Task %s revoked from Celery queue", job_id)
            return True, "Task cancelled and revoked from queue"
        except Exception as e:
            logger.warning("Failed to revoke task %s in Celery: %s", job_id, e)
            return True, "Task marked as cancelled (revoke failed - may already be running)"

    def get_task_status(self, job_id: str) -> Optional[dict[str, Any]]:
        """Get comprehensive task status from both Celery and registry."""
        celery_result = AsyncResult(job_id, app=self.celery_app)
        registry_meta = self.registry.get(job_id)

        if not registry_meta:
            return None

        info = celery_result.info or {}

        return {
            "job_id": job_id,
            "post_id": registry_meta["post_id"],
            "entity_type": registry_meta["entity_type"],
            "workflow": registry_meta["workflow"],
            "celery_state": celery_result.state,
            "registry_status": registry_meta["status"],
            "progress": info.get("progress"),
            "message": info.get("message"),
            "result": info.get("result"),
            "error": registry_meta.get("error") or info.get("error"),
            "cancelled": registry_meta["cancelled"],
            "created_at": registry_meta["created_at"],
            "started_at": registry_meta["started_at"],
            "completed_at": registry_meta["completed_at"],
        }

    def list_tasks(
        self,
        status_filter: Optional[str] = None,
        post_id: Optional[int] = None,
        workflow: Optional[str] = None,
        limit: int = 50,
        offset: int = 0,
    ) -> dict[str, Any]:
        """List tasks with filters and pagination."""
        tasks = self.registry.list_tasks(
            status_filter=status_filter,
            post_id=post_id,
            workflow=workflow,
            limit=limit,
            offset=offset,
        )

        return {
            "tasks": tasks,
            "total": self.registry.total(status_filter=status_filter),
            "limit": limit,
            "offset": offset,
            "filters": {
                "status": status_filter,
                "post_id": post_id,
                "workflow": workflow,
            },
        }

    def get_active_tasks(self, post_id: Optional[int] = None) -> list[dict[str, Any]]:
        """Get all currently running/queued tasks."""
        status = "started" if post_id else None
        tasks = self.registry.list_tasks(status_filter=status, limit=1000)

        # Filter to only truly active tasks
        active = []
        for task in tasks:
            if task["status"] in ("queued", "started"):
                active.append(task)

        return active

    def get_pending_tasks(self) -> list[dict[str, Any]]:
        """Get all queued (pending) tasks."""
        return self.registry.list_tasks(status_filter="queued", limit=1000)

    def promote_task(self, job_id: str) -> tuple[bool, str]:
        """Promote a task priority (placeholder for future implementation).

        NOTE: Celery doesn't support dynamic priority changes.
        This would require a priority queue setup with multiple queues.
        """
        return False, "Task promotion not yet implemented. Requires multi-queue setup."

    def release_post(self, post_id: int):
        """Release a post from rate limiting after task completion."""
        self.rate_limiter.release(post_id)
        logger.debug("Released post %d from rate limiter", post_id)
