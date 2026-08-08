"""Story Graph Intelligence — semantic search, continuity validation, and relationship analytics.

Transforms StoryOS from a content management system into a narrative intelligence platform.
All operations query the WordPress Story Graph via REST API and compute results locally.
"""

from __future__ import annotations

import json
import logging
import math
import os
import re
import time
from collections import Counter, defaultdict
from dataclasses import dataclass, field
from typing import Any, Optional

import requests

logger = logging.getLogger(__name__)


# ── Embedding Backend Protocol ──────────────────────────────────────────────

class EmbeddingBackend:
    """Abstract embedding backend. Subclass to support different providers."""

    def encode(self, texts: list[str]) -> list[list[float]]:
        """Encode a batch of texts into embedding vectors.

        Args:
            texts: List of text strings to encode.

        Returns:
            List of embedding vectors (each a list of floats).
        """
        raise NotImplementedError

    def dimension(self) -> int:
        """Return the embedding vector dimension."""
        raise NotImplementedError


class DummyEmbeddingBackend(EmbeddingBackend):
    """Deterministic hash-based embeddings for testing without a model server.

    Produces stable 128-dimensional vectors from text content using a
    simple hash function. Good for development and CI; replace with
    a real backend in production.
    """

    def __init__(self, dimension: int = 128):
        self._dim = dimension

    def encode(self, texts: list[str]) -> list[list[float]]:
        vectors: list[list[float]] = []
        for text in texts:
            # Deterministic hash-based embedding
            encoded = [0.0] * self._dim
            for i, ch in enumerate(text):
                h = hash((ch, i % 64))
                for d in range(self._dim):
                    encoded[d] += math.sin(h + d) * 0.01
            # Normalize
            norm = math.sqrt(sum(v * v for v in encoded)) or 1.0
            vectors.append([v / norm for v in encoded])
        return vectors

    def dimension(self) -> int:
        return self._dim


class OllamaEmbeddingBackend(EmbeddingBackend):
    """Embedding backend using Ollama's embed endpoint."""

    def __init__(
        self,
        url: str = "http://localhost:11434",
        model: str = "nomic-embed-text",
        dimension: int = 768,
    ):
        self._url = f"{url}/api/embed"
        self._model = model
        self._dim = dimension

    def encode(self, texts: list[str]) -> list[list[float]]:
        if not texts:
            return []
        # Ollama embed API expects a single text; batch by calling sequentially
        vectors: list[list[float]] = []
        for text in texts:
            try:
                resp = requests.post(
                    self._url,
                    json={"model": self._model, "input": text},
                    timeout=30,
                )
                resp.raise_for_status()
                data = resp.json()
                # Ollama returns {"embeddings": [[...]]}
                emb = data.get("embeddings", [[]])[0]
                vectors.append(emb)
            except Exception as e:
                logger.warning("Ollama embed failed for text '%s...': %s", text[:40], e)
                vectors.append([0.0] * self._dim)
        return vectors

    def dimension(self) -> int:
        return self._dim


class SentenceTransformerBackend(EmbeddingBackend):
    """Embedding backend using sentence-transformers library (CPU/GPU)."""

    def __init__(
        self,
        model_name: str = "all-MiniLM-L6-v2",
        dimension: int = 384,
    ):
        self._model_name = model_name
        self._dim = dimension
        self._model = None
        self._tokenizer = None

    def _load(self):
        if self._model is None:
            try:
                from sentence_transformers import SentenceTransformer
                self._model = SentenceTransformer(self._model_name)
            except ImportError:
                raise ImportError(
                    "sentence-transformers is required. Install with: pip install sentence-transformers"
                )
        return self._model

    def encode(self, texts: list[str]) -> list[list[float]]:
        model = self._load()
        embeddings = model.encode(texts, normalize_embeddings=True, show_progress_bar=False)
        # Ensure list of lists
        if isinstance(embeddings, list):
            if embeddings and isinstance(embeddings[0], list):
                return embeddings
            return [list(embeddings)] if embeddings else []
        return embeddings.tolist() if hasattr(embeddings, "tolist") else [list(embeddings)]

    def dimension(self) -> int:
        return self._dim


# ── Similarity Utilities ────────────────────────────────────────────────────

def cosine_similarity(a: list[float], b: list[float]) -> float:
    """Compute cosine similarity between two vectors."""
    if len(a) != len(b):
        raise ValueError(f"Vector dimension mismatch: {len(a)} vs {len(b)}")
    dot = sum(x * y for x, y in zip(a, b))
    norm_a = math.sqrt(sum(x * x for x in a)) or 1.0
    norm_b = math.sqrt(sum(x * x for x in b)) or 1.0
    return dot / (norm_a * norm_b)


def euclidean_distance(a: list[float], b: list[float]) -> float:
    """Compute Euclidean distance between two vectors."""
    if len(a) != len(b):
        raise ValueError(f"Vector dimension mismatch: {len(a)} vs {len(b)}")
    return math.sqrt(sum((x - y) ** 2 for x, y in zip(a, b)))


# ── Data Structures ─────────────────────────────────────────────────────────

@dataclass
class SearchResult:
    """A single semantic search result."""
    entity_type: str
    entity_id: int
    title: str
    score: float
    snippet: str
    metadata: dict[str, Any] = field(default_factory=dict)


@dataclass
class ContinuityIssue:
    """A detected continuity issue."""
    severity: str  # "error", "warning", "info"
    category: str  # "character", "scene", "location", "prop", "timeline"
    description: str
    entities: list[dict[str, Any]] = field(default_factory=list)
    suggestion: str = ""


@dataclass
class RelationshipEdge:
    """A relationship between two Story Graph entities."""
    source_type: str
    source_id: int
    source_name: str
    target_type: str
    target_id: int
    target_name: str
    relation_type: str  # "appears_in", "located_in", "related_to", etc.
    strength: float = 1.0  # 0.0 - 1.0


@dataclass
class CharacterAnalytics:
    """Analytics for a single character."""
    character_id: int
    name: str
    scene_count: int
    scenes: list[dict[str, Any]]
    co_occurrences: dict[str, int]  # other character -> count
    locations: dict[str, int]  # location -> count
    props_used: dict[str, int]  # prop -> count
    relationship_edges: list[dict[str, Any]]


@dataclass
class GraphAnalytics:
    """Overall Story Graph analytics."""
    total_entities: int
    entity_counts: dict[str, int]  # entity_type -> count
    total_relationships: int
    character_network: list[CharacterAnalytics] = field(default_factory=list)
    density: float = 0.0  # edge density of the graph
    most_connected: dict[str, Any] = field(default_factory=dict)  # entity with most edges
    isolated_entities: list[dict[str, Any]] = field(default_factory=list)  # entities with no relationships


# ── StoryGraphIntelligence Engine ───────────────────────────────────────────

class StoryGraphIntelligence:
    """Core narrative intelligence engine.

    Provides semantic search, continuity validation, and relationship
    analytics over the Story Graph stored in WordPress.
    """

    def __init__(
        self,
        wordpress_url: str,
        username: str,
        app_password: str,
        timeout: int = 30,
        embedding_backend: Optional[EmbeddingBackend] = None,
        index_path: Optional[str] = None,
        cache_ttl: int = 300,
    ):
        self.base_url = wordpress_url.rstrip("/")
        self.username = username
        self.app_password = app_password
        self.timeout = timeout
        self._cache: dict[str, tuple[float, dict[str, Any]]] = {}
        self._cache_ttl = cache_ttl

        # Embedding backend — defaults to Dummy for zero-dependency dev
        self.embeddings = embedding_backend or DummyEmbeddingBackend()

        # Persistent index storage
        self._index_path = index_path
        self._index_data: Optional[dict[str, Any]] = None
        self._entity_modified: dict[str, dict[int, float]] = {}  # entity_type -> {id: modified_timestamp}

        # Load persisted index on startup if available
        if self._index_path and os.path.isfile(self._index_path):
            try:
                with open(self._index_path, "r") as f:
                    self._index_data = json.load(f)
                logger.info("Loaded embedding index from %s (%d entries)",
                    self._index_path,
                    self._index_data.get("total_entries", 0),
                )
                # Restore modified timestamps if present
                if "entity_modified" in self._index_data:
                    self._entity_modified = self._index_data["entity_modified"]
            except (json.JSONDecodeError, IOError) as e:
                logger.warning("Failed to load persisted index: %s", e)
                self._index_data = None

        # Text fields to index for semantic search, per entity type
        self._index_fields: dict[str, list[str]] = {
            "characters": ["title.rendered", "meta.biography", "meta.appearance", "meta.motivation", "meta.backstory"],
            "locations": ["title.rendered", "meta.description", "meta.mood"],
            "scenes": ["title.rendered", "meta.summary", "meta.description"],
            "shots": ["meta.description", "meta.visual_description"],
            "assets": ["title.rendered", "meta.description"],
            "props": ["title.rendered", "meta.description", "meta.purpose"],
            "projects": ["title.rendered", "meta.description"],
            "story_worlds": ["title.rendered", "meta.description"],
        }

    def _auth(self):
        return (self.username, self.app_password)

    def _get(self, endpoint: str, params: Optional[dict] = None) -> list[dict[str, Any]]:
        """Fetch a paginated list from WordPress REST API."""
        url = f"{self.base_url}/wp-json/wp/v2/{endpoint}"
        all_items: list[dict[str, Any]] = []
        page = 1
        per_page = 100
        while True:
            resp = requests.get(
                url,
                auth=self._auth(),
                params={**params, "per_page": per_page, "page": page} if params else {"per_page": per_page, "page": page},
                timeout=self.timeout,
            )
            resp.raise_for_status()
            items = resp.json()
            if not items:
                break
            all_items.extend(items)
            if len(items) < per_page:
                break
            page += 1
        return all_items

    def _get_post(self, post_type: str, post_id: int) -> dict[str, Any]:
        """Get a single post by ID."""
        url = f"{self.base_url}/wp-json/wp/v2/{post_type}/{post_id}"
        resp = requests.get(url, auth=self._auth(), timeout=self.timeout)
        resp.raise_for_status()
        return resp.json()

    def _cached_get(self, key: str, fetch_func) -> list[dict[str, Any]]:
        """Fetch with simple TTL-based caching."""
        now = time.time()
        if key in self._cache:
            cached_time, data = self._cache[key]
            if now - cached_time < self._cache_ttl:
                return data
        data = fetch_func()
        self._cache[key] = (now, data)
        return data

    def save_index(self) -> bool:
        """Persist the current index to disk.

        Returns:
            True if saved successfully, False otherwise.
        """
        if not self._index_path or not self._index_data:
            return False

        try:
            # Ensure parent directory exists
            parent_dir = os.path.dirname(self._index_path)
            if parent_dir:
                os.makedirs(parent_dir, exist_ok=True)

            # Write to temp file then rename (atomic on POSIX)
            tmp_path = self._index_path + ".tmp"
            with open(tmp_path, "w") as f:
                json.dump(self._index_data, f)
            os.replace(tmp_path, self._index_path)
            logger.info("Saved embedding index to %s (%d entries)",
                self._index_path,
                self._index_data.get("total_entries", 0),
            )
            return True
        except IOError as e:
            logger.error("Failed to save index to %s: %s", self._index_path, e)
            return False

    def load_index(self) -> Optional[dict[str, Any]]:
        """Load the index from disk.

        Returns:
            The loaded index dict, or None if not available.
        """
        if not self._index_path or not os.path.isfile(self._index_path):
            return None

        try:
            with open(self._index_path, "r") as f:
                self._index_data = json.load(f)
            if "entity_modified" in self._index_data:
                self._entity_modified = self._index_data["entity_modified"]
            logger.info("Loaded embedding index from %s (%d entries)",
                self._index_path,
                self._index_data.get("total_entries", 0),
            )
            return self._index_data
        except (json.JSONDecodeError, IOError) as e:
            logger.warning("Failed to load index from %s: %s", self._index_path, e)
            return None

    def index_needs_update(self, entity_type: str) -> bool:
        """Check if an entity type's index is stale.

        An index is stale if:
        - No persisted index exists
        - The entity type is not in the persisted index
        - Any entity of this type was modified after the index was built

        Returns:
            True if the index needs rebuilding for this entity type.
        """
        if not self._index_data:
            return True

        indexed_at = self._index_data.get("indexed_at", 0)
        entity_entries = self._index_data.get("index", {}).get(entity_type, [])

        if not entity_entries:
            return True

        modified = self._entity_modified.get(entity_type, {})
        for eid, mtime in modified.items():
            if mtime > indexed_at:
                logger.debug("Entity %s/%d modified at %s > index at %s",
                    entity_type, eid, mtime, indexed_at)
                return True

        return False

    def _get_entity_text(self, entity: dict[str, Any]) -> str:
        """Extract readable text from an entity (post or flat dict).

        Handles both WordPress REST API format (nested title/content)
        and flat dicts.
        """
        parts: list[str] = []
        # WordPress REST API format
        title = entity.get("title", {})
        if isinstance(title, dict):
            rendered = title.get("rendered", "")
            if rendered:
                parts.append(rendered)
        else:
            # Flat dict
            if title:
                parts.append(str(title))

        content = entity.get("content", {})
        if isinstance(content, dict):
            rendered = content.get("rendered", "")
            if rendered:
                parts.append(rendered)
        else:
            # Flat dict
            if content:
                parts.append(str(content))

        return " ".join(parts)

    def _extract_text(self, post: dict[str, Any], fields: list[str]) -> str:
        """Extract searchable text from a post using dot-notation field paths."""
        parts: list[str] = []
        for field_path in fields:
            value = self._get_nested(post, field_path)
            if value and isinstance(value, str) and value.strip():
                parts.append(value.strip())
        return " ".join(parts)

    @staticmethod
    def _get_nested(obj: dict, path: str):
        """Get a nested dict value using dot notation."""
        keys = path.split(".")
        current = obj
        for key in keys:
            if isinstance(current, dict):
                current = current.get(key)
            else:
                return None
            if current is None:
                return None
        return current

    # ── Semantic Search ───────────────────────────────────────────────────

    def index_entities(
        self,
        entity_types: Optional[list[str]] = None,
        force_rebuild: bool = False,
    ) -> dict[str, Any]:
        """Build embedding index for all (or specified) Story Graph entities.

        Uses persistent index if available and not stale. Saves index to disk
        after building if persistence is configured.

        Args:
            entity_types: Entity types to index. None = all configured types.
            force_rebuild: If True, rebuild index even if cached version exists.

        Returns:
            Dict with index metadata and per-entity embedding references.
        """
        # Try to load cached index first (Optimization 1: Persistent Storage)
        if not force_rebuild and self._index_path:
            if self.load_index() is not None:
                # Check if index is still valid
                all_current = all(
                    not self.index_needs_update(et) for et in 
                    (entity_types or list(self._index_fields.keys()))
                )
                if all_current:
                    logger.info("Using cached embedding index")
                    return self._index_data

        entity_types = entity_types or list(self._index_fields.keys())
        index: dict[str, list[dict[str, Any]]] = {}

        for entity_type in entity_types:
            if entity_type not in self._index_fields:
                logger.warning("No index fields configured for '%s', skipping", entity_type)
                continue

            posts = self._cached_get(
                f"{entity_type}_all",
                lambda et=entity_type: self._get(et),
            )

            entries: list[dict[str, Any]] = []
            texts: list[str] = []

            for post in posts:
                text = self._extract_text(post, self._index_fields[entity_type])
                if text.strip():
                    entries.append({
                        "entity_type": entity_type,
                        "entity_id": post.get("id"),
                        "title": post.get("title", {}).get("rendered", ""),
                        "text": text,
                    })
                    texts.append(text)

            if texts:
                embeddings = self.embeddings.encode(texts)
                for i, emb in enumerate(embeddings):
                    entries[i]["embedding"] = emb

            index[entity_type] = entries
            logger.info("Indexed %d %s entries", len(entries), entity_type)

        index_data = {
            "indexed_at": time.time(),
            "entity_types": entity_types,
            "total_entries": sum(len(v) for v in index.values()),
            "index": index,
            "entity_modified": self._entity_modified,
        }

        # Save index to disk for persistence (Optimization 1)
        if self._index_path:
            self.save_index()

        return index_data

    def semantic_search(
        self,
        query: str,
        entity_types: Optional[list[str]] = None,
        top_k: int = 10,
        min_score: float = 0.1,
        index: Optional[dict[str, Any]] = None,
    ) -> list[SearchResult]:
        """Search Story Graph entities by semantic similarity.

        Args:
            query: Natural language search query.
            entity_types: Limit search to these entity types. None = all.
            top_k: Maximum number of results.
            min_score: Minimum cosine similarity threshold.
            index: Pre-built index (if None, builds one on the fly).

        Returns:
            List of SearchResult sorted by relevance.
        """
        # Build or use provided index
        if index is None:
            index = self.index_entities(entity_types)

        entity_types = entity_types or list(index.get("index", {}).keys())
        query_embedding = self.embeddings.encode([query])[0]

        results: list[SearchResult] = []
        for entity_type in entity_types:
            entries = index.get("index", {}).get(entity_type, [])
            for entry in entries:
                emb = entry.get("embedding", [])
                if not emb:
                    continue
                score = cosine_similarity(query_embedding, emb)
                if score >= min_score:
                    results.append(SearchResult(
                        entity_type=entry["entity_type"],
                        entity_id=entry["entity_id"],
                        title=entry["title"],
                        score=round(score, 4),
                        snippet=entry["text"][:200],
                    ))

        results.sort(key=lambda r: r.score, reverse=True)
        return results[:top_k]

    def fuzzy_search(
        self,
        query: str,
        entity_types: Optional[list[str]] = None,
        top_k: int = 10,
    ) -> list[dict[str, Any]]:
        """Keyword-based fallback search for when embeddings are unavailable.

        Uses simple TF-like scoring on title and text fields.
        """
        entity_types = entity_types or list(self._index_fields.keys())
        query_terms = set(re.findall(r"\w+", query.lower()))

        results: list[dict[str, Any]] = []
        for entity_type in entity_types:
            posts = self._cached_get(
                f"{entity_type}_all",
                lambda et=entity_type: self._get(et),
            )
            for post in posts:
                text = self._extract_text(post, self._index_fields[entity_type]).lower()
                title = post.get("title", {}).get("rendered", "").lower()

                # Score: title matches weighted 3x, text matches 1x
                title_terms = set(re.findall(r"\w+", title))
                text_terms = set(re.findall(r"\w+", text))

                title_overlap = len(query_terms & title_terms)
                text_overlap = len(query_terms & text_terms)

                if title_overlap == 0 and text_overlap == 0:
                    continue

                score = (title_overlap * 3) + text_overlap
                results.append({
                    "entity_type": entity_type,
                    "entity_id": post.get("id"),
                    "title": post.get("title", {}).get("rendered", ""),
                    "score": score,
                    "snippet": text[:200],
                })

        results.sort(key=lambda r: r["score"], reverse=True)
        return results[:top_k]

    def hybrid_search(
        self,
        query: str,
        entity_types: Optional[list[str]] = None,
        top_k: int = 10,
        semantic_weight: float = 0.7,
        keyword_weight: float = 0.3,
    ) -> list[dict[str, Any]]:
        """Combine semantic and keyword search results.

        Args:
            query: Search query.
            entity_types: Entity types to search.
            top_k: Max results.
            semantic_weight: Weight for semantic search (0-1).
            keyword_weight: Weight for keyword search (0-1).

        Returns:
            List of merged results sorted by combined score.
        """
        semantic_results = self.semantic_search(query, entity_types, top_k=top_k * 2)
        keyword_results = self.fuzzy_search(query, entity_types, top_k=top_k * 2)

        # Normalize keyword scores to 0-1
        max_kw_score = max((r["score"] for r in keyword_results), default=1) or 1
        for r in keyword_results:
            r["score"] = r["score"] / max_kw_score

        # Merge: semantic results get their score * semantic_weight
        # Keyword-only results get keyword_weight * normalized_score
        merged: dict[int, dict[str, Any]] = {}

        for r in semantic_results:
            key = (r.entity_type, r.entity_id)
            merged[key] = {
                "entity_type": r.entity_type,
                "entity_id": r.entity_id,
                "title": r.title,
                "score": r.score * semantic_weight,
                "snippet": r.snippet,
                "is_semantic": True,
            }

        for r in keyword_results:
            key = (r["entity_type"], r["entity_id"])
            if key in merged:
                merged[key]["score"] += keyword_weight * r["score"]
            else:
                merged[key] = {
                    "entity_type": r["entity_type"],
                    "entity_id": r["entity_id"],
                    "title": r["title"],
                    "score": keyword_weight * r["score"],
                    "snippet": r["snippet"],
                    "is_semantic": False,
                }

        results = sorted(merged.values(), key=lambda r: r["score"], reverse=True)
        return results[:top_k]

    # ── Continuity Validation ─────────────────────────────────────────────

    def validate_continuity(
        self,
        episode_id: Optional[int] = None,
        scene_ids: Optional[list[int]] = None,
    ) -> list[ContinuityIssue]:
        """Validate narrative continuity across scenes.

        Checks for:
        - Characters appearing in scenes they're not associated with
        - Location inconsistencies (scenes at wrong locations)
        - Prop continuity (props used without being defined)
        - Timeline inconsistencies (scene order vs date)
        - Character relationship conflicts

        Args:
            episode_id: Validate only scenes in this episode.
            scene_ids: Validate only specific scenes.

        Returns:
            List of ContinuityIssue detected.
        """
        issues: list[ContinuityIssue] = []

        # Fetch all scenes
        scenes = self._cached_get(
            "scenes_all",
            lambda: self._get("scenes"),
        )

        if episode_id:
            scenes = [s for s in scenes if self._get_nested(s, "meta.episode") == episode_id]

        if scene_ids:
            scenes = [s for s in scenes if s.get("id") in scene_ids]

        if not scenes:
            return issues

        # ── Check 1: Character appearance consistency ──
        issues.extend(self._check_character_appearances(scenes))

        # ── Check 2: Location consistency ──
        issues.extend(self._check_location_consistency(scenes))

        # ── Check 3: Prop continuity ──
        issues.extend(self._check_prop_continuity(scenes))

        # ── Check 4: Scene ordering ──
        issues.extend(self._check_scene_order(scenes))

        # ── Check 5: Character relationship consistency ──
        issues.extend(self._check_relationship_consistency(scenes))

        # ── Check 6: Empty or minimal content detection ──
        issues.extend(self._check_content_completeness(scenes))

        return issues

    def _check_character_appearances(
        self, scenes: list[dict[str, Any]]
    ) -> list[ContinuityIssue]:
        """Check that characters appear only in scenes they're associated with."""
        issues: list[ContinuityIssue] = []

        # Build character -> scenes mapping from all characters
        char_scenes: dict[int, list[int]] = defaultdict(list)
        characters = self._cached_get(
            "characters_all",
            lambda: self._get("characters"),
        )

        for char in characters:
            char_id = char.get("id")
            scene_refs = self._get_nested(char, "meta.scenes") or []
            for scene_ref in scene_refs:
                scene_id = scene_ref if isinstance(scene_ref, int) else scene_ref.get("id")
                char_scenes[char_id].append(scene_id)

        # Check each scene's character list
        scene_chars: dict[int, list[int]] = defaultdict(list)
        for scene in scenes:
            scene_id = scene.get("id")
            char_refs = self._get_nested(scene, "meta.characters") or []
            for char_ref in char_refs:
                char_id = char_ref if isinstance(char_ref, int) else char_ref.get("id")
                scene_chars[scene_id].append(char_id)

                # If character not listed as appearing in this scene
                if char_id in char_scenes and scene_id not in char_scenes[char_id]:
                    issues.append(ContinuityIssue(
                        severity="warning",
                        category="character",
                        description=f"Character {char_id} appears in scene {scene_id} but is not listed in their appearance list",
                        entities=[
                            {"type": "character", "id": char_id},
                            {"type": "scene", "id": scene_id},
                        ],
                        suggestion="Verify if this character should appear in this scene and update their appearance list.",
                    ))

        return issues

    def _check_location_consistency(
        self, scenes: list[dict[str, Any]]
    ) -> list[ContinuityIssue]:
        """Check that scenes are assigned to valid locations and detect location jumps."""
        issues: list[ContinuityIssue] = []

        # Fetch all locations
        locations = self._cached_get(
            "locations_all",
            lambda: self._get("locations"),
        )
        valid_location_ids = {loc.get("id") for loc in locations}

        # Check each scene's location assignment
        prev_location: Optional[int] = None
        prev_scene_num: Optional[str] = None

        sorted_scenes = sorted(
            scenes,
            key=lambda s: str(self._get_nested(s, "meta.scene_number") or ""),
        )

        for scene in sorted_scenes:
            scene_id = scene.get("id")
            location_id = self._get_nested(scene, "meta.location")

            if location_id and location_id not in valid_location_ids:
                issues.append(ContinuityIssue(
                    severity="error",
                    category="location",
                    description=f"Scene {scene.get('title', {}).get('rendered', scene_id)} references location {location_id} which does not exist",
                    entities=[
                        {"type": "scene", "id": scene_id},
                    ],
                    suggestion=f"Create location {location_id} or assign a valid location to this scene.",
                ))

            # Detect extreme location jumps between consecutive scenes
            if prev_location is not None and location_id and location_id != prev_location:
                scene_num = str(self._get_nested(scene, "meta.scene_number") or "")
                if prev_scene_num and scene_num:
                    # Check if there's a transition scene
                    has_transition = any(
                        self._get_nested(s, "meta.scene_number") == "transition"
                        for s in scenes
                    )
                    if not has_transition:
                        issues.append(ContinuityIssue(
                            severity="info",
                            category="location",
                            description=f"Location jump from scene {prev_scene_num} to {scene_num} — consider adding a transition scene",
                            entities=[
                                {"type": "scene", "id": scene_id},
                                {"type": "location", "id": location_id},
                            ],
                            suggestion="Add a transition scene or travel sequence between these locations.",
                        ))

            if location_id:
                prev_location = location_id
                prev_scene_num = str(self._get_nested(scene, "meta.scene_number") or "")

        return issues

    def _check_prop_continuity(
        self, scenes: list[dict[str, Any]]
    ) -> list[ContinuityIssue]:
        """Check that props used in scenes are defined and track their usage."""
        issues: list[ContinuityIssue] = []

        # Fetch all props
        props = self._cached_get(
            "props_all",
            lambda: self._get("props"),
        )
        valid_prop_ids = {prop.get("id") for prop in props}

        # Track prop usage across scenes
        prop_usage: dict[int, list[int]] = defaultdict(list)

        for scene in scenes:
            scene_id = scene.get("id")
            prop_refs = self._get_nested(scene, "meta.props") or []
            for prop_ref in prop_refs:
                prop_id = prop_ref if isinstance(prop_ref, int) else prop_ref.get("id")
                prop_usage[prop_id].append(scene_id)

                if prop_id not in valid_prop_ids:
                    issues.append(ContinuityIssue(
                        severity="error",
                        category="prop",
                        description=f"Scene {scene_id} uses prop {prop_id} which is not defined",
                        entities=[
                            {"type": "scene", "id": scene_id},
                        ],
                        suggestion=f"Create prop {prop_id} or remove the reference from this scene.",
                    ))

        # Check for props used in only one scene (might be forgotten)
        for prop_id, scene_ids in prop_usage.items():
            if len(scene_ids) == 1:
                issues.append(ContinuityIssue(
                    severity="info",
                    category="prop",
                    description=f"Prop {prop_id} is used in only one scene ({scene_ids[0]}) — verify this is intentional",
                    entities=[
                        {"type": "prop", "id": prop_id},
                        {"type": "scene", "id": scene_ids[0]},
                    ],
                    suggestion="If this prop should appear in multiple scenes, add it to the others.",
                ))

        return issues

    def _check_scene_order(
        self, scenes: list[dict[str, Any]]
    ) -> list[ContinuityIssue]:
        """Check that scene numbers are in sequential order."""
        issues: list[ContinuityIssue] = []

        scene_numbers: list[tuple[str, int]] = []
        for scene in scenes:
            scene_num = self._get_nested(scene, "meta.scene_number")
            if scene_num:
                scene_numbers.append((str(scene_num), scene.get("id")))

        scene_numbers.sort(key=lambda x: self._scene_num_key(x[0]))

        for i in range(1, len(scene_numbers)):
            prev_num, prev_id = scene_numbers[i - 1]
            curr_num, curr_id = scene_numbers[i]

            prev_num_int = self._extract_scene_number(prev_num)
            curr_num_int = self._extract_scene_number(curr_num)

            if prev_num_int and curr_num_int and curr_num_int > prev_num_int + 1:
                gap = curr_num_int - prev_num_int - 1
                issues.append(ContinuityIssue(
                    severity="warning",
                    category="timeline",
                    description=f"Scene number gap: {prev_num} to {curr_num} ({gap} missing scene(s))",
                    entities=[
                        {"type": "scene", "id": prev_id},
                        {"type": "scene", "id": curr_id},
                    ],
                    suggestion=f"Check if {gap} scene(s) are missing between scenes {prev_num} and {curr_num}.",
                ))

        return issues

    @staticmethod
    def _scene_num_key(num: str) -> tuple:
        """Sort key for scene numbers like '12a', '12.1', '12b'."""
        match = re.match(r"(\d+)([a-z]?)\.?(\d*)", num.strip())
        if match:
            major = int(match.group(1))
            minor_letter = ord(match.group(2)) if match.group(2) else 0
            minor_num = int(match.group(3)) if match.group(3) else 0
            return (major, minor_letter, minor_num)
        return (999999, 0, 0)

    @staticmethod
    def _extract_scene_number(num: str) -> Optional[int]:
        """Extract the numeric part of a scene number."""
        match = re.match(r"(\d+)", num.strip())
        return int(match.group(1)) if match else None

    def _check_relationship_consistency(
        self, scenes: list[dict[str, Any]]
    ) -> list[ContinuityIssue]:
        """Check for character relationship conflicts across scenes."""
        issues: list[ContinuityIssue] = []

        # Fetch character relationships
        characters = self._cached_get(
            "characters_all",
            lambda: self._get("characters"),
        )

        char_rels: dict[int, dict[str, Any]] = {}
        for char in characters:
            char_id = char.get("id")
            rels = self._get_nested(char, "meta.relationships") or []
            char_rels[char_id] = {
                "name": char.get("title", {}).get("rendered", ""),
                "relationships": rels,
            }

        # Check co-occurring characters for relationship consistency
        scene_char_pairs: dict[tuple[int, int], list[int]] = defaultdict(list)
        for scene in scenes:
            scene_id = scene.get("id")
            char_refs = self._get_nested(scene, "meta.characters") or []
            char_ids = [
                c if isinstance(c, int) else c.get("id")
                for c in char_refs
            ]
            for i in range(len(char_ids)):
                for j in range(i + 1, len(char_ids)):
                    pair = (min(char_ids[i], char_ids[j]), max(char_ids[i], char_ids[j]))
                    scene_char_pairs[pair].append(scene_id)

        for (char_a, char_b), scene_ids in scene_char_pairs.items():
            rel_a = char_rels.get(char_a, {}).get("relationships", [])
            rel_b = char_rels.get(char_b, {}).get("relationships", [])

            # Check if relationship is defined in both directions
            rel_b_to_a = any(
                r.get("target") == char_b and r.get("type") in ("hostile", "antagonist", "enemy")
                for r in rel_a
            )
            rel_a_to_b = any(
                r.get("target") == char_a and r.get("type") in ("friendly", "ally", "friend")
                for r in rel_b
            )

            if rel_b_to_a and rel_a_to_b and len(scene_ids) > 2:
                issues.append(ContinuityIssue(
                    severity="warning",
                    category="character",
                    description=f"Characters {char_a} and {char_b} have conflicting relationship definitions but appear together in {len(scene_ids)} scenes",
                    entities=[
                        {"type": "character", "id": char_a},
                        {"type": "character", "id": char_b},
                    ],
                    suggestion="Review and reconcile the relationship between these characters.",
                ))

        return issues

    def _check_content_completeness(
        self, scenes: list[dict[str, Any]]
    ) -> list[ContinuityIssue]:
        """Check for scenes with minimal or missing content."""
        issues: list[ContinuityIssue] = []

        for scene in scenes:
            scene_id = scene.get("id")
            summary = self._get_nested(scene, "meta.summary") or ""
            description = self._get_nested(scene, "meta.description") or ""
            characters = self._get_nested(scene, "meta.characters") or []
            location = self._get_nested(scene, "meta.location")

            content_length = len(summary.strip()) + len(description.strip())

            if content_length < 20:
                issues.append(ContinuityIssue(
                    severity="warning",
                    category="scene",
                    description=f"Scene {scene_id} has minimal content ({content_length} characters)",
                    entities=[{"type": "scene", "id": scene_id}],
                    suggestion="Add a summary or description to this scene.",
                ))

            if not characters:
                issues.append(ContinuityIssue(
                    severity="info",
                    category="scene",
                    description=f"Scene {scene_id} has no characters assigned",
                    entities=[{"type": "scene", "id": scene_id}],
                    suggestion="Assign characters to this scene or mark it as background/atmosphere.",
                ))

            if not location:
                issues.append(ContinuityIssue(
                    severity="info",
                    category="scene",
                    description=f"Scene {scene_id} has no location assigned",
                    entities=[{"type": "scene", "id": scene_id}],
                    suggestion="Assign a location to this scene for continuity tracking.",
                ))

        return issues

    # ── Relationship Analytics ────────────────────────────────────────────

    def compute_relationship_graph(
        self,
        scene_ids: Optional[list[int]] = None,
    ) -> list[RelationshipEdge]:
        """Build a relationship graph from Story Graph entities.

        Returns:
            List of RelationshipEdge representing all discovered relationships.
        """
        edges: list[RelationshipEdge] = []

        # Fetch scenes
        scenes = self._cached_get("scenes_all", lambda: self._get("scenes"))
        if scene_ids:
            scenes = [s for s in scenes if s.get("id") in scene_ids]

        # Fetch characters, locations, props
        characters = self._cached_get("characters_all", lambda: self._get("characters"))
        locations = self._cached_get("locations_all", lambda: self._get("locations"))
        props = self._cached_get("props_all", lambda: self._get("props"))

        char_map = {c.get("id"): c.get("title", {}).get("rendered", "") for c in characters}
        loc_map = {l.get("id"): l.get("title", {}).get("rendered", "") for l in locations}
        prop_map = {p.get("id"): p.get("title", {}).get("rendered", "") for p in props}

        # Character -> Scene edges (appears_in)
        for scene in scenes:
            scene_id = scene.get("id")
            scene_title = scene.get("title", {}).get("rendered", f"Scene {scene_id}")
            char_refs = self._get_nested(scene, "meta.characters") or []
            for char_ref in char_refs:
                char_id = char_ref if isinstance(char_ref, int) else char_ref.get("id")
                edges.append(RelationshipEdge(
                    source_type="character",
                    source_id=char_id,
                    source_name=char_map.get(char_id, f"Character {char_id}"),
                    target_type="scene",
                    target_id=scene_id,
                    target_name=scene_title,
                    relation_type="appears_in",
                ))

        # Scene -> Location edges (located_in)
        for scene in scenes:
            scene_id = scene.get("id")
            scene_title = scene.get("title", {}).get("rendered", f"Scene {scene_id}")
            location_id = self._get_nested(scene, "meta.location")
            if location_id:
                edges.append(RelationshipEdge(
                    source_type="scene",
                    source_id=scene_id,
                    source_name=scene_title,
                    target_type="location",
                    target_id=location_id,
                    target_name=loc_map.get(location_id, f"Location {location_id}"),
                    relation_type="located_in",
                ))

        # Scene -> Prop edges (uses)
        for scene in scenes:
            scene_id = scene.get("id")
            scene_title = scene.get("title", {}).get("rendered", f"Scene {scene_id}")
            prop_refs = self._get_nested(scene, "meta.props") or []
            for prop_ref in prop_refs:
                prop_id = prop_ref if isinstance(prop_ref, int) else prop_ref.get("id")
                edges.append(RelationshipEdge(
                    source_type="scene",
                    source_id=scene_id,
                    source_name=scene_title,
                    target_type="prop",
                    target_id=prop_id,
                    target_name=prop_map.get(prop_id, f"Prop {prop_id}"),
                    relation_type="uses",
                ))

        # Character -> Character edges (from relationship metadata)
        for char in characters:
            char_id = char.get("id")
            char_name = char.get("title", {}).get("rendered", "")
            rels = self._get_nested(char, "meta.relationships") or []
            for rel in rels:
                target_id = rel.get("target")
                rel_type = rel.get("type", "related_to")
                if target_id and target_id in char_map:
                    edges.append(RelationshipEdge(
                        source_type="character",
                        source_id=char_id,
                        source_name=char_name,
                        target_type="character",
                        target_id=target_id,
                        target_name=char_map[target_id],
                        relation_type=rel_type,
                        strength=rel.get("strength", 1.0),
                    ))

        return edges

    def compute_character_analytics(
        self,
        character_id: Optional[int] = None,
        scene_ids: Optional[list[int]] = None,
    ) -> list[CharacterAnalytics]:
        """Compute detailed analytics for characters.

        Args:
            character_id: Limit to a specific character. None = all.
            scene_ids: Limit to specific scenes.

        Returns:
            List of CharacterAnalytics.
        """
        characters = self._cached_get("characters_all", lambda: self._get("characters"))
        scenes = self._cached_get("scenes_all", lambda: self._get("scenes"))

        if scene_ids:
            scenes = [s for s in scenes if s.get("id") in scene_ids]

        # Build scene lookup
        scene_map = {s.get("id"): s for s in scenes}

        # Build character -> co-occurrence map
        char_scenes: dict[int, list[int]] = defaultdict(list)
        for scene in scenes:
            scene_id = scene.get("id")
            char_refs = self._get_nested(scene, "meta.characters") or []
            for char_ref in char_refs:
                char_id = char_ref if isinstance(char_ref, int) else char_ref.get("id")
                char_scenes[char_id].append(scene_id)

        # Build co-occurrence counts
        co_occurrence_pairs: Counter = Counter()
        for scene_id, char_ids in char_scenes.items():
            for i in range(len(char_ids)):
                for j in range(i + 1, len(char_ids)):
                    pair = (min(char_ids[i], char_ids[j]), max(char_ids[i], char_ids[j]))
                    co_occurrence_pairs[pair] += 1

        # Build character -> location and prop maps
        char_locations: dict[int, dict[str, int]] = defaultdict(Counter)
        char_props: dict[int, dict[str, int]] = defaultdict(Counter)

        for scene in scenes:
            scene_id = scene.get("id")
            location_id = self._get_nested(scene, "meta.location")
            char_refs = self._get_nested(scene, "meta.characters") or []
            prop_refs = self._get_nested(scene, "meta.props") or []

            for char_ref in char_refs:
                char_id = char_ref if isinstance(char_ref, int) else char_ref.get("id")
                if location_id:
                    char_locations[char_id][str(location_id)] += 1
                for prop_ref in prop_refs:
                    prop_id = prop_ref if isinstance(prop_ref, int) else prop_ref.get("id")
                    char_props[char_id][str(prop_id)] += 1

        # Build character name map
        char_names = {c.get("id"): c.get("title", {}).get("rendered", "") for c in characters}

        # Build co-occurrence name map
        char_names_full = self._cached_get(
            "characters_full",
            lambda: {c.get("id"): c.get("title", {}).get("rendered", "") for c in self._get("characters")},
        )

        results: list[CharacterAnalytics] = []
        target_ids = {character_id} if character_id else set(char_scenes.keys())

        for char_id in target_ids:
            if char_id not in char_scenes:
                continue

            scene_ids_list = char_scenes[char_id]
            scenes_data = [
                {"id": sid, "title": scene_map.get(sid, {}).get("title", {}).get("rendered", "")}
                for sid in scene_ids_list
                if sid in scene_map
            ]

            # Co-occurrences with other characters
            co_occs: dict[str, int] = {}
            for (a, b), count in co_occurrence_pairs.items():
                if a == char_id:
                    co_occs[str(b)] = count
                elif b == char_id:
                    co_occs[str(a)] = count

            results.append(CharacterAnalytics(
                character_id=char_id,
                name=char_names.get(char_id, f"Character {char_id}"),
                scene_count=len(scene_ids_list),
                scenes=scenes_data,
                co_occurrences=co_occs,
                locations=dict(char_locations.get(char_id, {})),
                props_used=dict(char_props.get(char_id, {})),
                relationship_edges=[],  # populated below
            ))

        # Populate relationship edges from character relationship metadata
        for char in characters:
            char_id = char.get("id")
            rels = self._get_nested(char, "meta.relationships") or []
            for rel in rels:
                target_id = rel.get("target")
                if target_id in target_ids:
                    edge_data = {
                        "source": char_id,
                        "target": target_id,
                        "type": rel.get("type", "related_to"),
                        "strength": rel.get("strength", 1.0),
                    }
                    for ra in results:
                        if ra.character_id == char_id:
                            ra.relationship_edges.append(edge_data)

        return results

    def compute_graph_analytics(self) -> GraphAnalytics:
        """Compute overall Story Graph analytics.

        Returns:
            GraphAnalytics with aggregate statistics.
        """
        # Fetch all entity types
        all_entities: dict[str, list[dict[str, Any]]] = {}
        entity_counts: dict[str, int] = {}

        for entity_type in self._index_fields.keys():
            items = self._cached_get(
                f"{entity_type}_all",
                lambda et=entity_type: self._get(et),
            )
            all_entities[entity_type] = items
            entity_counts[entity_type] = len(items)

        total_entities = sum(entity_counts.values())

        # Build relationship graph
        edges = self.compute_relationship_graph()
        total_relationships = len(edges)

        # Compute character analytics
        character_analytics = self.compute_character_analytics()

        # Find most connected entity
        edge_counts: Counter = Counter()
        for edge in edges:
            edge_counts[(edge.source_type, edge.source_id)] += 1
            edge_counts[(edge.target_type, edge.target_id)] += 1

        most_connected_key = edge_counts.most_common(1)
        most_connected = {}
        if most_connected_key:
            (etype, eid), count = most_connected_key[0]
            most_connected = {
                "entity_type": etype,
                "entity_id": eid,
                "edge_count": count,
            }

        # Find isolated entities (no relationships)
        connected_ids = set()
        for edge in edges:
            connected_ids.add((edge.source_type, edge.source_id))
            connected_ids.add((edge.target_type, edge.target_id))

        isolated: list[dict[str, Any]] = []
        for etype, items in all_entities.items():
            for item in items:
                eid = item.get("id")
                if (etype, eid) not in connected_ids:
                    isolated.append({
                        "entity_type": etype,
                        "entity_id": eid,
                        "title": item.get("title", {}).get("rendered", ""),
                    })

        # Compute graph density: E / (V * (V-1)) for undirected
        max_edges = total_entities * (total_entities - 1) if total_entities > 1 else 1
        density = total_relationships / max_edges if max_edges > 0 else 0.0

        return GraphAnalytics(
            total_entities=total_entities,
            entity_counts=entity_counts,
            total_relationships=total_relationships,
            character_network=character_analytics,
            density=round(density, 6),
            most_connected=most_connected,
            isolated_entities=isolated,
        )

    def get_character_network_summary(self) -> dict[str, Any]:
        """Get a summary of the character relationship network.

        Returns:
            Dict with network statistics and key relationships.
        """
        characters = self._cached_get("characters_all", lambda: self._get("characters"))
        scenes = self._cached_get("scenes_all", lambda: self._get("scenes"))

        # Build co-occurrence matrix
        char_scenes: dict[int, list[int]] = defaultdict(list)
        for scene in scenes:
            scene_id = scene.get("id")
            char_refs = self._get_nested(scene, "meta.characters") or []
            for char_ref in char_refs:
                char_id = char_ref if isinstance(char_ref, int) else char_ref.get("id")
                char_scenes[char_id].append(scene_id)

        # Co-occurrence counts
        co_occurrences: dict[str, dict[str, int]] = defaultdict(dict)
        for scene_id, char_ids in char_scenes.items():
            for i in range(len(char_ids)):
                for j in range(i + 1, len(char_ids)):
                    a, b = char_ids[i], char_ids[j]
                    name_a = next(
                        (c.get("title", {}).get("rendered", "") for c in characters if c.get("id") == a),
                        f"Character {a}",
                    )
                    name_b = next(
                        (c.get("title", {}).get("rendered", "") for c in characters if c.get("id") == b),
                        f"Character {b}",
                    )
                    co_occurrences[str(a)][str(b)] = co_occurrences[str(a)].get(str(b), 0) + 1
                    co_occurrences[str(b)][str(a)] = co_occurrences[str(b)].get(str(a), 0) + 1

        # Find strongest relationships
        strong_pairs: list[dict[str, Any]] = []
        for char_a, partners in co_occurrences.items():
            for char_b, count in partners.items():
                if char_a < char_b:  # Avoid duplicates
                    name_a = next(
                        (c.get("title", {}).get("rendered", "") for c in characters if c.get("id") == int(char_a)),
                        f"Character {char_a}",
                    )
                    name_b = next(
                        (c.get("title", {}).get("rendered", "") for c in characters if c.get("id") == int(char_b)),
                        f"Character {char_b}",
                    )
                    strong_pairs.append({
                        "character_a": char_a,
                        "name_a": name_a,
                        "character_b": char_b,
                        "name_b": name_b,
                        "co_occurrences": count,
                    })

        strong_pairs.sort(key=lambda p: p["co_occurrences"], reverse=True)

        # Character scene presence
        scene_presence = [
            {
                "character_id": c.get("id"),
                "name": c.get("title", {}).get("rendered", ""),
                "scene_count": len(char_scenes.get(c.get("id"), [])),
            }
            for c in characters
        ]
        scene_presence.sort(key=lambda s: s["scene_count"], reverse=True)

        return {
            "total_characters": len(characters),
            "total_scenes": len(scenes),
            "strongest_relationships": strong_pairs[:10],
            "scene_presence": scene_presence,
            "co_occurrence_matrix": dict(co_occurrences),
        }

    def clear_cache(self):
        """Clear the internal cache."""
        self._cache.clear()
