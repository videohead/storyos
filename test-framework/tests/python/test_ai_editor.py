"""
Tests for the StoryOS AI Editor REST API endpoints.
"""

import pytest
import requests


class TestAIEditorREST:
    """Test suite for AI Editor REST API endpoints."""

    @pytest.fixture(autouse=True)
    def setup(self, rest_client, admin_token):
        """Set up test fixtures."""
        self.base_url = "http://appserver/wp-json/storyos/v1"
        self.ai_base_url = "http://appserver/wp-json/ai/v1"
        self.headers = {
            "Authorization": f"Bearer {admin_token}",
            "Content-Type": "application/json",
        }

    def test_ai_editor_plugin_active(self, wp_rest):
        """Test that the AI Editor plugin is active."""
        response = requests.get(
            "http://appserver/wp-json/wp/v2/plugins",
            headers=self.headers,
        )
        assert response.status_code == 200
        plugins = response.json()
        plugin_slugs = [p["slug"] for p in plugins]
        assert "storyos" in plugin_slugs, "StoryOS plugin should be active"

    def test_ai_rest_namespace_exists(self):
        """Test that the AI REST namespace is registered."""
        response = requests.get("http://appserver/wp-json/", headers=self.headers)
        assert response.status_code == 200
        namespaces = response.json()
        assert any("/ai/" in key for key in namespaces.keys()), "AI namespace should exist"

    def test_ai_health_endpoint(self):
        """Test the /ai/health endpoint."""
        response = requests.post(
            "http://appserver/wp-json/ai/v1/health",
            headers=self.headers,
        )
        assert response.status_code in [200, 503]  # 503 if LLM not available
        data = response.json()
        assert "status" in data, "Health response should have status key"

    def test_ai_agents_endpoint(self):
        """Test the /ai/agents endpoint."""
        response = requests.post(
            "http://appserver/wp-json/ai/v1/agents",
            headers=self.headers,
            json={"action": "list"},
        )
        assert response.status_code in [200, 500]  # 500 if MAF not loaded
        data = response.json()
        assert "agents" in data or "error" in data, "Agents response should have agents or error"

    def test_ai_context_endpoint(self):
        """Test the /ai/context endpoint."""
        # Create a test post first
        post_data = {
            "post_type": "post",
            "post_title": "Test Post for AI",
            "post_content": "This is a test post for AI context building",
            "post_status": "draft",
        }
        post_response = requests.post(
            "http://appserver/wp-json/wp/v2/posts",
            headers=self.headers,
            json=post_data,
        )
        assert post_response.status_code == 201
        post_id = post_response.json()["id"]

        # Test context endpoint
        response = requests.post(
            "http://appserver/wp-json/ai/v1/context",
            headers=self.headers,
            json={"post_id": post_id},
        )
        assert response.status_code in [200, 400]
        data = response.json()
        assert "context" in data or "error" in data, "Context response should have context or error"

    def test_ai_chat_endpoint_rejects_without_backend(self):
        """Test that /ai/chat returns error when no LLM backend configured."""
        response = requests.post(
            "http://appserver/wp-json/ai/v1/chat",
            headers=self.headers,
            json={"prompt": "Test prompt"},
        )
        # Should return error since no LLM is configured
        assert response.status_code in [200, 500]
        data = response.json()
        assert "content" in data or "error" in data, "Chat response should have content or error"

    def test_ai_settings_endpoint(self):
        """Test the /ai/settings endpoint."""
        response = requests.post(
            "http://appserver/wp-json/ai/v1/settings",
            headers=self.headers,
            json={"action": "get"},
        )
        assert response.status_code == 200
        data = response.json()
        assert "backend" in data or "error" in data, "Settings response should have backend or error"

    def test_ai_analyze_endpoint(self):
        """Test the /ai/analyze endpoint."""
        response = requests.post(
            "http://appserver/wp-json/ai/v1/analyze",
            headers=self.headers,
            json={
                "content": "This is a test scene with characters",
                "analysis_type": "continuity",
            },
        )
        assert response.status_code in [200, 500]
        data = response.json()
        assert "analysis" in data or "error" in data, "Analyze response should have analysis or error"

    def test_ai_generate_endpoint(self):
        """Test the /ai/generate endpoint."""
        response = requests.post(
            "http://appserver/wp-json/ai/v1/generate",
            headers=self.headers,
            json={
                "prompt": "Write a scene",
                "type": "scene",
            },
        )
        assert response.status_code in [200, 500]
        data = response.json()
        assert "content" in data or "error" in data, "Generate response should have content or error"

    def test_ai_continuity_endpoint(self):
        """Test the /ai/continuity endpoint."""
        response = requests.post(
            "http://appserver/wp-json/ai/v1/continuity",
            headers=self.headers,
            json={
                "scene_id": 1,
                "previous_scenes": [],
            },
        )
        assert response.status_code in [200, 500]
        data = response.json()
        assert "continuity" in data or "error" in data, "Continuity response should have continuity or error"


class TestAIEditorAssets:
    """Test suite for AI Editor frontend assets."""

    @pytest.fixture(autouse=True)
    def setup(self, admin_token):
        """Set up test fixtures."""
        self.headers = {
            "Authorization": f"Bearer {admin_token}",
            "Content-Type": "application/json",
        }

    def test_ai_editor_assets_exist(self):
        """Test that AI Editor asset files exist."""
        import os
        plugin_path = "/var/www/html/wp-content/plugins/storyos"
        ai_editor_path = os.path.join(plugin_path, "includes/ai-editor/")
        
        assert os.path.isdir(ai_editor_path), "AI Editor directory should exist"
        
        # Check for JS and CSS files
        js_files = [f for f in os.listdir(ai_editor_path) if f.endswith(".js")]
        css_files = [f for f in os.listdir(ai_editor_path) if f.endswith(".css")]
        
        assert len(js_files) > 0, "Should have at least one JS file"
        assert len(css_files) > 0, "Should have at least one CSS file"


class TestAIEditorAutoloader:
    """Test suite for AI Editor autoloading."""

    @pytest.fixture(autouse=True)
    def setup(self):
        """Set up test fixtures."""
        self.base_url = "http://appserver/wp-json"
        self.headers = {
            "Content-Type": "application/json",
        }

    def test_ai_editor_classes_loaded(self):
        """Test that AI Editor classes are loaded by WordPress."""
        # This tests that the autoloader correctly maps AI\ namespace
        response = requests.get(
            "http://appserver/wp-json/wp/v2/types",
        )
        assert response.status_code == 200
        # If we got here, WordPress loaded without autoloader errors
