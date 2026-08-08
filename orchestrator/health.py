"""Health check service for StoryOS orchestrator.

Provides structured health checks for all connected services:
- WordPress REST API
- ComfyUI API
- Redis broker/backend
- Celery worker connectivity
"""

from __future__ import annotations

import logging
import time
from typing import Any, Optional

import requests
from celery import Celery

logger = logging.getLogger(__name__)


class HealthCheckError(Exception):
    """Raised when a health check fails."""


class ServiceHealth:
    """Health status for a single service."""

    def __init__(
        self,
        name: str,
        status: str = "unknown",
        error: Optional[str] = None,
        latency_ms: Optional[float] = None,
    ):
        self.name = name
        self.status = status
        self.error = error
        self.latency_ms = latency_ms

    def to_dict(self) -> dict[str, Any]:
        return {
            "status": self.status,
            "error": self.error,
            "latency_ms": self.latency_ms,
        }


class HealthChecker:
    """Checks health of all connected services."""

    def __init__(
        self,
        wordpress_url: str,
        comfyui_url: str,
        celery_app: Celery,
        timeout: int = 5,
    ):
        self.wordpress_url = wordpress_url.rstrip("/")
        self.comfyui_url = comfyui_url.rstrip("/")
        self.celery_app = celery_app
        self.timeout = timeout

    def check_wordpress(self) -> ServiceHealth:
        """Check WordPress REST API connectivity."""
        name = "wordpress"
        start = time.time()

        try:
            resp = requests.get(
                f"{self.wordpress_url}/wp-json/",
                timeout=self.timeout,
            )
            latency = (time.time() - start) * 1000

            if resp.ok:
                return ServiceHealth(name, "connected", latency_ms=latency)
            else:
                error = f"HTTP {resp.status_code}"
                logger.warning("WordPress health check: %s", error)
                return ServiceHealth(name, f"error_{resp.status_code}", error, latency_ms=latency)

        except requests.exceptions.Timeout:
            error = "Timeout"
            logger.error("WordPress health check: %s", error)
            return ServiceHealth(name, "timeout", error)
        except requests.exceptions.ConnectionError:
            error = "Connection refused"
            logger.error("WordPress health check: %s", error)
            return ServiceHealth(name, "unreachable", error)
        except Exception as e:
            error = str(e)[:200]
            logger.error("WordPress health check error: %s", e)
            return ServiceHealth(name, "error", error)

    def check_comfyui(self) -> ServiceHealth:
        """Check ComfyUI API connectivity."""
        name = "comfyui"
        start = time.time()

        try:
            # Check /history endpoint (returns 404 if no history, but connection works)
            resp = requests.get(
                f"{self.comfyui_url}/history/",
                timeout=self.timeout,
            )
            latency = (time.time() - start) * 1000

            if resp.ok or resp.status_code == 404:
                return ServiceHealth(name, "connected", latency_ms=latency)
            else:
                error = f"HTTP {resp.status_code}"
                logger.warning("ComfyUI health check: %s", error)
                return ServiceHealth(name, f"error_{resp.status_code}", error, latency_ms=latency)

        except requests.exceptions.Timeout:
            error = "Timeout"
            logger.error("ComfyUI health check: %s", error)
            return ServiceHealth(name, "timeout", error)
        except requests.exceptions.ConnectionError:
            error = "Connection refused"
            logger.error("ComfyUI health check: %s", error)
            return ServiceHealth(name, "unreachable", error)
        except Exception as e:
            error = str(e)[:200]
            logger.error("ComfyUI health check error: %s", e)
            return ServiceHealth(name, "error", error)

    def check_redis(self) -> ServiceHealth:
        """Check Redis connectivity via Celery backend."""
        name = "redis"
        start = time.time()

        try:
            backend = self.celery_app.backend
            if hasattr(backend, "client"):
                # RedisBackend has a client attribute
                client = backend.client
                client.ping()
                latency = (time.time() - start) * 1000
                return ServiceHealth(name, "connected", latency_ms=latency)
            else:
                # Fallback: try to get backend info
                return ServiceHealth(name, "connected", latency_ms=0)
        except Exception as e:
            error = str(e)[:200]
            logger.error("Redis health check error: %s", e)
            return ServiceHealth(name, "unreachable", error)

    def check_celery(self) -> ServiceHealth:
        """Check Celery worker connectivity."""
        name = "celery"
        start = time.time()

        try:
            # Try to inspect active tasks (will fail if no workers)
            inspect = self.celery_app.inspect()
            active = inspect.active()
            latency = (time.time() - start) * 1000

            if active and any(active.values()):
                return ServiceHealth(name, "connected", latency_ms=latency)
            else:
                # No active tasks is okay, workers might be idle
                return ServiceHealth(name, "connected", latency_ms=latency)

        except Exception as e:
            error = str(e)[:200]
            logger.warning("Celery health check: %s", error)
            # Celery check is soft — don't mark as unhealthy if inspect fails
            return ServiceHealth(name, "unknown", f"Celery inspect: {error}", latency_ms=0)

    def get_overall_health(self, service_healths: dict[str, ServiceHealth]) -> str:
        """Determine overall health status from individual service health."""
        statuses = [h.status for h in service_healths.values()]

        if all(s == "connected" for s in statuses):
            return "healthy"
        elif any(s in ("unreachable", "timeout") for s in statuses):
            return "unhealthy"
        else:
            return "degraded"

    def run_all_checks(self) -> dict[str, Any]:
        """Run all health checks and return comprehensive results."""
        logger.info("Running health checks...")

        checks = {
            "wordpress": self.check_wordpress(),
            "comfyui": self.check_comfyui(),
            "redis": self.check_redis(),
            "celery": self.check_celery(),
        }

        overall = self.get_overall_health(checks)

        result = {
            "status": overall,
            "services": {name: health.to_dict() for name, health in checks.items()},
            "timestamp": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime()),
        }

        logger.info("Health check complete: %s", overall)
        return result

    def is_healthy(self) -> bool:
        """Check if all critical services are healthy."""
        result = self.run_all_checks()
        return result["status"] == "healthy"
