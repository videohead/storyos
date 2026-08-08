"""Pytest configuration for StoryOS test-framework."""
import os
import sys
import pytest

# Add the test-framework directory to the Python path
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

# Database configuration for WordPress tests
DB_NAME = os.getenv('DB_NAME', 'wordpress')
DB_USER = os.getenv('DB_USER', 'wordpress')
DB_PASSWORD = os.getenv('DB_PASSWORD', 'wordpress')
DB_HOST = os.getenv('DB_HOST', 'database')
WP_BASE_URL = os.getenv('WP_BASE_URL', 'http://appserver')
WP_USERNAME = os.getenv('WP_USERNAME', 'admin')
WP_APP_PASSWORD = os.getenv('WP_APP_PASSWORD', 'admin')


@pytest.fixture
def db_config():
    """Provide database configuration."""
    return {
        'name': DB_NAME,
        'user': DB_USER,
        'password': DB_PASSWORD,
        'host': DB_HOST,
    }


@pytest.fixture
def wp_config():
    """Provide WordPress configuration."""
    return {
        'base_url': WP_BASE_URL,
        'username': WP_USERNAME,
        'app_password': WP_APP_PASSWORD,
    }


@pytest.fixture
def rest_client(wp_config):
    """Create a REST API client session."""
    import requests
    session = requests.Session()
    session.headers.update({
        'Content-Type': 'application/json',
        'X-WP-Nonce': '',  # Will be set after authentication
    })
    return session


@pytest.fixture
def admin_token(rest_client, wp_config):
    """Get an admin authentication token."""
    import requests
    # Get nonce for authentication
    response = requests.get(f"{wp_config['base_url']}/wp-json/")
    nonce = response.headers.get('X-WP-Nonce', '')
    rest_client.headers['X-WP-Nonce'] = nonce
    return nonce
