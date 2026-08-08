"""Tests for Story Graph Intelligence — semantic search, continuity validation, and relationship analytics."""

import math
import pytest
from unittest.mock import patch, MagicMock, Mock
from collections import defaultdict

from story_intelligence import (
    DummyEmbeddingBackend,
    OllamaEmbeddingBackend,
    SentenceTransformerBackend,
    cosine_similarity,
    euclidean_distance,
    SearchResult,
    ContinuityIssue,
    RelationshipEdge,
    CharacterAnalytics,
    GraphAnalytics,
    StoryGraphIntelligence,
)


# ── Embedding Backend Tests ─────────────────────────────────────────────────

class TestDummyEmbeddingBackend:
    """Tests for the deterministic hash-based embedding backend."""

    def test_encode_single_text(self):
        backend = DummyEmbeddingBackend()
        vectors = backend.encode(["hello world"])
        assert len(vectors) == 1
        assert len(vectors[0]) == 128  # default dimension

    def test_encode_multiple_texts(self):
        backend = DummyEmbeddingBackend()
        vectors = backend.encode(["hello", "world", "test"])
        assert len(vectors) == 3
        for v in vectors:
            assert len(v) == 128

    def test_deterministic(self):
        """Same text should produce same embedding."""
        backend = DummyEmbeddingBackend()
        v1 = backend.encode(["test text"])[0]
        v2 = backend.encode(["test text"])[0]
        assert v1 == v2

    def test_different_texts_different_embeddings(self):
        """Different texts should produce different embeddings."""
        backend = DummyEmbeddingBackend()
        v1 = backend.encode(["hello"])[0]
        v2 = backend.encode(["world"])[0]
        assert v1 != v2

    def test_normalized(self):
        """Embeddings should be normalized (unit length)."""
        backend = DummyEmbeddingBackend()
        vectors = backend.encode(["test text", "another text"])
        for v in vectors:
            norm = math.sqrt(sum(x * x for x in v))
            assert abs(norm - 1.0) < 0.001

    def test_custom_dimension(self):
        backend = DummyEmbeddingBackend(dimension=256)
        vectors = backend.encode(["test"])
        assert len(vectors[0]) == 256

    def test_dimension_method(self):
        backend = DummyEmbeddingBackend(dimension=64)
        assert backend.dimension() == 64

    def test_empty_input(self):
        backend = DummyEmbeddingBackend()
        vectors = backend.encode([])
        assert vectors == []


class TestOllamaEmbeddingBackend:
    """Tests for the Ollama embedding backend."""

    def test_encode_with_mock(self):
        """Test encoding with mocked Ollama API."""
        backend = OllamaEmbeddingBackend(url="http://test:11434", model="test-model")

        with patch("story_intelligence.requests.post") as mock_post:
            mock_response = MagicMock()
            mock_response.json.return_value = {"embeddings": [[0.1] * 768]}
            mock_response.raise_for_status = MagicMock()
            mock_post.return_value = mock_response

            vectors = backend.encode(["test text"])
            assert len(vectors) == 1
            assert len(vectors[0]) == 768

    def test_empty_input(self):
        backend = OllamaEmbeddingBackend()
        vectors = backend.encode([])
        assert vectors == []

    def test_dimension(self):
        backend = OllamaEmbeddingBackend()
        assert backend.dimension() == 768

    def test_api_error_fallback(self):
        """Should return zero vector on API error."""
        backend = OllamaEmbeddingBackend()

        with patch("story_intelligence.requests.post") as mock_post:
            mock_post.side_effect = Exception("Connection refused")

            vectors = backend.encode(["test text"])
            assert len(vectors) == 1
            assert all(v == 0.0 for v in vectors[0])


class TestSentenceTransformerBackend:
    """Tests for the sentence-transformers backend."""

    def test_import_error(self):
        """Should raise helpful error when library not installed."""
        backend = SentenceTransformerBackend()

        with patch.dict("sys.modules", {"sentence_transformers": None}):
            with pytest.raises(ImportError, match="sentence-transformers is required"):
                backend.encode(["test"])

    def test_dimension(self):
        backend = SentenceTransformerBackend()
        assert backend.dimension() == 384


# ── Similarity Function Tests ───────────────────────────────────────────────

class TestSimilarityFunctions:
    """Tests for cosine similarity and Euclidean distance."""

    def test_cosine_similarity_identical(self):
        a = [1.0, 0.0, 0.0]
        b = [1.0, 0.0, 0.0]
        assert cosine_similarity(a, b) == pytest.approx(1.0)

    def test_cosine_similarity_orthogonal(self):
        a = [1.0, 0.0]
        b = [0.0, 1.0]
        assert cosine_similarity(a, b) == pytest.approx(0.0)

    def test_cosine_similarity_opposite(self):
        a = [1.0, 0.0]
        b = [-1.0, 0.0]
        assert cosine_similarity(a, b) == pytest.approx(-1.0)

    def test_cosine_similarity_same_direction_different_magnitude(self):
        a = [1.0, 1.0]
        b = [2.0, 2.0]
        assert cosine_similarity(a, b) == pytest.approx(1.0)

    def test_cosine_similarity_dimension_mismatch(self):
        with pytest.raises(ValueError, match="dimension mismatch"):
            cosine_similarity([1.0, 2.0], [1.0, 2.0, 3.0])

    def test_euclidean_distance_identical(self):
        a = [1.0, 2.0, 3.0]
        b = [1.0, 2.0, 3.0]
        assert euclidean_distance(a, b) == pytest.approx(0.0)

    def test_euclidean_distance_different(self):
        a = [0.0, 0.0]
        b = [3.0, 4.0]
        assert euclidean_distance(a, b) == pytest.approx(5.0)

    def test_euclidean_distance_dimension_mismatch(self):
        with pytest.raises(ValueError, match="dimension mismatch"):
            euclidean_distance([1.0, 2.0], [1.0])


# ── StoryGraphIntelligence Tests ────────────────────────────────────────────

class TestStoryGraphIntelligence:
    """Tests for the main intelligence engine."""

    @pytest.fixture
    def intelligence(self):
        """Create intelligence instance with dummy backend."""
        backend = DummyEmbeddingBackend()
        return StoryGraphIntelligence(
            wordpress_url="http://test:80",
            username="test_user",
            app_password="test_pass",
            embedding_backend=backend,
        )

    @pytest.fixture
    def mock_wp_response(self, monkeypatch):
        """Mock WordPress API responses."""
        mock_response = MagicMock()
        mock_response.json.return_value = []
        mock_response.status_code = 200

        def fake_get(*args, **kwargs):
            return mock_response

        monkeypatch.setattr("requests.get", fake_get)
        return mock_response

    def test_index_entities(self, intelligence, mock_wp_response):
        """Test indexing entities from WordPress."""
        # Mock a scene entity
        mock_wp_response.json.return_value = [
            {
                "id": 1,
                "title": {"rendered": "Scene 1 - Introduction"},
                "content": {"rendered": "John enters the room."},
                "type": "scene",
                "meta": {"scene_number": "1"},
            }
        ]

        result = intelligence.index_entities(entity_types=["scenes"])

        assert "indexed_at" in result
        assert "scenes" in result["entity_types"]
        assert result["total_entries"] >= 1

    def test_index_entities_empty(self, intelligence, mock_wp_response):
        """Test indexing when no entities exist."""
        mock_wp_response.json.return_value = []

        result = intelligence.index_entities(entity_types=["scenes"])

        assert result["total_entries"] == 0

    def test_semantic_search(self, intelligence, mock_wp_response):
        """Test semantic search functionality."""
        mock_wp_response.json.return_value = [
            {
                "id": 1,
                "title": {"rendered": "Action Scene"},
                "content": {"rendered": "A high-speed car chase through the city."},
                "type": "scene",
            },
            {
                "id": 2,
                "title": {"rendered": "Romantic Scene"},
                "content": {"rendered": "A quiet dinner between two lovers."},
                "type": "scene",
            },
        ]

        results = intelligence.semantic_search(query="fast cars", entity_types=["scenes"], top_k=2)

        assert len(results) <= 2
        for r in results:
            assert isinstance(r, SearchResult)
            assert r.entity_type == "scenes"
            assert r.score >= 0

    def test_semantic_search_empty(self, intelligence, mock_wp_response):
        """Test semantic search with no entities."""
        mock_wp_response.json.return_value = []

        results = intelligence.semantic_search(query="test", entity_types=["scenes"], top_k=5)

        assert results == []

    def test_fuzzy_search(self, intelligence, mock_wp_response):
        """Test keyword-based fuzzy search."""
        mock_wp_response.json.return_value = [
            {
                "id": 1,
                "title": {"rendered": "Car Chase Scene"},
                "content": {"rendered": "High speed pursuit."},
                "type": "scene",
            }
        ]

        results = intelligence.fuzzy_search(query="car chase", entity_types=["scenes"], top_k=5)

        assert len(results) >= 1
        assert results[0]["entity_id"] == 1
        assert results[0]["title"] == "Car Chase Scene"

    def test_hybrid_search(self, intelligence, mock_wp_response):
        """Test hybrid semantic + keyword search."""
        mock_wp_response.json.return_value = [
            {
                "id": 1,
                "title": {"rendered": "Car Chase"},
                "content": {"rendered": "Fast cars racing."},
                "type": "scene",
            }
        ]

        results = intelligence.hybrid_search(query="car chase", entity_types=["scenes"], top_k=5)

        assert len(results) >= 1

    def test_clear_cache(self, intelligence):
        """Test cache clearing."""
        # Populate cache
        intelligence._cache["test_key"] = "test_value"
        assert "test_key" in intelligence._cache

        intelligence.clear_cache()
        assert intelligence._cache == {}

    def test_get_entity_text(self, intelligence):
        """Test text extraction from entity."""
        entity = {
            "title": {"rendered": "Test Title"},
            "content": {"rendered": "Test Content"},
        }

        text = intelligence._get_entity_text(entity)
        assert "Test Title" in text
        assert "Test Content" in text

    def test_get_entity_text_flat(self, intelligence):
        """Test text extraction from flat entity."""
        entity = {"title": "Simple Title", "content": "Simple Content"}

        text = intelligence._get_entity_text(entity)
        assert "Simple Title" in text
        assert "Simple Content" in text

    def test_get_entity_text_empty(self, intelligence):
        """Test text extraction from empty entity."""
        entity = {}
        text = intelligence._get_entity_text(entity)
        assert text == ""

    def test_scene_num_key(self):
        """Test scene number parsing for sorting."""
        intelligence = StoryGraphIntelligence(
            wordpress_url="http://test:80",
            username="test",
            app_password="test",
            embedding_backend=DummyEmbeddingBackend(),
        )

        # Test various scene number formats
        assert intelligence._scene_num_key("1") < intelligence._scene_num_key("2")
        assert intelligence._scene_num_key("10") > intelligence._scene_num_key("9")
        assert intelligence._scene_num_key("1a") < intelligence._scene_num_key("1b")
        assert intelligence._scene_num_key("12.1") < intelligence._scene_num_key("12.2")

    def test_index_with_cache(self, intelligence, mock_wp_response):
        """Test that indexing populates the cache."""
        mock_wp_response.json.return_value = [
            {
                "id": 1,
                "title": {"rendered": "Scene 1"},
                "content": {"rendered": "Test content"},
                "type": "scene",
            }
        ]

        intelligence.index_entities(entity_types=["scenes"])

        # Cache should have been populated
        assert len(intelligence._cache) > 0

    def test_semantic_search_uses_cache(self, intelligence, mock_wp_response):
        """Test that search uses cached embeddings."""
        mock_wp_response.json.return_value = [
            {
                "id": 1,
                "title": {"rendered": "Scene 1"},
                "content": {"rendered": "Test content"},
                "type": "scene",
            }
        ]

        # First call - populates cache
        intelligence.index_entities(entity_types=["scenes"])

        # Second call - should use cache
        results = intelligence.semantic_search(query="test", entity_types=["scenes"], top_k=5)

        assert isinstance(results, list)


class TestContinuityValidation:
    """Tests for continuity validation functionality."""

    @pytest.fixture
    def intelligence(self):
        backend = DummyEmbeddingBackend()
        return StoryGraphIntelligence(
            wordpress_url="http://test:80",
            username="test_user",
            app_password="test_pass",
            embedding_backend=backend,
        )

    def _make_scene(self, scene_id, scene_num, characters=None, location_id=None, props=None):
        """Helper to create scene mock data."""
        scene = {
            "id": scene_id,
            "title": {"rendered": f"Scene {scene_num}"},
            "content": {"rendered": f"Content of scene {scene_num}"},
            "type": "scene",
            "meta": {
                "scene_number": str(scene_num),
                "characters": characters or [],
                "location_id": location_id or 0,
                "props": props or [],
            },
        }
        return scene

    def _make_character(self, char_id, name):
        """Helper to create character mock data."""
        return {
            "id": char_id,
            "title": {"rendered": name},
            "content": {"rendered": f"Description of {name}"},
            "type": "character",
            "meta": {},
        }

    def test_validate_continuity_no_scenes(self, intelligence, monkeypatch):
        """Test validation with no scenes."""
        mock_response = MagicMock()
        mock_response.json.return_value = []
        mock_response.status_code = 200
        monkeypatch.setattr("requests.get", MagicMock(return_value=mock_response))

        issues = intelligence.validate_continuity(episode_id=1)
        assert issues == []

    def test_validate_continuity_character_appearances(self, intelligence, monkeypatch):
        """Test character appearance validation."""
        scenes = [
            self._make_scene(1, "1", characters=[1, 2]),
            self._make_scene(2, "2", characters=[1]),
        ]

        characters = [
            self._make_character(1, "John"),
            self._make_character(2, "Jane"),
        ]

        def fake_get(url, **kwargs):
            mock = MagicMock()
            if "scene" in url:
                mock.json.return_value = scenes
            elif "character" in url:
                mock.json.return_value = characters
            else:
                mock.json.return_value = []
            mock.status_code = 200
            return mock

        monkeypatch.setattr("requests.get", fake_get)

        issues = intelligence.validate_continuity(episode_id=1)

        # Should have at least some validation results
        assert isinstance(issues, list)

    def test_validate_continuity_location_consistency(self, intelligence, monkeypatch):
        """Test location consistency validation."""
        scenes = [
            self._make_scene(1, "1", location_id=10),
            self._make_scene(2, "2", location_id=10),
        ]

        def fake_get(url, **kwargs):
            mock = MagicMock()
            if "scene" in url:
                mock.json.return_value = scenes
            else:
                mock.json.return_value = []
            mock.status_code = 200
            return mock

        monkeypatch.setattr("requests.get", fake_get)

        issues = intelligence.validate_continuity(episode_id=1)
        assert isinstance(issues, list)

    def test_validate_continuity_scene_ordering(self, intelligence, monkeypatch):
        """Test scene ordering validation."""
        scenes = [
            self._make_scene(1, "3"),
            self._make_scene(2, "1"),  # Out of order
            self._make_scene(3, "2"),
        ]

        def fake_get(url, **kwargs):
            mock = MagicMock()
            if "scene" in url:
                mock.json.return_value = scenes
            else:
                mock.json.return_value = []
            mock.status_code = 200
            return mock

        monkeypatch.setattr("requests.get", fake_get)

        issues = intelligence.validate_continuity(episode_id=1)
        assert isinstance(issues, list)

    def test_validate_continuity_with_scene_ids(self, intelligence, monkeypatch):
        """Test validation with specific scene IDs."""
        scenes = [
            self._make_scene(1, "1"),
            self._make_scene(2, "2"),
        ]

        def fake_get(url, **kwargs):
            mock = MagicMock()
            if "scene" in url:
                mock.json.return_value = scenes
            else:
                mock.json.return_value = []
            mock.status_code = 200
            return mock

        monkeypatch.setattr("requests.get", fake_get)

        issues = intelligence.validate_continuity(episode_id=1, scene_ids=[1])

        assert isinstance(issues, list)


class TestRelationshipAnalytics:
    """Tests for relationship analytics functionality."""

    @pytest.fixture
    def intelligence(self):
        backend = DummyEmbeddingBackend()
        return StoryGraphIntelligence(
            wordpress_url="http://test:80",
            username="test_user",
            app_password="test_pass",
            embedding_backend=backend,
        )

    def _make_scene(self, scene_id, scene_num, characters=None, location_id=None, props=None):
        scene = {
            "id": scene_id,
            "title": {"rendered": f"Scene {scene_num}"},
            "content": {"rendered": f"Content of scene {scene_num}"},
            "type": "scene",
            "meta": {
                "scene_number": str(scene_num),
                "characters": characters or [],
                "location_id": location_id or 0,
                "props": props or [],
            },
        }
        return scene

    def _make_character(self, char_id, name):
        return {
            "id": char_id,
            "title": {"rendered": name},
            "content": {"rendered": f"Description of {name}"},
            "type": "character",
            "meta": {},
        }

    def test_compute_relationship_graph(self, intelligence, monkeypatch):
        """Test relationship graph computation."""
        scenes = [
            self._make_scene(1, "1", characters=[1, 2], location_id=10, props=[20]),
            self._make_scene(2, "2", characters=[1, 3], location_id=11, props=[21]),
        ]

        characters = [
            self._make_character(1, "John"),
            self._make_character(2, "Jane"),
            self._make_character(3, "Bob"),
        ]

        def fake_get(url, **kwargs):
            mock = MagicMock()
            if "scene" in url:
                mock.json.return_value = scenes
            elif "character" in url:
                mock.json.return_value = characters
            else:
                mock.json.return_value = []
            mock.status_code = 200
            return mock

        monkeypatch.setattr("requests.get", fake_get)

        edges = intelligence.compute_relationship_graph()

        assert len(edges) > 0
        for edge in edges:
            assert isinstance(edge, RelationshipEdge)

    def test_compute_character_analytics(self, intelligence, monkeypatch):
        """Test per-character analytics."""
        scenes = [
            self._make_scene(1, "1", characters=[1, 2], location_id=10),
            self._make_scene(2, "2", characters=[1, 3], location_id=11),
        ]

        characters = [
            self._make_character(1, "John"),
            self._make_character(2, "Jane"),
            self._make_character(3, "Bob"),
        ]

        def fake_get(url, **kwargs):
            mock = MagicMock()
            if "scene" in url:
                mock.json.return_value = scenes
            elif "character" in url:
                mock.json.return_value = characters
            else:
                mock.json.return_value = []
            mock.status_code = 200
            return mock

        monkeypatch.setattr("requests.get", fake_get)

        analytics = intelligence.compute_character_analytics()

        assert len(analytics) == 3
        for a in analytics:
            assert isinstance(a, CharacterAnalytics)
            assert a.character_id in [1, 2, 3]

    def test_compute_character_analytics_single(self, intelligence, monkeypatch):
        """Test analytics for a single character."""
        scenes = [
            self._make_scene(1, "1", characters=[1, 2]),
            self._make_scene(2, "2", characters=[1, 3]),
        ]

        characters = [
            self._make_character(1, "John"),
            self._make_character(2, "Jane"),
        ]

        def fake_get(url, **kwargs):
            mock = MagicMock()
            if "scene" in url:
                mock.json.return_value = scenes
            elif "character" in url:
                mock.json.return_value = characters
            else:
                mock.json.return_value = []
            mock.status_code = 200
            return mock

        monkeypatch.setattr("requests.get", fake_get)

        analytics = intelligence.compute_character_analytics(character_id=1)

        assert len(analytics) == 1
        assert analytics[0].character_id == 1
        assert analytics[0].scene_count == 2

    def test_compute_graph_analytics(self, intelligence, monkeypatch):
        """Test aggregate graph analytics."""
        scenes = [
            self._make_scene(1, "1", characters=[1, 2], location_id=10),
            self._make_scene(2, "2", characters=[1, 3], location_id=11),
        ]

        characters = [
            self._make_character(1, "John"),
            self._make_character(2, "Jane"),
            self._make_character(3, "Bob"),
        ]

        locations = [
            {"id": 10, "title": {"rendered": "Office"}, "type": "location"},
            {"id": 11, "title": {"rendered": "Park"}, "type": "location"},
        ]

        def fake_get(url, **kwargs):
            mock = MagicMock()
            if "scene" in url:
                mock.json.return_value = scenes
            elif "character" in url:
                mock.json.return_value = characters
            elif "location" in url:
                mock.json.return_value = locations
            else:
                mock.json.return_value = []
            mock.status_code = 200
            return mock

        monkeypatch.setattr("requests.get", fake_get)

        analytics = intelligence.compute_graph_analytics()

        assert isinstance(analytics, GraphAnalytics)
        assert analytics.total_entities > 0
        assert "scene" in analytics.entity_counts
        assert "character" in analytics.entity_counts

    def test_get_character_network_summary(self, intelligence, monkeypatch):
        """Test character network summary."""
        scenes = [
            self._make_scene(1, "1", characters=[1, 2], location_id=10),
            self._make_scene(2, "2", characters=[1, 3], location_id=11),
        ]

        characters = [
            self._make_character(1, "John"),
            self._make_character(2, "Jane"),
            self._make_character(3, "Bob"),
        ]

        def fake_get(url, **kwargs):
            mock = MagicMock()
            if "scene" in url:
                mock.json.return_value = scenes
            elif "character" in url:
                mock.json.return_value = characters
            else:
                mock.json.return_value = []
            mock.status_code = 200
            return mock

        monkeypatch.setattr("requests.get", fake_get)

        summary = intelligence.get_character_network_summary()

        assert "total_characters" in summary
        assert "total_scenes" in summary
        assert "strongest_relationships" in summary
        assert "scene_presence" in summary
        assert summary["total_characters"] == 3
        assert summary["total_scenes"] == 2


# ── API Endpoint Tests ──────────────────────────────────────────────────────

class TestIntelligenceEndpoints:
    """Tests for Story Graph Intelligence API endpoints."""

    @pytest.fixture
    def mock_intelligence(self, monkeypatch):
        """Mock the intelligence engine."""
        mock = MagicMock()
        mock.index_entities.return_value = {
            "indexed_at": "2024-01-01T00:00:00Z",
            "entity_types": ["scene", "character"],
            "total_entries": 10,
        }
        mock.semantic_search.return_value = [
            SearchResult(
                entity_type="scene",
                entity_id=1,
                title="Test Scene",
                score=0.95,
                snippet="Test content",
            )
        ]
        mock.hybrid_search.return_value = [
            SearchResult(
                entity_type="scene",
                entity_id=1,
                title="Test Scene",
                score=0.90,
                snippet="Test content",
            )
        ]
        mock.validate_continuity.return_value = [
            ContinuityIssue(
                severity="warning",
                category="character",
                description="Test issue",
            )
        ]
        mock.get_character_network_summary.return_value = {
            "total_characters": 5,
            "total_scenes": 10,
            "strongest_relationships": [],
            "scene_presence": [],
        }
        mock.compute_graph_analytics.return_value = GraphAnalytics(
            total_entities=20,
            entity_counts={"scene": 10, "character": 5, "location": 5},
            total_relationships=15,
            density=0.5,
            most_connected=[],
            isolated_entities=[],
        )
        mock.compute_relationship_graph.return_value = [
            RelationshipEdge(
                source_type="character",
                source_id=1,
                source_name="John",
                target_type="scene",
                target_id=1,
                target_name="Scene 1",
                relation_type="appears_in",
                strength=1.0,
            )
        ]
        mock.compute_character_analytics.return_value = [
            CharacterAnalytics(
                character_id=1,
                name="John",
                scene_count=5,
                scenes=[1, 2, 3],
                co_occurrences={2: 3},
                locations=[10, 11],
                props_used=[20],
                relationship_edges=[],
            )
        ]
        mock.clear_cache.return_value = None

        monkeypatch.setattr("app.intelligence", mock)
        return mock

    def test_semantic_search_endpoint(self, mock_intelligence, monkeypatch):
        """Test semantic search API endpoint."""
        from fastapi.testclient import TestClient
        import app as app_module

        client = TestClient(app_module.app)

        resp = client.post("/intelligence/search", json={
            "query": "test query",
            "entity_types": ["scene"],
            "top_k": 5,
            "min_score": 0.5,
            "use_hybrid": False,
        })

        assert resp.status_code == 200
        data = resp.json()
        assert data["query"] == "test query"
        assert data["total"] == 1
        assert "search_time_ms" in data

    def test_hybrid_search_endpoint(self, mock_intelligence, monkeypatch):
        """Test hybrid search API endpoint."""
        from fastapi.testclient import TestClient
        import app as app_module

        client = TestClient(app_module.app)

        resp = client.post("/intelligence/search", json={
            "query": "test query",
            "use_hybrid": True,
        })

        assert resp.status_code == 200
        assert mock_intelligence.hybrid_search.called

    def test_index_entities_endpoint(self, mock_intelligence, monkeypatch):
        """Test index entities API endpoint."""
        from fastapi.testclient import TestClient
        import app as app_module

        client = TestClient(app_module.app)

        resp = client.post("/intelligence/index")

        assert resp.status_code == 200
        data = resp.json()
        assert data["total_entries"] == 10
        assert "scene" in data["entity_types"]

    def test_validate_continuity_endpoint(self, mock_intelligence, monkeypatch):
        """Test continuity validation API endpoint."""
        from fastapi.testclient import TestClient
        import app as app_module

        client = TestClient(app_module.app)

        resp = client.post("/intelligence/validate", json={
            "episode_id": 1,
            "scene_ids": [1, 2, 3],
        })

        assert resp.status_code == 200
        data = resp.json()
        assert data["total_issues"] == 1
        assert data["warnings"] == 1

    def test_character_network_endpoint(self, mock_intelligence, monkeypatch):
        """Test character network API endpoint."""
        from fastapi.testclient import TestClient
        import app as app_module

        client = TestClient(app_module.app)

        resp = client.get("/intelligence/character-network")

        assert resp.status_code == 200
        data = resp.json()
        assert data["total_characters"] == 5
        assert data["total_scenes"] == 10

    def test_graph_analytics_endpoint(self, mock_intelligence, monkeypatch):
        """Test graph analytics API endpoint."""
        from fastapi.testclient import TestClient
        import app as app_module

        client = TestClient(app_module.app)

        resp = client.get("/intelligence/graph-analytics")

        assert resp.status_code == 200
        data = resp.json()
        assert data["total_entities"] == 20
        assert data["density"] == 0.5

    def test_relationship_graph_endpoint(self, mock_intelligence, monkeypatch):
        """Test relationship graph API endpoint."""
        from fastapi.testclient import TestClient
        import app as app_module

        client = TestClient(app_module.app)

        resp = client.get("/intelligence/relationships")

        assert resp.status_code == 200
        data = resp.json()
        assert data["total_edges"] == 1

    def test_relationship_graph_with_scene_filter(self, mock_intelligence, monkeypatch):
        """Test relationship graph with scene filter."""
        from fastapi.testclient import TestClient
        import app as app_module

        client = TestClient(app_module.app)

        resp = client.get("/intelligence/relationships?scene_ids=1,2,3")

        assert resp.status_code == 200

    def test_character_analytics_endpoint(self, mock_intelligence, monkeypatch):
        """Test character analytics API endpoint."""
        from fastapi.testclient import TestClient
        import app as app_module

        client = TestClient(app_module.app)

        resp = client.post("/intelligence/character-analytics", json={
            "character_id": 1,
            "scene_ids": [1, 2],
        })

        assert resp.status_code == 200
        data = resp.json()
        assert data["total_characters"] == 1
        assert data["characters"][0]["name"] == "John"

    def test_clear_cache_endpoint(self, mock_intelligence, monkeypatch):
        """Test cache clearing API endpoint."""
        from fastapi.testclient import TestClient
        import app as app_module

        client = TestClient(app_module.app)

        resp = client.post("/intelligence/cache/clear")

        assert resp.status_code == 200
        assert resp.json()["success"] is True

    def test_search_error_handling(self, mock_intelligence, monkeypatch):
        """Test search error handling."""
        mock_intelligence.semantic_search.side_effect = Exception("Search failed")

        from fastapi.testclient import TestClient
        import app as app_module

        client = TestClient(app_module.app)

        resp = client.post("/intelligence/search", json={
            "query": "test",
        })

        assert resp.status_code == 500
        assert "Search error" in resp.json()["detail"]
