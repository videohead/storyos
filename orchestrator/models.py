"""Pydantic models for StoryOS orchestrator API."""

from __future__ import annotations

from enum import Enum
from typing import Any, Optional

from pydantic import BaseModel, Field


# ── Enums ───────────────────────────────────────────────────────────────────

class TaskStatus(str, Enum):
    """Task lifecycle states."""
    PENDING = "pending"
    QUEUED = "queued"
    PROCESSING = "processing"
    COMPLETED = "completed"
    FAILED = "failed"
    CANCELLED = "cancelled"


class GenerationType(str, Enum):
    """Types of asset generation supported by workflow templates."""
    CHARACTER_SHEET = "character-sheet"
    ENVIRONMENT = "environment"
    STORYBOARD = "storyboard"
    LOOKBOOK = "lookbook"


class HealthStatus(str, Enum):
    """Overall health status."""
    OK = "healthy"
    DEGRADED = "degraded"
    UNHEALTHY = "unhealthy"


# ── Request Models ──────────────────────────────────────────────────────────

class GenerateRequest(BaseModel):
    """Request to generate an asset."""
    post_id: int
    provider_type: str = Field(
        description="Provider type slug registered in orchestrator (e.g., 'comfyui', 'veo')",
        min_length=1,
    )
    connection_id: int = Field(
        description="Provider connection identifier from control plane",
        ge=1,
    )
    workflow: GenerationType = Field(
        default=GenerationType.CHARACTER_SHEET,
        description="Workflow template to use",
    )
    custom_params: Optional[dict[str, Any]] = Field(
        default=None,
        description="Override default workflow parameters (seed, steps, cfg, etc.)",
    )


class GenerationPreviewRequest(BaseModel):
    """Request for Epic 5 preparation without provider dispatch."""
    entity_type: str = Field(default="post", min_length=1)
    entity_id: int = Field(ge=1)
    template_id: str = Field(min_length=1)
    template_version: Optional[str] = None
    provider_type: str = Field(min_length=1)
    connection_id: int = Field(ge=1)
    scf_values: dict[str, Any] = Field(default_factory=dict)
    story_graph_context: dict[str, Any] = Field(default_factory=dict)
    connection: dict[str, Any] = Field(default_factory=dict)
    user_overrides: dict[str, Any] = Field(default_factory=dict)


class BatchGenerateRequest(BaseModel):
    """Request to generate assets for multiple posts."""
    post_ids: list[int]
    workflow: GenerationType = Field(
        default=GenerationType.CHARACTER_SHEET,
        description="Workflow template to use for all items",
    )
    custom_params: Optional[dict[str, Any]] = Field(
        default=None,
        description="Override default workflow parameters",
    )


class CancelRequest(BaseModel):
    """Request to cancel a task."""
    job_id: str


class AgentRunRequest(BaseModel):
    """Request to run an agent advisor."""
    agent: str = Field(
        description="Agent name (e.g., 'story-advisor', 'prompt-advisor')",
    )
    context: dict[str, Any] = Field(
        description="Story Graph context to provide to the agent",
    )


# ── Response Models ─────────────────────────────────────────────────────────

class GenerateResponse(BaseModel):
    """Response from /generate endpoint."""
    job_id: str
    status: TaskStatus
    post_id: int
    workflow: str
    provider_type: str
    connection_id: int


class BatchGenerateResponse(BaseModel):
    """Response from /batch/generate endpoint."""
    jobs: list[GenerateResponse]
    total: int


class TaskStatusResponse(BaseModel):
    """Response from /status/{job_id} endpoint."""
    job_id: str
    state: str
    status: TaskStatus
    progress: Optional[float] = None
    message: Optional[str] = None
    result: Optional[dict[str, Any]] = None
    error: Optional[str] = None
    created_at: Optional[str] = None
    completed_at: Optional[str] = None


class TaskListItem(BaseModel):
    """Simplified task item for listing."""
    job_id: str
    status: TaskStatus
    progress: Optional[float] = None
    message: Optional[str] = None


class TaskListResponse(BaseModel):
    """Response from /tasks endpoint."""
    tasks: list[TaskListItem]


# ── Story Graph Intelligence Models ─────────────────────────────────────────

class SemanticSearchRequest(BaseModel):
    """Request for semantic search."""
    query: str = Field(
        description="Natural language search query",
        min_length=1,
    )
    entity_types: Optional[list[str]] = Field(
        default=None,
        description="Limit search to these entity types (e.g., ['characters', 'scenes'])",
    )
    top_k: int = Field(
        default=10,
        ge=1,
        le=100,
        description="Maximum number of results",
    )
    min_score: float = Field(
        default=0.1,
        ge=0.0,
        le=1.0,
        description="Minimum cosine similarity threshold",
    )
    use_hybrid: bool = Field(
        default=True,
        description="Use hybrid semantic + keyword search",
    )


class SearchHit(BaseModel):
    """A single search result."""
    entity_type: str
    entity_id: int
    title: str
    score: float
    snippet: str
    is_semantic: bool = True


class SemanticSearchResponse(BaseModel):
    """Response from semantic search."""
    query: str
    results: list[SearchHit]
    total: int
    search_time_ms: float


class IndexEntitiesResponse(BaseModel):
    """Response from indexing entities."""
    indexed_at: float
    entity_types: list[str]
    total_entries: int


class ContinuityValidationRequest(BaseModel):
    """Request for continuity validation."""
    episode_id: Optional[int] = Field(
        default=None,
        description="Validate only scenes in this episode",
    )
    scene_ids: Optional[list[int]] = Field(
        default=None,
        description="Validate only specific scenes",
    )


class ContinuityIssue(BaseModel):
    """A detected continuity issue."""
    severity: str  # "error", "warning", "info"
    category: str  # "character", "scene", "location", "prop", "timeline"
    description: str
    entities: list[dict[str, Any]] = Field(default_factory=list)
    suggestion: str = ""


class ContinuityValidationResponse(BaseModel):
    """Response from continuity validation."""
    issues: list[ContinuityIssue]
    total_issues: int
    errors: int
    warnings: int
    infos: int
    scenes_validated: int


class CharacterNetworkRequest(BaseModel):
    """Request for character network analytics."""
    character_id: Optional[int] = Field(
        default=None,
        description="Limit to a specific character",
    )
    scene_ids: Optional[list[int]] = Field(
        default=None,
        description="Limit to specific scenes",
    )


class CharacterNetworkResponse(BaseModel):
    """Response from character network analytics."""
    total_characters: int
    total_scenes: int
    strongest_relationships: list[dict[str, Any]]
    scene_presence: list[dict[str, Any]]


class GraphAnalyticsResponse(BaseModel):
    """Response from graph analytics."""
    total_entities: int
    entity_counts: dict[str, int]
    total_relationships: int
    density: float
    most_connected: dict[str, Any]
    isolated_entities: list[dict[str, Any]]
    character_count: int
    total: int
    filters: dict[str, Any] = {}


class TemplateListResponse(BaseModel):
    """Response from /workflows endpoint."""
    templates: list[dict[str, str]]
    total: int


class HealthServiceStatus(BaseModel):
    """Status of a single health check service."""
    status: str = "unknown"
    error: Optional[str] = None


class HealthResponse(BaseModel):
    """Response from /health endpoint."""
    status: str
    services: dict[str, HealthServiceStatus]
    timestamp: str = ""


class WorkflowBuildRequest(BaseModel):
    """Request to /workflows/build endpoint (dry-run)."""
    template: str = Field(
        description="Workflow template name to render",
    )
    entity_type: str = Field(
        default="post",
        description="Entity type: post, character, scene, location",
    )
    entity_id: int = Field(
        description="ID of the entity to build context for",
    )
    custom_params: Optional[dict[str, Any]] = Field(
        default=None,
        description="Override default workflow parameters",
    )


class WorkflowBuildResponse(BaseModel):
    """Response from /workflows/build endpoint (dry-run)."""
    template: str
    workflow: dict[str, Any]
    context_summary: dict[str, Any]


# ── Internal Models ─────────────────────────────────────────────────────────

class GenerationContext(BaseModel):
    """Story Graph context data for workflow rendering."""
    post_id: int
    entity_type: str = "scene"
    entity_id: Optional[int] = None

    # Scene/Shot fields
    scene_number: Optional[str] = None
    scene_title: Optional[str] = None
    shot_number: Optional[str] = None
    shot_type: Optional[str] = None

    # Character fields
    character_name: Optional[str] = None
    character_appearance: Optional[str] = None

    # Location fields
    location_name: Optional[str] = None
    location_description: Optional[str] = None

    # Generation fields
    positive_prompt: str = ""
    negative_prompt: str = "blurry, low quality, deformed"
    seed: int = 42
    steps: int = 20
    cfg: float = 8.0
    resolution_x: int = 1024
    resolution_y: int = 1024
    fps: int = 24

    # Reference image paths (resolved from media IDs)
    style_ref_path: Optional[str] = None
    pose_ref_path: Optional[str] = None
    visual_ref_path: Optional[str] = None

    # Metadata
    workflow_template: str = "base"
    generated_at: Optional[str] = None
