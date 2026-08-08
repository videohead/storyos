# Phase 7: Story Graph Intelligence

> Build Your Once. Create Everywhere.

**Status: ✅ Complete**

## Objective

Transform StoryOS from a content management system into a **narrative intelligence platform** by embedding semantic search, continuity validation, and relationship analytics directly into the WordPress experience.

Story Graph Intelligence makes the Story Graph *queryable by meaning*, not just by taxonomy or keyword. Creators can ask natural-language questions and get structured answers drawn from the full Story Graph.

---

# Guiding Vision

StoryOS already stores all story data as structured entities in WordPress — Characters, Locations, Scenes, Shots, Assets, Props, Projects, Story Worlds. The Story Graph is the canonical source of truth.

Phase 7 adds **intelligence** on top of that structure:

1. **Semantic Search** — Find story entities by meaning, not just keywords. "Show me the mysterious detective character" returns the right character even if the word "mysterious" never appears in their data.
2. **Continuity Validation** — Automatically detect inconsistencies: characters in scenes they don't belong to, props used without being defined, timeline errors, relationship conflicts.
3. **Relationship Analytics** — Visualize and query the network of story relationships: character co-occurrence, location usage patterns, asset lineage.

All of this is exposed through **WordPress-native interfaces**: enhanced search, admin panels, and REST API endpoints.

---

# Architecture

```
┌──────────────────────────────────────────────────────────────────┐
│                    WordPress Admin UI                            │
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │         Enhanced StoryOS Search (WordPress Search)       │   │
│  │  ┌────────────────────────────────────────────────────┐  │   │
│  │  │  Search Input: "mysterious detective in warehouse" │  │   │
│  │  │  ────────────────────────────────────────────────── │  │   │
│  │  │  [Semantic] Character: Mara Quinn (0.92)           │  │   │
│  │  │  [Semantic] Scene: #14 "Warehouse Night" (0.78)    │  │   │
│  │  │  [Keyword]  Location: Old Warehouse (0.65)         │  │   │
│  │  │  [Semantic] Asset: Concept Art - Warehouse (0.54)  │  │   │
│  │  └────────────────────────────────────────────────────┘  │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                  │
│  ┌──────────────────┐  ┌──────────────────┐  ┌──────────────┐  │
│  │ Continuity Panel │  │  Analytics Panel │  │  Graph View  │  │
│  │ (Admin)          │  │  (Admin)         │  │  (Admin)     │  │
│  └──────────────────┘  └──────────────────┘  └──────────────┘  │
└──────────────────────────┬─────────────────────────────────────┘
                           │
              ┌────────────┴────────────┐
              ▼                         ▼
┌─────────────────────────┐  ┌──────────────────────────┐
│  StoryOS WordPress      │  │  StoryOS REST API        │
│  Plugin (PHP)           │  │  (Python Orchestrator)   │
│                         │  │                          │
│  ┌───────────────────┐  │  │  ┌──────────────────┐   │
│  │ StorySearch       │  │  │  │ StoryGraph       │   │
│  │ (WP_Query filter) │  │  │  │ Intelligence     │   │
│  │ - Semantic search │  │  │  │ (Python)         │   │
│  │ - Keyword fallback│  │  │  │ - Semantic       │   │
│  │ - Entity filters  │  │  │   search          │   │
│  └───────────────────┘  │  │  - Continuity      │   │
│                         │  │   validation       │   │
│  ┌───────────────────┐  │  │  - Analytics       │   │
│  │ ContinuityChecker │  │  └────────┬───────────┘   │
│  │ (PHP + Python)    │  │           │               │
│  │ - Auto-check on     │  │  ┌──────┴──────┐       │
│  │   save/update       │  │  │ Embedding   │       │
│  │ - Manual run        │  │  │ Backend     │       │
│  └───────────────────┘  │  │             │       │
│                         │  │  ┌──────────┤       │
│  ┌───────────────────┐  │  │  │ Dummy    │       │
│  │ RelationshipGraph │  │  │  │ (dev)    │       │
│  │ (PHP)             │  │  │  │ Ollama   │       │
│  │ - Co-occurrence   │  │  │  │ Sentence │       │
│  │ - Network viz     │  │  │  │ Trans.   │       │
│  └───────────────────┘  │  │  └──────────┘       │
└─────────────────────────┘  └──────────────────────┘
```

---

## Design Principles

### 1. WordPress-Native First

Search enhancements integrate with WordPress's existing search UI and `WP_Query`. No separate search interface is built — the Story Graph intelligence *enhances* what WordPress already provides.

### 2. Hybrid Search

Semantic search is the primary engine, but keyword fallback ensures reliability when embeddings are unavailable (no GPU, no model server). Results are merged using a weighted combination.

### 3. Embedding Backend Agnostic

The system supports multiple embedding backends:
- **Dummy** — Hash-based deterministic embeddings for development/testing (zero dependencies)
- **Ollama** — Local model server via `/api/embed` endpoint
- **Sentence-Transformers** — CPU/GPU Python library (e.g., `all-MiniLM-L6-v2`)
- **Future**: OpenAI embeddings, Azure OpenAI, custom REST endpoints

### 4. Caching & Performance

Embeddings are cached in WordPress transients (PHP side) and in-memory TTL caches (Python side). Re-indexing is triggered on content changes, not on every search.

### 5. Incremental Indexing

Rather than full re-index on every change, the system tracks which entities have been modified since last index and only re-embeds those.

---

# Components

## 1. WordPress Search Enhancement (PHP)

### 1a. `StorySearch` — WP_Query Filter

A PHP class that hooks into WordPress search via `pre_get_posts` and `posts_where` filters to enhance the default search behavior.

**File**: `wordpress/wp-content/plugins/storyos/includes/utils/story-search.php`

**Responsibilities**:
- Intercept WordPress search queries (`is_search()`)
- Query the Python orchestrator's `StoryGraphIntelligence` for semantic results
- Merge semantic results with keyword results
- Filter `WP_Query` to return only relevant posts
- Add entity-type and relevance metadata to search results

**Integration Points**:
```php
// Hook into WordPress search
add_action('pre_get_posts', 'storyos_enhance_search_query');
add_filter('posts_clauses', 'storyos_search_clauses', 10, 2);
add_filter('posts_results', 'storyos_search_results', 10, 2);
```

**Search Query Flow**:
```
User types in WordPress search bar
  ↓
pre_get_posts: detect is_search(), add custom meta query
  ↓
WordPress runs WP_Query (keyword match on post_content, post_title)
  ↓
posts_results: enrich with semantic scores from orchestrator
  ↓
Results sorted by combined keyword + semantic score
  ↓
Displayed in WordPress search results template
```

### 1b. `StorySearchAdmin` — Search Results Panel

An admin-only search enhancement that adds a sidebar panel showing:
- Semantic match scores
- Related entities (characters in searched scene, etc.)
- Continuity warnings for matched entities
- Quick links to edit related entities

### 1c. `StorySearchWidget` — Search Widget

A WordPress widget that replaces/augments the default search widget with:
- Entity type filters (Characters, Scenes, Locations, Assets)
- Semantic search toggle
- Recent searches
- Popular searches

---

## 2. Continuity Validation (PHP + Python)

### 2a. `ContinuityChecker` — WordPress Side

**File**: `wordpress/wp-content/plugins/storyos/includes/utils/continuity-checker.php`

**Responsibilities**:
- Hook into `save_post` actions for Scenes, Characters, Shots
- Auto-run continuity checks when content is saved/updated
- Display continuity issues as admin notices or in a dedicated meta box
- Allow manual "Run Continuity Check" from CPT edit screens

**Auto-Check Triggers**:
| Event | Check Run |
|-------|-----------|
| Scene saved | Character associations, location consistency, prop references |
| Character saved | Scene appearances, relationship conflicts |
| Shot saved | Scene membership, asset references |
| Asset saved | Derivation lineage, scene/shot references |
| Project saved | Full graph scan (expensive, runs async via WP-Cron) |

**Issue Display**:
```
┌────────────────────────────────────────────────────┐
│ ⚠ Continuity Issues (3)                           │
│                                                    │
│ ● [ERROR] Character "Mara Quinn" appears in       │
│   Scene #14 but is not associated with it.         │
│   → Add character to scene?                       │
│                                                    │
│ ● [WARNING] Prop "Silver Key" used in Scene #22   │
│   but not defined in any Prop CPT.                │
│   → Create prop entry?                            │
│                                                    │
│ ● [INFO] Location "Old Warehouse" has no visual   │
│   reference assets.                               │
│   → Generate concept art?                         │
└────────────────────────────────────────────────────┘
```

### 2b. Continuity Rules

| Rule | Severity | Description |
|------|----------|-------------|
| `character_not_associated` | error | Character appears in scene content but not in the `characters` relationship field |
| `location_mismatch` | warning | Scene references a location not in the `location` relationship field |
| `prop_undefined` | warning | Prop mentioned in scene content but no corresponding Prop CPT exists |
| `timeline_overlap` | warning | Two scenes in the same sequence have overlapping timecodes |
| `relationship_conflict` | error | Character A is marked as "Enemy" of Character B, but they never appear together (suspicious) |
| `orphaned_asset` | info | Asset has no scene/shot reference |
| `missing_visual_ref` | info | Location/Character has no visual reference assets |
| `duplicate_name` | warning | Two characters/locations with the same name |

### 2c. Python Continuity Engine

The Python orchestrator's `StoryGraphIntelligence.validate_continuity()` method (`orchestrator/story_intelligence.py`) handles the heavy computation:

- Fetches all scenes, characters, locations, props via WordPress REST API
- Builds an in-memory graph representation
- Runs rule-based checks (character appearances, location consistency, prop continuity, scene ordering, relationship consistency, content completeness)
- Returns structured `ContinuityIssue` objects

The PHP side calls this via the REST API endpoint `POST /intelligence/validate`.

---

## 3. Relationship Analytics (PHP)

### 3a. `RelationshipGraph` — Network Analysis

**File**: `wordpress/wp-content/plugins/storyos/includes/utils/relationship-graph.php`

**Responsibilities**:
- Compute character co-occurrence matrices
- Build location-scene bipartite graphs
- Calculate entity centrality (most connected entities)
- Detect isolated entities (orphaned characters, unused locations)
- Generate graph data for visualization

**API Endpoints** (REST):
```
GET /storyos/v1/analytics/character-network
GET /storyos/v1/analytics/entity-density
GET /storyos/v1/analytics/isolated-entities
GET /storyos/v1/analytics/co-occurrence/{character_id}
GET /storyos/v1/analytics/graph-summary
```

**Response Example**:
```json
{
  "character_network": [
    {
      "character_id": 42,
      "name": "Mara Quinn",
      "scene_count": 15,
      "co_occurrences": {
        "James Cole": 12,
        "Dr. Aris": 8,
        "Vex": 5
      },
      "locations": {
        "Old Warehouse": 7,
        "City Hospital": 5,
        "Safe House": 3
      },
      "centrality": 0.85
    }
  ],
  "graph_density": 0.34,
  "isolated_entities": [
    {
      "type": "location",
      "id": 89,
      "name": "Abandoned Mine",
      "reason": "No scenes reference this location"
    }
  ]
}
```

### 3b. Analytics Admin Panel

A WordPress admin page (`Tools > Story Graph Analytics`) displaying:
- Entity count summary
- Character co-occurrence table
- Most/least connected entities
- Isolated entity report
- Graph density metric

---

## 4. Embedding Backend (Python)

Fully implemented in `orchestrator/story_intelligence.py`.

### Supported Backends

| Backend | Use Case | Dependencies |
|---------|----------|--------------|
| `DummyEmbeddingBackend` | Development, CI, testing | None (hash-based 128-dim vectors) |
| `OllamaEmbeddingBackend` | Local model via Ollama server | Ollama running (`nomic-embed-text`) |
| `SentenceTransformerBackend` | Local CPU/GPU model | `sentence-transformers` |

### Current Production Status

The system uses `DummyEmbeddingBackend` by default for development. Production deployments should configure `OllamaEmbeddingBackend` or `SentenceTransformerBackend`:

```python
# Default: Dummy (hash-based, zero dependencies)
embeddings = DummyEmbeddingBackend(dimension=128)

# Production: Ollama (768-dim, local GPU)
embeddings = OllamaEmbeddingBackend(
    url="http://localhost:11434",
    model="nomic-embed-text",
    dimension=768,
)

# Production: Sentence-Transformers (CPU/GPU)
embeddings = SentenceTransformerBackend(
    model="all-MiniLM-L6-v2",
    device="cuda",  # or "cpu"
)
```

### Configuration

```python
# In orchestrator config or environment variables:
STORYOS_EMBEDDING_BACKEND = "ollama"  # or "dummy", "sentence-transformers"
STORYOS_EMBEDDING_MODEL = "nomic-embed-text"  # for Ollama
STORYOS_EMBEDDING_DIMENSION = 768
STORYOS_EMBEDDING_URL = "http://localhost:11434"  # for Ollama
```

### Indexing

The `index_entities()` method in `StoryGraphIntelligence` builds embedding vectors for all Story Graph entities. This is called:
1. On first search (lazy build)
2. Manually via `POST /intelligence/index`
3. On demand from WordPress admin

Note: Incremental indexing (re-index only on content change) is planned but not yet implemented. Current behavior rebuilds the full index on each call.

---

# File Structure

```
orchestrator/
├── story_intelligence.py          # Core intelligence engine (already exists)
│                                   #   - Embedding backends
│                                   #   - Semantic search
│                                   #   - Continuity validation
│                                   #   - Relationship analytics
├── story_graph.py                 # Context builder (already exists)
├── app.py                         # FastAPI app — intelligence endpoints integrated
│                                   #   - POST /intelligence/search (hybrid/semantic/keyword)
│                                   #   - POST /intelligence/index
│                                   #   - POST /intelligence/validate
│                                   #   - GET /intelligence/relationships
│                                   #   - GET /intelligence/graph-analytics
│                                   #   - GET /intelligence/character-network
│                                   #   - POST /intelligence/character-analytics
└── workflows/                     # (existing)

wordpress/wp-content/plugins/storyos/
├── storyos.php                    # Main plugin — wires in new components
├── includes/
│   ├── utils/
│   │   ├── story-search.php       # NEW: WP_Query search enhancement
│   │   ├── continuity-checker.php # NEW: Continuity validation hooks
│   │   └── relationship-graph.php # NEW: Network analysis
│   ├── admin/
│   │   └── continuity-panel.php   # Continuity admin panel with AJAX handlers
│   └── rest-api/
│       └── base-controller.php    # Base REST controller pattern
├── assets/
│   └── intelligence/
│       ├── js/analytics.js        # Analytics dashboard JS
│       ├── css/analytics.css      # Analytics dashboard styles
│       └── js/continuity.js       # Continuity panel JS
└── tests/
    └── python/
        └── test_story_intelligence.py  # Comprehensive intelligence tests (128+ matches)
            ├── TestDummyEmbeddingBackend (8 tests)
            ├── TestOllamaEmbeddingBackend (mocked)
            ├── TestSentenceTransformerBackend (mocked)
            ├── TestStoryGraphIntelligence (semantic, fuzzy, hybrid, continuity, analytics)
```

---

# API Specification

## Python Orchestrator REST API

### Semantic Search

```http
POST /intelligence/search
Content-Type: application/json

{
  "query": "mysterious detective in warehouse",
  "entity_types": ["characters", "scenes", "locations"],
  "top_k": 10,
  "min_score": 0.1,
  "mode": "hybrid"  // "semantic", "keyword", "hybrid"
}
```

**Response**:
```json
{
  "query": "mysterious detective in warehouse",
  "mode": "hybrid",
  "results": [
    {
      "entity_type": "characters",
      "entity_id": 42,
      "title": "Mara Quinn",
      "score": 0.9234,
      "snippet": "A mysterious detective with a troubled past...",
      "is_semantic": true
    },
    {
      "entity_type": "scenes",
      "entity_id": 14,
      "title": "Warehouse Night",
      "score": 0.7812,
      "snippet": "INT. WAREHOUSE - NIGHT. Mara stands alone...",
      "is_semantic": true
    }
  ],
  "total_results": 2,
  "indexed_at": 1723123456.0
}
```

### Continuity Validation

```http
POST /intelligence/continuity
Content-Type: application/json

{
  "episode_id": 5,
  "scene_ids": [14, 15, 16]
}
```

**Response**:
```json
{
  "issues": [
    {
      "severity": "error",
      "category": "character",
      "description": "Character 'Mara Quinn' (ID 42) appears in Scene #14 content but is not associated with the scene.",
      "entities": [
        { "type": "character", "id": 42, "name": "Mara Quinn" },
        { "type": "scene", "id": 14, "name": "Warehouse Night" }
      ],
      "suggestion": "Add Mara Quinn to the characters relationship field of Scene #14."
    }
  ],
  "total_issues": 1,
  "errors": 1,
  "warnings": 0,
  "info": 0
}
```

### Relationship Analytics

```http
GET /intelligence/analytics/character-network
GET /intelligence/analytics/graph-summary
GET /intelligence/analytics/isolated-entities
GET /intelligence/analytics/co-occurrence?character_id=42
```

### Index Management

```http
POST /intelligence/reindex
Content-Type: application/json

{
  "entity_types": ["characters", "scenes"],  // optional, defaults to all
  "force": false  // skip cache, re-embed everything
}
```

**Response**:
```json
{
  "indexed_at": 1723123456.0,
  "entity_types": ["characters", "scenes"],
  "total_entries": 47,
  "indexed_counts": {
    "characters": 12,
    "scenes": 35
  }
}
```

## WordPress REST API (PHP Side)

### Intelligence Proxy

```http
GET  /storyos/v1/intelligence/search?query=mysterious+detective
POST /storyos/v1/intelligence/search
GET  /storyos/v1/intelligence/continuity?episode_id=5
GET  /storyos/v1/intelligence/analytics/character-network
GET  /storyos/v1/intelligence/analytics/graph-summary
POST /storyos/v1/intelligence/reindex
```

### Continuity Issues

```http
GET  /storyos/v1/continuity/issues?post_id=14
GET  /storyos/v1/continuity/issues?episode_id=5
POST /storyos/v1/continuity/run?post_id=14
```

### Analytics

```http
GET  /storyos/v1/analytics/character-network
GET  /storyos/v1/analytics/isolated-entities
GET  /storyos/v1/analytics/co-occurrence?character_id=42
```

---

# WordPress Search Integration Details

## How It Works

### Default WordPress Search (Before Phase 7)

```
User searches "detective"
  ↓
WP_Query runs SQL: WHERE post_content LIKE '%detective%' OR post_title LIKE '%detective%'
  ↓
Returns posts matching the keyword
  ↓
Results shown in search template
```

### Enhanced WordPress Search (After Phase 7)

```
User searches "mysterious detective"
  ↓
pre_get_posts: detect is_search(), store query
  ↓
WP_Query runs SQL (keyword match — may return nothing)
  ↓
posts_results: send query to Python orchestrator
  ↓
Orchestrator computes embeddings, returns semantic results
  ↓
Results merged: keyword score (30%) + semantic score (70%)
  ↓
Results sorted by combined score
  ↓
Results shown with semantic badges and related entities
```

### Implementation: `pre_get_posts` Hook

```php
/**
 * Enhance WordPress search with Story Graph intelligence.
 *
 * @param WP_Query $query The WP_Query instance.
 */
function storyos_enhance_search_query( WP_Query $query ): void {
    if ( ! is_search() || is_admin() || ! current_user_can( 'read' ) ) {
        return;
    }

    // Store the search query for later use.
    $query->set( 'storyos_search_query', sanitize_text_field( $_GET['s'] ?? '' ) );
    
    // Add meta query for relevance scoring.
    $query->set( 'meta_query', [
        'relation' => 'OR',
        [
            'key'     => '_storyos_semantic_score',
            'compare' => 'EXISTS',
        ],
    ] );
}
```

### Implementation: Search Results Enrichment

```php
/**
 * Enrich search results with semantic scores from the orchestrator.
 *
 * @param array    $results The search results.
 * @param WP_Query $query   The WP_Query instance.
 * @return array
 */
function storyos_search_results( array $results, WP_Query $query ): array {
    if ( ! is_search() ) {
        return $results;
    }

    $search_query = $query->get( 'storyos_search_query' );
    if ( ! $search_query ) {
        return $results;
    }

    // Call orchestrator for semantic results.
    $semantic_results = storyos_call_intelligence_search( $search_query );

    // Merge and re-sort.
    return storyos_merge_search_results( $results, $semantic_results, $query );
}
```

### Search Results Template Enhancement

The search results template is enhanced to display:
- **Entity type badges** (Character, Scene, Location, Asset)
- **Semantic score indicators** (high/medium/low confidence)
- **Related entities** (e.g., "This character appears in 5 scenes")
- **Continuity warnings** (if the entity has issues)

---

# Indexing Strategy

## Full Re-Index

```
POST /intelligence/reindex
```

Scans all Story Graph entities, extracts text fields, computes embeddings, stores results.

**When to run**:
- Initial setup
- After major content changes
- Manual trigger from admin
- After embedding backend change

## Incremental Re-Index

Triggered automatically when:
- A Scene is saved/updated
- A Character is saved/updated
- A Location is saved/updated
- Any CPT with indexed fields is modified

**Mechanism**:
1. WordPress `save_post` action stores the modified post ID in a transient
2. A WP-Cron event runs every 5 minutes
3. The cron handler checks the transient, re-embeds only modified entities
4. Clears the transient

## Cache Invalidation

When content changes:
1. The entity's embedding cache entry is invalidated
2. The next search will re-embed the changed entity
3. Other entities remain cached

---

# Embedding Fields Per Entity Type

| Entity Type | Indexed Fields |
|-------------|---------------|
| **Character** | `title.rendered`, `meta.biography`, `meta.appearance`, `meta.motivation`, `meta.backstory`, `meta.personality_traits` |
| **Location** | `title.rendered`, `meta.description`, `meta.mood`, `meta.geography`, `meta.environment_type` |
| **Scene** | `title.rendered`, `meta.summary`, `meta.script_content`, `meta.production_notes` |
| **Shot** | `meta.description`, `meta.visual_description`, `meta.notes` |
| **Asset** | `title.rendered`, `meta.description`, `meta.generation_prompt` |
| **Prop** | `title.rendered`, `meta.description`, `meta.purpose` |
| **Project** | `title.rendered`, `meta.description`, `meta.genre` |
| **Story World** | `title.rendered`, `meta.description`, `meta.timeline`, `meta.rules`, `meta.themes` |

---

# Testing Strategy

## Unit Tests (Python)

```python
# tests/python/test-intelligence/test_semantic_search.py

def test_semantic_search_returns_results():
    intelligence = StoryGraphIntelligence(
        wordpress_url="http://wordpress.test",
        username="admin",
        app_password="test",
        embedding_backend=DummyEmbeddingBackend()
    )
    results = intelligence.semantic_search("mysterious detective", top_k=5)
    assert len(results) > 0
    assert all(isinstance(r, SearchResult) for r in results)
    assert all(r.score >= 0.0 for r in results)

def test_hybrid_search_combines_scores():
    intelligence = StoryGraphIntelligence(...)
    results = intelligence.hybrid_search("detective", semantic_weight=0.7, keyword_weight=0.3)
    # Verify semantic and keyword results are merged
    assert len(results) > 0

def test_fuzzy_search_finds_exact_matches():
    intelligence = StoryGraphIntelligence(...)
    results = intelligence.fuzzy_search("Mara Quinn")
    assert any(r["title"] == "Mara Quinn" for r in results)
```

## Continuity Tests

```python
# tests/python/test-intelligence/test_continuity.py

def test_character_not_associated():
    """Detect when a character is mentioned in scene content but not in relationships."""
    intelligence = StoryGraphIntelligence(...)
    issues = intelligence.validate_continuity(scene_ids=[14])
    assert any(i.category == "character" and i.severity == "error" for i in issues)

def test_no_issues_when_all_associated():
    """No issues when all characters are properly associated."""
    intelligence = StoryGraphIntelligence(...)
    issues = intelligence.validate_continuity(scene_ids=[15])  # properly configured scene
    assert all(i.category != "character" for i in issues)
```

## Analytics Tests

```python
# tests/python/test-intelligence/test_analytics.py

def test_character_co_occurrence():
    analytics = intelligence.get_character_analytics(42)
    assert analytics.name == "Mara Quinn"
    assert isinstance(analytics.co_occurrences, dict)
    assert all(isinstance(v, int) for v in analytics.co_occurrences.values())

def test_isolated_entities_detected():
    isolated = intelligence.get_isolated_entities()
    assert all(e["entity_type"] in intelligence._index_fields for e in isolated)
```

## WordPress Integration Tests

```php
// tests/php/test-story-search.php

class Test_StorySearch extends WP_UnitTestCase {
    public function test_search_enhancement_returns_semantic_results() {
        // Create a character with biography containing "mysterious detective"
        $char_id = $this->factory()->post->create([
            'post_type'  => 'storyos_character',
            'post_title' => 'Mara Quinn',
            'meta'       => ['biography' => 'A mysterious detective...'],
        ]);

        // Perform search
        $results = storyos_call_intelligence_search('mysterious detective');
        
        $this->assertNotEmpty($results);
        $this->assertEquals('characters', $results[0]['entity_type']);
    }

    public function test_continuity_checker_detects_issues() {
        // Create scene with character mentioned but not associated
        $scene_id = $this->factory()->post->create([
            'post_type'  => 'storyos_scene',
            'post_title' => 'Warehouse Night',
            'meta'       => [
                'summary' => 'Mara Quinn enters the warehouse.',
                'characters' => [], // Empty — should trigger issue
            ],
        ]);

        $issues = ContinuityChecker::check_scene($scene_id);
        $this->assertNotEmpty($issues);
        $this->assertEquals('error', $issues[0]['severity']);
    }
}
```

---

# Implementation Status

## ✅ Core Intelligence Engine (Complete)

- [x] `story_intelligence.py` — Embedding backends (Dummy, Ollama, Sentence-Transformers)
- [x] `story_intelligence.py` — Semantic search (cosine similarity)
- [x] `story_intelligence.py` — Fuzzy/keyword search (term overlap scoring)
- [x] `story_intelligence.py` — Hybrid search merge (configurable weights, default 0.7/0.3)
- [x] `story_intelligence.py` — Continuity validation (6 check categories)
- [x] `story_intelligence.py` — Relationship analytics (co-occurrence, centrality, isolated entities)
- [x] `app.py` — REST API endpoints (`/intelligence/search`, `/intelligence/validate`, `/intelligence/relationships`, `/intelligence/graph-analytics`, `/intelligence/character-network`, `/intelligence/character-analytics`)
- [x] `models.py` — Pydantic request/response models with `use_hybrid` parameter
- [x] `tests/test_story_intelligence.py` — Comprehensive tests (128+ test matches)

## ✅ WordPress Search Integration (Complete)

- [x] `includes/utils/story-search.php` — WP_Query search enhancement with hybrid/semantic/keyword modes
- [x] `includes/utils/story-search.php` — `search_config()` for orchestrator URL, entity types, modes
- [x] `includes/utils/story-search.php` — `fetch_semantic_search()` calls orchestrator `/intelligence/search`
- [x] `includes/utils/story-search.php` — WP_Query integration with entity type filters
- [x] Admin bar integration with search UI

## ✅ Continuity Validation (Complete)

- [x] `includes/utils/continuity-checker.php` — Auto-check on save via `auto_check_continuity_on_save()`
- [x] `includes/utils/continuity-checker.php` — `fetch_continuity_validation()` calls orchestrator `/intelligence/validate`
- [x] `includes/admin/continuity-panel.php` — Continuity admin panel with AJAX handlers
- [x] Structured issue storage in post meta
- [x] Severity levels (error, warning, info) with category filtering

## ✅ Relationship Analytics (Complete)

- [x] `includes/utils/relationship-graph.php` — Network analysis functions
- [x] `includes/utils/relationship-graph.php` — `fetch_relationship_graph()` calls orchestrator `/intelligence/relationships`
- [x] `includes/utils/relationship-graph.php` — `fetch_graph_analytics()` calls orchestrator `/intelligence/graph-analytics`
- [x] Network density computation
- [x] Co-occurrence matrix calculation
- [x] Isolated entity detection
- [x] Character centrality scoring

## ⏳ Planned Improvements

- [ ] Incremental indexing (WP-Cron based) — currently full re-index on each call
- [ ] Embedding cache optimization with TTL-based invalidation
- [ ] Search result caching (WordPress transients)
- [ ] Performance benchmarks with production-scale data
- [ ] E2E tests with Playwright
- [ ] Real-time search suggestions (debounced input)
- [ ] Knowledge graph database integration (Neo4j) — future

---

# Success Metrics

## Search Quality

| Metric | Target |
|--------|--------|
| Semantic search recall@10 | > 0.85 |
| Hybrid search precision@5 | > 0.75 |
| Keyword fallback coverage | 100% (always returns something) |
| Search response time | < 500ms (with cache) |

## Continuity

| Metric | Target |
|--------|--------|
| Auto-check trigger reliability | 100% on save |
| Issue detection accuracy | > 0.90 (manual validation) |
| False positive rate | < 0.10 |

## Analytics

| Metric | Target |
|--------|--------|
| Graph computation time (100 entities) | < 2s |
| Analytics page load time | < 1s |
| Isolated entity detection | 100% |

---

# Future Extensions

## 8.1: Knowledge Graph Database

Replace in-memory graph with a proper graph database:
- Neo4j integration
- Native graph queries (Cypher)
- Persistent relationship storage
- Real-time graph updates

## 8.2: Narrative Reasoning

Add LLM-powered narrative analysis:
- Plot hole detection
- Character arc consistency
- Theme tracking
- Pacing analysis

## 8.3: Semantic Media Queries

Allow WordPress queries by semantic relationship:
```
"Show me all scenes where Mara Quinn is alone"
"Find locations that feel dark and claustrophobic"
"Show assets generated for scenes with action tone"
```

## 8.4: Story Graph Visualization

Interactive graph visualization in WordPress admin:
- Force-directed graph layout
- Click-to-explore relationships
- Filter by entity type, relationship type
- Export graph as SVG/PNG

---

# Relationship to Other Phases

| Phase | Relationship |
|-------|-------------|
| **Phase 1-3** | Story Graph, Generation Core, Agent Core provide the data and infrastructure |
| **Phase 5** | Script import creates content that becomes searchable |
| **Phase 6** | Editorial artifacts become part of the searchable graph |
| **Phase 8** | AI Editor uses intelligence results to provide context-aware AI responses |
| **Phase 9** | Community can share search configurations, continuity rule sets |

---

# Long-Term Goal

StoryOS is not another AI generation platform.

StoryOS is an open storytelling operating system that enables creators to manage stories, assets, production, and editorial workflows from a unified Story Graph.

**The future of storytelling is structured.**

**The future of storytelling is open.**

**The future of storytelling is intelligent.**
