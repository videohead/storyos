"""Middleware for StoryOS orchestrator.

Provides:
- Structured JSON logging for all requests/responses
- Request timing metrics
- Error tracking
- Request ID propagation
"""

from __future__ import annotations

import json
import logging
import time
import uuid
from typing import Any, Callable

from fastapi import Request, Response
from starlette.middleware.base import BaseHTTPMiddleware

logger = logging.getLogger(__name__)


class StructuredLogFormatter(logging.Formatter):
    """Formats log records as JSON for machine parsing."""

    def format(self, record: logging.LogRecord) -> str:
        log_data = {
            "timestamp": self.formatTime(record),
            "level": record.levelname,
            "logger": record.name,
            "message": record.getMessage(),
            "module": record.module,
            "function": record.funcName,
            "line": record.lineno,
        }

        # Add exception info if present
        if record.exc_info and record.exc_info[0] is not None:
            log_data["exception"] = {
                "type": record.exc_info[0].__name__,
                "message": str(record.exc_info[1]),
                "traceback": self.formatException(record.exc_info),
            }

        # Add any extra fields
        if hasattr(record, "request_id"):
            log_data["request_id"] = record.request_id
        if hasattr(record, "duration_ms"):
            log_data["duration_ms"] = record.duration_ms

        return json.dumps(log_data, default=str)


def setup_logging(level: str = "INFO"):
    """Configure structured JSON logging for the application."""
    handler = logging.StreamHandler()
    handler.setFormatter(StructuredLogFormatter())

    # Root logger
    root_logger = logging.getLogger()
    root_logger.setLevel(getattr(logging, level.upper(), logging.INFO))
    root_logger.addHandler(handler)

    # StoryOS loggers
    for name in ["orchestrator", "tasks", "workflows", "story_graph"]:
        app_logger = logging.getLogger(name)
        app_logger.addHandler(handler)
        app_logger.propagate = False


class RequestLoggingMiddleware(BaseHTTPMiddleware):
    """Middleware to log all requests/responses in structured JSON."""

    async def dispatch(self, request: Request, call_next: Callable) -> Response:
        request_id = str(uuid.uuid4())[:8]
        start_time = time.time()

        # Create a logger with request_id attached
        log = logger.bind(request_id=request_id) if hasattr(logger, "bind") else logger
        extra_logger = logging.getLogger(__name__)
        extra_logger.request_id = request_id

        # Log request
        extra_logger.info(
            "REQUEST",
            extra={
                "request_id": request_id,
                "method": request.method,
                "path": request.url.path,
                "query_params": str(request.query_params),
                "client_host": request.client.host if request.client else "unknown",
            },
        )

        try:
            response = await call_next(request)
            duration_ms = (time.time() - start_time) * 1000

            # Log response
            extra_logger.info(
                "RESPONSE",
                extra={
                    "request_id": request_id,
                    "status_code": response.status_code,
                    "duration_ms": round(duration_ms, 2),
                },
            )

            # Add request ID to response headers
            response.headers["X-Request-ID"] = request_id
            response.headers["X-Processing-Time"] = f"{duration_ms:.2f}ms"

            return response

        except Exception as e:
            duration_ms = (time.time() - start_time) * 1000

            # Log error
            extra_logger.error(
                "ERROR",
                extra={
                    "request_id": request_id,
                    "duration_ms": round(duration_ms, 2),
                    "exception_type": type(e).__name__,
                    "exception_message": str(e),
                },
                exc_info=True,
            )
            raise


class MetricsMiddleware(BaseHTTPMiddleware):
    """Middleware to track request metrics."""

    def __init__(self, app, metrics: dict[str, Any] | None = None):
        super().__init__(app)
        self.metrics = metrics or {
            "total_requests": 0,
            "total_errors": 0,
            "total_duration_ms": 0,
            "requests_by_endpoint": {},
            "requests_by_status": {},
            "last_request_time": None,
        }

    async def dispatch(self, request: Request, call_next: Callable) -> Response:
        start_time = time.time()

        # Update total requests
        self.metrics["total_requests"] += 1

        try:
            response = await call_next(request)
            duration_ms = (time.time() - start_time) * 1000

            # Update metrics
            self.metrics["total_duration_ms"] += duration_ms
            self.metrics["last_request_time"] = time.time()

            # Track by endpoint
            endpoint = request.url.path
            if endpoint not in self.metrics["requests_by_endpoint"]:
                self.metrics["requests_by_endpoint"][endpoint] = {
                    "count": 0,
                    "total_duration_ms": 0,
                    "errors": 0,
                }
            self.metrics["requests_by_endpoint"][endpoint]["count"] += 1
            self.metrics["requests_by_endpoint"][endpoint]["total_duration_ms"] += duration_ms

            # Track by status code
            status_key = f"HTTP_{response.status_code // 100}xx"
            self.metrics["requests_by_status"][status_key] = (
                self.metrics["requests_by_status"].get(status_key, 0) + 1
            )

            # Track errors
            if response.status_code >= 400:
                self.metrics["total_errors"] += 1
                if endpoint in self.metrics["requests_by_endpoint"]:
                    self.metrics["requests_by_endpoint"][endpoint]["errors"] += 1

            return response

        except Exception as e:
            duration_ms = (time.time() - start_time) * 1000
            self.metrics["total_errors"] += 1
            raise


def get_metrics() -> dict[str, Any]:
    """Get current metrics (called by /metrics endpoint)."""
    from app import metrics_middleware

    if metrics_middleware and metrics_middleware.metrics:
        metrics = dict(metrics_middleware.metrics)

        # Calculate average duration
        if metrics["total_requests"] > 0:
            metrics["avg_duration_ms"] = round(
                metrics["total_duration_ms"] / metrics["total_requests"], 2
            )
            metrics["error_rate"] = round(
                metrics["total_errors"] / metrics["total_requests"], 4
            )
        else:
            metrics["avg_duration_ms"] = 0
            metrics["error_rate"] = 0

        return metrics

    return {"error": "Metrics not available"}
