import os
import time
from typing import Optional

from dotenv import load_dotenv

load_dotenv()  # Load .env file before any os.getenv() calls

import requests
from fastapi import FastAPI, HTTPException, Request
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse, Response
from pydantic import BaseModel
from celery import Celery
from celery.result import AsyncResult

from models import (
    GenerateRequest,
    GenerateResponse,
    TaskStatusResponse,
    TaskListResponse,
    TaskListItem,
    TemplateListResponse,
    WorkflowBuildRequest,
    WorkflowBuildResponse,
    HealthResponse,
    HealthStatus,
    SemanticSearchRequest,
    SemanticSearchResponse,
    SearchHit,
    IndexEntitiesResponse,
    ContinuityValidationRequest,
    ContinuityIssue,
    ContinuityValidationResponse,
    CharacterNetworkRequest,
    CharacterNetworkResponse,
    GraphAnalyticsResponse,
)
from story_graph import StoryGraphContextBuilder, WordPressAPIError
from story_intelligence import (
    StoryGraphIntelligence,
    EmbeddingBackend,
    DummyEmbeddingBackend,
    OllamaEmbeddingBackend,
    SentenceTransformerBackend,
)
from workflows.loader import get_loader, build_workflow
from adapters import (
    ExecutiveOrchestrator,
    StoryAdvisor,
    PromptAdvisor,
    ProductionAdvisor,
    TechnicalAdvisor,
    EditorialAdvisor,
)
from health import HealthChecker
from middleware import RequestLoggingMiddleware, MetricsMiddleware, setup_logging, get_metrics
from queue_manager import QueueManager
from asset_lineage import AssetLineage, WordPressAssetError
from mcp_agents import create_mcp_agent_router

# ── Logging setup ──────────────────────────────────────────────────────────

setup_logging(os.getenv("LOG_LEVEL", "info").upper())

# ── Environment variables ───────────────────────────────────────────────────

WORDPRESS_URL = os.getenv("WORDPRESS_URL", "http://wordpress:80")
WORDPRESS_USER = os.getenv("WP_USERNAME")
WORDPRESS_APP_PASSWORD = os.getenv("WP_APP_PASSWORD")
COMFYUI_URL = os.getenv("COMFY_URL", "http://127.0.0.1:8188")
REDIS_URL = os.getenv("REDIS_URL", "redis://redis:6379/0")

# ── Celery app ──────────────────────────────────────────────────────────────

celery_app = Celery(
    "video_tasks",
    broker=REDIS_URL,
    backend=REDIS_URL,
)

# ── FastAPI app ─────────────────────────────────────────────────────────────

app = FastAPI(
    title="StoryOS Orchestrator",
    description="WordPress ↔ ComfyUI orchestration platform",
    version="0.1.0",
)

# ── Middleware ──────────────────────────────────────────────────────────────

# Add CORS middleware
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],  # Configure for production
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Add request logging middleware
app.add_middleware(RequestLoggingMiddleware)

# Add metrics middleware
app.add_middleware(MetricsMiddleware)

# ── Initialize services ────────────────────────────────────────────────────

health_checker = HealthChecker(
    wordpress_url=WORDPRESS_URL,
    comfyui_url=COMFYUI_URL,
    celery_app=celery_app,
)

queue_manager = QueueManager(celery_app=celery_app)

asset_lineage = AssetLineage(
    wordpress_url=WORDPRESS_URL,
    wordpress_user=WORDPRESS_USER,
    wordpress_password=WORDPRESS_APP_PASSWORD,
)

# ── Initialize agent advisors ──────────────────────────────────────────────

orchestrator = ExecutiveOrchestrator()

story_advisor = StoryAdvisor()
prompt_advisor = PromptAdvisor()
production_advisor = ProductionAdvisor()
technical_advisor = TechnicalAdvisor()
editorial_advisor = EditorialAdvisor()

# ── Initialize intelligence engine ──────────────────────────────────────────

# Embedding backend selection via env var
_EMBED_BACKEND = os.getenv("EMBEDDING_BACKEND", "dummy").lower()
_embedding_backend: Optional[EmbeddingBackend] = None

if _EMBED_BACKEND == "ollama":
    _ollama_url = os.getenv("OLLAMA_URL", "http://localhost:11434")
    _ollama_model = os.getenv("OLLAMA_EMBED_MODEL", "nomic-embed-text")
    _embedding_backend = OllamaEmbeddingBackend(url=_ollama_url, model=_ollama_model)
    logger.info("Using Ollama embedding backend (model=%s)", _ollama_model)
elif _EMBED_BACKEND == "sentence-transformers":
    _st_model = os.getenv("SENTENCE_TRANSFORMERS_MODEL", "all-MiniLM-L6-v2")
    _embedding_backend = SentenceTransformerBackend(model_name=_st_model)
    logger.info("Using sentence-transformers backend (model=%s)", _st_model)
else:
    _embedding_backend = DummyEmbeddingBackend()
    logger.info("Using dummy embedding backend (development mode)")

intelligence = StoryGraphIntelligence(
    wordpress_url=WORDPRESS_URL,
    username=WORDPRESS_USER,
    app_password=WORDPRESS_APP_PASSWORD,
    embedding_backend=_embedding_backend,
)

# ── WordPress helpers ───────────────────────────────────────────────────────


def wp_auth():
    return (WORDPRESS_USER, WORDPRESS_APP_PASSWORD)


def get_post(post_id: int, post_type: str = "posts"):
    """Get a WordPress post by ID."""
    url = f"{WORDPRESS_URL}/wp-json/wp/v2/{post_type}/{post_id}"
    resp = requests.get(url, auth=wp_auth(), timeout=30)
    if not resp.ok:
        raise WordPressAPIError(
            f"WordPress GET {post_type}/{post_id} failed: {resp.status_code} {resp.text[:500]}"
        )
    return resp.json()


def update_post_meta(post_id: int, meta: dict, post_type: str = "posts"):
    """Update WordPress post meta."""
    url = f"{WORDPRESS_URL}/wp-json/wp/v2/{post_type}/{post_id}"
    resp = requests.post(url, json={"meta": meta}, auth=wp_auth(), timeout=30)
    if not resp.ok:
        raise WordPressAPIError(
            f"WordPress update failed: {resp.status_code} {resp.text[:500]}"
        )
    return resp.json()


# ── Health check ────────────────────────────────────────────────────────────


@app.get("/health", response_model=HealthResponse)
def health_check():
    """Check health of all connected services."""
    health = HealthStatus.OK

    # Check WordPress
    wp_status = "unknown"
    wp_error = None
    try:
        resp = requests.get(f"{WORDPRESS_URL}/wp-json/", timeout=5)
        if resp.ok:
            wp_status = "connected"
        else:
            wp_status = f"error_{resp.status_code}"
            wp_error = resp.text[:200]
            health = HealthStatus.DEGRADED
    except Exception as e:
        wp_status = "unreachable"
        wp_error = str(e)[:200]
        health = HealthStatus.DEGRADED

    # Check ComfyUI
    comfy_status = "unknown"
    comfy_error = None
    try:
        resp = requests.get(f"{COMFYUI_URL}/history/", timeout=5)
        if resp.ok or resp.status_code == 404:
            comfy_status = "connected"
        else:
            comfy_status = f"error_{resp.status_code}"
            comfy_error = resp.text[:200]
            health = HealthStatus.DEGRADED
    except Exception as e:
        comfy_status = "unreachable"
        comfy_error = str(e)[:200]
        health = HealthStatus.DEGRADED

    # Check Redis (via Celery)
    redis_status = "unknown"
    redis_error = None
    try:
        # Try to ping Redis through Celery
        result = celery_app.backend.get_backend()
        if hasattr(result, 'driver'):
            redis_status = f"connected_{result.driver}"
        else:
            redis_status = "connected"
    except Exception as e:
        redis_status = "unreachable"
        redis_error = str(e)[:200]
        health = HealthStatus.DEGRADED

    return HealthResponse(
        status=health,
        services={
            "wordpress": {
                "status": wp_status,
                "error": wp_error,
            },
            "comfyui": {
                "status": comfy_status,
                "error": comfy_error,
            },
            "redis": {
                "status": redis_status,
                "error": redis_error,
            },
        },
    )


# ── Workflow endpoints ──────────────────────────────────────────────────────


@app.get("/workflows", response_model=TemplateListResponse)
def list_workflows():
    """List all available workflow templates."""
    loader = get_loader()
    templates = loader.list_templates()
    return TemplateListResponse(templates=templates)


@app.post("/workflows/build", response_model=WorkflowBuildResponse)
def build_workflow_endpoint(req: WorkflowBuildRequest):
    """Dry-run: build a workflow from a template without executing it.

    Returns the rendered workflow JSON for inspection.
    """
    try:
        # Build context from WordPress data
        context_builder = StoryGraphContextBuilder(
            wordpress_url=WORDPRESS_URL,
            username=WORDPRESS_USER,
            app_password=WORDPRESS_APP_PASSWORD,
        )

        if req.entity_type == "character":
            context = context_builder.build_for_character(req.entity_id)
        elif req.entity_type == "scene":
            context = context_builder.build_for_scene(req.entity_id)
        elif req.entity_type == "location":
            context = context_builder.build_for_location(req.entity_id)
        else:
            context = context_builder.build_for_post(req.entity_id)

        # Apply custom params
        if req.custom_params:
            context.update(req.custom_params)

        # Render workflow
        workflow_data = build_workflow(req.template, context)

        return WorkflowBuildResponse(
            template=req.template,
            workflow=workflow_data,
            context_summary={
                "entity_type": req.entity_type,
                "entity_id": req.entity_id,
                "positive_prompt": context.get("positive_prompt", ""),
                "negative_prompt": context.get("negative_prompt", ""),
                "seed": context.get("seed"),
                "steps": context.get("steps"),
                "cfg": context.get("cfg"),
            },
        )

    except WordPressAPIError as e:
        raise HTTPException(status_code=500, detail=f"WordPress error: {str(e)}")
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Workflow build error: {str(e)}")


# ── Generation endpoints ────────────────────────────────────────────────────


@app.post("/generate", response_model=GenerateResponse)
def generate(req: GenerateRequest):
    """Generate an asset using a workflow template.

    Args:
        post_id: WordPress post ID to generate for
        workflow: Workflow template name (default: 'base')
        custom_params: Override parameters (seed, steps, cfg, etc.)
    """
    try:
        # Validate post exists
        post = get_post(req.post_id)

        # Send task to Celery
        task = celery_app.send_task(
            "tasks.generate_video_task",
            args=[req.post_id, req.workflow, req.custom_params],
        )

        # Update WordPress post meta
        meta = post.get("meta", {}) or {}
        meta["video_status"] = "queued"
        meta["video_job_id"] = task.id

        update_post_meta(req.post_id, meta)

        return GenerateResponse(
            job_id=task.id,
            status="queued",
            workflow=req.workflow,
            post_id=req.post_id,
        )

    except WordPressAPIError as e:
        raise HTTPException(status_code=500, detail=f"WordPress error: {str(e)}")
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Generation error: {str(e)}")


@app.get("/status/{job_id}", response_model=TaskStatusResponse)
def status(job_id: str):
    """Get the status of a generation job."""
    result = AsyncResult(job_id, app=celery_app)
    info = result.info or {}

    return TaskStatusResponse(
        job_id=job_id,
        state=result.state,
        progress=info.get("progress"),
        message=info.get("message"),
        result=info.get("result"),
    )


@app.get("/tasks", response_model=TaskListResponse)
def list_tasks(
    status_filter: Optional[str] = None,
    limit: int = 50,
    offset: int = 0,
):
    """List generation tasks with optional status filtering.

    NOTE: This is a simplified implementation. In production, you'd want
    to store task metadata in Redis/DB for efficient querying.
    """
    # Get all known task IDs from backend
    # This is a simplified approach — in production, use a proper task registry
    tasks = []

    try:
        backend = celery_app.backend
        # Iterate through results (simplified — production should use task registry)
        # For now, return empty list with note
        tasks = []
    except Exception:
        pass

    # Apply filters
    if status_filter:
        tasks = [t for t in tasks if t.get("status") == status_filter]

    # Apply pagination
    paginated_tasks = tasks[offset : offset + limit]

    return TaskListResponse(
        tasks=paginated_tasks,
        total=len(tasks),
        limit=limit,
        offset=offset,
    )


# ── Queue management endpoints ─────────────────────────────────────────────

@app.post("/queue/submit/{post_id}")
def submit_task(
    post_id: int,
    workflow: str = "base",
    entity_type: str = "post",
    custom_params: Optional[dict] = None,
):
    """Submit a task to the queue with rate limiting."""
    try:
        job_id = queue_manager.submit_task(
            task_name="tasks.generate_video_task",
            post_id=post_id,
            workflow=workflow,
            entity_type=entity_type,
            custom_params=custom_params,
        )

        if job_id is None:
            raise HTTPException(
                status_code=429,
                detail="Rate limit exceeded. Too many concurrent tasks for this post.",
            )

        return {
            "job_id": job_id,
            "post_id": post_id,
            "workflow": workflow,
            "status": "queued",
        }

    except WordPressAPIError as e:
        raise HTTPException(status_code=500, detail=f"WordPress error: {str(e)}")
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Queue submission error: {str(e)}")


@app.post("/queue/cancel/{job_id}")
def cancel_task(job_id: str):
    """Cancel a task by job ID."""
    success, message = queue_manager.cancel_task(job_id)

    if not success:
        raise HTTPException(status_code=400, detail=message)

    return {
        "job_id": job_id,
        "cancelled": True,
        "message": message,
    }


@app.get("/queue/active")
def get_active_tasks():
    """Get all currently running/queued tasks."""
    active = queue_manager.get_active_tasks()
    return {"active_tasks": active, "count": len(active)}


@app.get("/queue/pending")
def get_pending_tasks():
    """Get all queued (pending) tasks."""
    pending = queue_manager.get_pending_tasks()
    return {"pending_tasks": pending, "count": len(pending)}


@app.get("/queue/list")
def list_all_tasks(
    status_filter: Optional[str] = None,
    post_id: Optional[int] = None,
    workflow: Optional[str] = None,
    limit: int = 50,
    offset: int = 0,
):
    """List all tasks with filters and pagination."""
    result = queue_manager.list_tasks(
        status_filter=status_filter,
        post_id=post_id,
        workflow=workflow,
        limit=limit,
        offset=offset,
    )
    return result


@app.get("/queue/task/{job_id}")
def get_task_status(job_id: str):
    """Get comprehensive task status from both Celery and registry."""
    status = queue_manager.get_task_status(job_id)

    if status is None:
        raise HTTPException(status_code=404, detail=f"Task {job_id} not found")

    return status


# ── Asset lineage endpoints ────────────────────────────────────────────────

@app.post("/assets")
def create_asset(req: dict):
    """Create an asset record with full provenance."""
    try:
        asset = asset_lineage.create_asset(
            source_post_id=req["source_post_id"],
            source_type=req.get("source_type", "scene"),
            workflow_template=req.get("workflow_template", "base"),
            prompt_id=req.get("prompt_id"),
            generation_params=req.get("generation_params", {}),
            output_media_url=req["output_media_url"],
            output_media_id=req.get("output_media_id"),
        )
        return {
            "success": True,
            "post_id": asset.get("id"),
            "link": asset.get("link"),
        }
    except WordPressAssetError as e:
        raise HTTPException(status_code=500, detail=str(e))
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Asset creation error: {str(e)}")


@app.get("/assets")
def list_assets(
    source_post_id: Optional[int] = None,
    source_type: Optional[str] = None,
    workflow_template: Optional[str] = None,
    per_page: int = 20,
    page: int = 1,
):
    """List assets with optional filters."""
    assets = asset_lineage.list_assets(
        source_post_id=source_post_id,
        source_type=source_type,
        workflow_template=workflow_template,
        per_page=per_page,
        page=page,
    )
    return {"assets": assets, "count": len(assets)}


@app.get("/assets/{post_id}")
def get_asset(post_id: int):
    """Get an asset by post ID."""
    asset = asset_lineage.get_asset(post_id)

    if asset is None:
        raise HTTPException(status_code=404, detail=f"Asset {post_id} not found")

    return asset


@app.post("/assets/{post_id}/status")
def update_asset_status(post_id: int, req: dict):
    """Update asset status (pending, processing, done, error)."""
    status = req.get("status")
    if status not in ("pending", "processing", "done", "error"):
        raise HTTPException(
            status_code=400,
            detail="Status must be one of: pending, processing, done, error",
        )

    success = asset_lineage.update_asset_status(
        post_id=post_id,
        status=status,
        metadata=req.get("metadata"),
    )

    if not success:
        raise HTTPException(status_code=500, detail="Failed to update asset status")

    return {"success": True, "post_id": post_id, "status": status}


@app.post("/assets/{post_id}/media")
def upload_media(post_id: int, req: dict):
    """Upload media file to WordPress and associate with asset."""
    file_path = req.get("file_path")
    title = req.get("title", f"Asset media {post_id}")

    if not file_path:
        raise HTTPException(status_code=400, detail="file_path is required")

    try:
        media = asset_lineage.upload_media(
            file_path=file_path,
            title=title,
            source_post_id=post_id,
        )
        return {
            "success": True,
            "media_id": media.get("id"),
            "media_url": media.get("guid", {}).get("rendered"),
        }
    except WordPressAssetError as e:
        raise HTTPException(status_code=500, detail=str(e))
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Media upload error: {str(e)}")


# ── Metrics endpoint ───────────────────────────────────────────────────────

@app.get("/metrics")
def metrics():
    """Prometheus-style metrics endpoint."""
    return get_metrics()


# ── Agent endpoints ────────────────────────────────────────────────────────

@app.get("/agents")
def list_agents():
    """List all available agents and their capabilities."""
    return orchestrator.get_advisor_summary()


@app.post("/agents/orchestrator")
def orchestrator_request(req: dict):
    """Send a request to the Executive Orchestrator.

    The orchestrator will route to appropriate advisor(s) based on
    the request content and context.

    Args:
        request: User's question or request
        context: Optional project context (story graph, assets, etc.)
        force_advisor: Optional force routing to specific advisor
    """
    user_request = req.get("request", "")
    if not user_request:
        raise HTTPException(status_code=400, detail="request field is required")

    context = req.get("context", {})
    force_advisor = req.get("force_advisor")

    result = orchestrator.process_request(
        user_request=user_request,
        context=context,
        force_advisor=force_advisor,
    )

    return result


@app.post("/agents/story")
def story_advisor_request(req: dict):
    """Send a narrative question to the Story Advisor.

    Args:
        question: Narrative question or request
        story_context: Story Graph context
    """
    question = req.get("question", "")
    if not question:
        raise HTTPException(status_code=400, detail="question field is required")

    story_context = req.get("story_context", {})

    result = orchestrator.ask_story(
        question=question,
        story_context=story_context,
    )

    return result


@app.post("/agents/prompt")
def prompt_advisor_request(req: dict):
    """Generate an asset generation prompt.

    Args:
        prompt_type: Type of prompt ('character', 'environment', 'storyboard')
        target_data: Target data (character, location, scene)
        style_reference: Optional style reference
    """
    prompt_type = req.get("prompt_type", "character")
    target_data = req.get("target_data", {})

    if not target_data:
        raise HTTPException(status_code=400, detail="target_data is required")

    style_reference = req.get("style_reference", "")

    result = orchestrator.generate_prompt(
        prompt_type=prompt_type,
        target_data=target_data,
        style_reference=style_reference,
    )

    return result


@app.post("/agents/production")
def production_advisor_request(req: dict):
    """Check production status and get recommendations.

    Args:
        project_data: Project metadata and status
    """
    project_data = req.get("project_data", {})

    if not project_data:
        raise HTTPException(status_code=400, detail="project_data is required")

    result = orchestrator.check_production_status(
        project_data=project_data,
    )

    return result


@app.post("/agents/technical")
def technical_advisor_request(req: dict):
    """Troubleshoot a technical issue.

    Args:
        service: Service name (wordpress, comfyui, redis, celery)
        error_message: Error message or description
        context: Additional context (logs, config, etc.)
    """
    service = req.get("service", "")
    error_message = req.get("error_message", "")

    if not service or not error_message:
        raise HTTPException(
            status_code=400,
            detail="service and error_message are required",
        )

    context = req.get("context")

    result = orchestrator.troubleshoot(
        service=service,
        error_message=error_message,
        context=context,
    )

    return result


@app.post("/agents/editorial")
def editorial_advisor_request(req: dict):
    """Review an asset for quality or narrative fit.

    Args:
        asset: Asset data
        review_type: Type of review ('quality', 'style', 'narrative')
    """
    asset = req.get("asset", {})

    if not asset:
        raise HTTPException(status_code=400, detail="asset is required")

    review_type = req.get("review_type", "quality")

    result = orchestrator.review_asset(
        asset=asset,
        review_type=review_type,
    )

    return result


@app.post("/agents/review")
def multi_advisor_review(req: dict):
    """Conduct a comprehensive multi-advisor review.

    Invokes Story, Editorial, and Production advisors simultaneously.

    Args:
        asset: Asset to review
        story_context: Story Graph context
        project_data: Project metadata
    """
    asset = req.get("asset", {})
    story_context = req.get("story_context", {})
    project_data = req.get("project_data", {})

    if not asset:
        raise HTTPException(status_code=400, detail="asset is required")

    result = orchestrator.multi_advisor_review(
        asset=asset,
        story_context=story_context,
        project_data=project_data,
    )

    return result


@app.get("/agents/history")
def get_agent_history(limit: int = 10):
    """Get recent agent conversation history.

    Args:
        limit: Number of recent entries to return (default: 10)
    """
    return {
        "history": orchestrator.get_conversation_history(limit=limit),
        "total": len(orchestrator.get_conversation_history()),
    }


# ── Story Graph Intelligence endpoints ──────────────────────────────────────

@app.post("/intelligence/search", response_model=SemanticSearchResponse)
def semantic_search(req: SemanticSearchRequest):
    """Search Story Graph entities by semantic similarity.

    Uses embeddings to find entities matching the natural language query.
    Falls back to keyword search when embeddings are unavailable.

    Args:
        query: Natural language search query
        entity_types: Limit search to specific entity types
        top_k: Maximum number of results
        min_score: Minimum similarity threshold
        use_hybrid: Use hybrid semantic + keyword search
    """
    import time
    start = time.time()

    try:
        if req.use_hybrid:
            results = intelligence.hybrid_search(
                query=req.query,
                entity_types=req.entity_types,
                top_k=req.top_k,
            )
        else:
            results = intelligence.semantic_search(
                query=req.query,
                entity_types=req.entity_types,
                top_k=req.top_k,
                min_score=req.min_score,
            )

        search_time_ms = round((time.time() - start) * 1000, 2)

        return SemanticSearchResponse(
            query=req.query,
            results=[SearchHit(**r) for r in results],
            total=len(results),
            search_time_ms=search_time_ms,
        )

    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Search error: {str(e)}")


@app.post("/intelligence/index")
def index_entities_endpoint():
    """Build embedding index for Story Graph entities.

    Pre-computes embeddings for all entities to speed up search queries.
    Returns index metadata.
    """
    try:
        index_result = intelligence.index_entities()
        return IndexEntitiesResponse(
            indexed_at=index_result["indexed_at"],
            entity_types=index_result["entity_types"],
            total_entries=index_result["total_entries"],
        )
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Index error: {str(e)}")


@app.post("/intelligence/validate", response_model=ContinuityValidationResponse)
def validate_continuity_endpoint(req: ContinuityValidationRequest):
    """Validate narrative continuity across scenes.

    Checks for character appearance inconsistencies, location jumps,
    prop continuity, scene ordering, and relationship conflicts.

    Args:
        episode_id: Validate only scenes in this episode
        scene_ids: Validate only specific scenes
    """
    try:
        issues = intelligence.validate_continuity(
            episode_id=req.episode_id,
            scene_ids=req.scene_ids,
        )

        # Count by severity
        errors = sum(1 for i in issues if i.severity == "error")
        warnings = sum(1 for i in issues if i.severity == "warning")
        infos = sum(1 for i in issues if i.severity == "info")

        # Count unique scenes validated
        scene_ids_validated = set()
        for issue in issues:
            for entity in issue.entities:
                if entity.get("type") == "scene":
                    scene_ids_validated.add(entity["id"])

        # Also count scenes from the request
        if req.scene_ids:
            scene_ids_validated.update(req.scene_ids)

        return ContinuityValidationResponse(
            issues=[
                ContinuityIssue(
                    severity=i.severity,
                    category=i.category,
                    description=i.description,
                    entities=i.entities,
                    suggestion=i.suggestion,
                )
                for i in issues
            ],
            total_issues=len(issues),
            errors=errors,
            warnings=warnings,
            infos=infos,
            scenes_validated=len(scene_ids_validated),
        )

    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Validation error: {str(e)}")


@app.get("/intelligence/character-network", response_model=CharacterNetworkResponse)
def get_character_network(
    character_id: Optional[int] = None,
    scene_ids: Optional[str] = None,
):
    """Get character relationship network analytics.

    Returns co-occurrence data, strongest relationships, and scene presence.

    Query params:
        character_id: Limit to a specific character
        scene_ids: Comma-separated list of scene IDs to limit analysis
    """
    try:
        scene_id_list = None
        if scene_ids:
            scene_id_list = [int(x.strip()) for x in scene_ids.split(",") if x.strip()]

        network_data = intelligence.get_character_network_summary()

        return CharacterNetworkResponse(
            total_characters=network_data["total_characters"],
            total_scenes=network_data["total_scenes"],
            strongest_relationships=network_data["strongest_relationships"],
            scene_presence=network_data["scene_presence"],
        )

    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Network analytics error: {str(e)}")


@app.get("/intelligence/graph-analytics", response_model=GraphAnalyticsResponse)
def get_graph_analytics():
    """Get overall Story Graph analytics.

    Returns entity counts, relationship density, most connected entities,
    and isolated entities with no relationships.
    """
    try:
        analytics = intelligence.compute_graph_analytics()

        return GraphAnalyticsResponse(
            total_entities=analytics.total_entities,
            entity_counts=analytics.entity_counts,
            total_relationships=analytics.total_relationships,
            density=analytics.density,
            most_connected=analytics.most_connected,
            isolated_entities=analytics.isolated_entities,
            character_count=analytics.entity_counts.get("characters", 0),
        )

    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Graph analytics error: {str(e)}")


@app.get("/intelligence/relationships")
def get_relationship_graph(
    scene_ids: Optional[str] = None,
):
    """Get the full relationship graph.

    Returns all relationships between entities (character-scene, scene-location,
    scene-prop, character-character).

    Query params:
        scene_ids: Comma-separated list of scene IDs to filter relationships
    """
    try:
        scene_id_list = None
        if scene_ids:
            scene_id_list = [int(x.strip()) for x in scene_ids.split(",") if x.strip()]

        edges = intelligence.compute_relationship_graph(scene_ids=scene_id_list)

        return {
            "edges": [
                {
                    "source_type": e.source_type,
                    "source_id": e.source_id,
                    "source_name": e.source_name,
                    "target_type": e.target_type,
                    "target_id": e.target_id,
                    "target_name": e.target_name,
                    "relation_type": e.relation_type,
                    "strength": e.strength,
                }
                for e in edges
            ],
            "total_edges": len(edges),
        }

    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Relationship graph error: {str(e)}")


@app.post("/intelligence/character-analytics")
def get_character_analytics(req: CharacterNetworkRequest):
    """Get detailed analytics for one or more characters.

    Returns scene count, co-occurrences, locations, props used, and relationships.

    Body:
        character_id: Limit to a specific character (optional)
        scene_ids: Limit to specific scenes (optional)
    """
    try:
        analytics = intelligence.compute_character_analytics(
            character_id=req.character_id,
            scene_ids=req.scene_ids,
        )

        return {
            "characters": [
                {
                    "character_id": a.character_id,
                    "name": a.name,
                    "scene_count": a.scene_count,
                    "scenes": a.scenes,
                    "co_occurrences": a.co_occurrences,
                    "locations": a.locations,
                    "props_used": a.props_used,
                    "relationship_edges": a.relationship_edges,
                }
                for a in analytics
            ],
            "total_characters": len(analytics),
        }

    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Character analytics error: {str(e)}")


@app.post("/intelligence/cache/clear")
def clear_intelligence_cache():
    """Clear the intelligence engine cache."""
    intelligence.clear_cache()
    return {"success": True, "message": "Cache cleared"}


# ── Legacy compatibility ────────────────────────────────────────────────────


class LegacyGenerateRequest(BaseModel):
    post_id: int


@app.post("/generate/legacy")
def generate_legacy(req: LegacyGenerateRequest):
    """Legacy endpoint for backward compatibility."""
    return generate(GenerateRequest(post_id=req.post_id))


# ── MCP Agent Router ────────────────────────────────────────────────────────

# Determine agents directory: prefer includes/agents/, fall back to multi-agent-framework/agents/
_agents_dir = os.path.join(os.path.dirname(__file__), '..', 'wordpress', 'wp-content', 'plugins', 'storyos', 'includes', 'agents')
if not os.path.isdir(_agents_dir):
    _agents_dir = os.path.join(os.path.dirname(__file__), '..', 'multi-agent-framework', 'agents')

mcp_router = create_mcp_agent_router(_agents_dir)
app.include_router(mcp_router)

print(f"MCP Agent Router initialized with agents from: {_agents_dir}")
