<?php
/**
 * Plugin Name: StoryOS Transient Cache
 * Description: Provides REST API endpoints for StoryOS orchestrator caching. Stores API responses in WordPress transients to reduce redundant WordPress REST API calls.
 * Version: 1.0.0
 * Author: StoryOS Team
 * License: MIT
 * Text Domain: storyos
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register REST API routes for transient caching.
 */
class StoryOS_Transient_Cache {
    
    const NAMESPACE = 'storyos/v1';
    const TRANSIENT_PREFIX = 'storyos_';
    const DEFAULT_TTL = 900; // 15 minutes
    
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
        add_action('init', [$this, 'flush_transients_on_cron']);
    }
    
    /**
     * Register REST API routes.
     */
    public function register_routes() {
        // Get transient
        register_rest_route(self::NAMESPACE, '/transient/(?P<key>[a-zA-Z0-9_-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_transient'],
            'permission_callback' => [$this, 'verify_permission'],
        ]);
        
        // Set/update transient
        register_rest_route(self::NAMESPACE, '/transient/(?P<key>[a-zA-Z0-9_-]+)', [
            'methods' => 'POST',
            'callback' => [$this, 'set_transient'],
            'permission_callback' => [$this, 'verify_permission'],
            'args' => [
                'data' => [
                    'required' => true,
                    'type' => 'object',
                    'description' => 'Data to cache',
                ],
            ],
        ]);
        
        // Delete transient
        register_rest_route(self::NAMESPACE, '/transient/(?P<key>[a-zA-Z0-9_-]+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_transient'],
            'permission_callback' => [$this, 'verify_permission'],
        ]);
        
        // Flush all StoryOS transients
        register_rest_route(self::NAMESPACE, '/transients/flush', [
            'methods' => 'POST',
            'callback' => [$this, 'flush_all_transients'],
            'permission_callback' => [$this, 'verify_permission'],
        ]);
        
        // Get cache stats
        register_rest_route(self::NAMESPACE, '/transients/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'get_stats'],
            'permission_callback' => [$this, 'verify_permission'],
        ]);
    }
    
    /**
     * Verify API access using application password or auth header.
     */
    public function verify_permission($request) {
        // Allow if already authenticated via WordPress
        if (is_user_logged_in()) {
            return true;
        }
        
        // Check for application password auth
        $auth_type = $this->get_auth_type();
        
        if ($auth_type === 'application_password') {
            $username = $request->get_header('x_wp_nonce') ?: $request->get_header('author');
            $password = $request->get_header('x_wp_nonce') ?: $request->get_header('password');
            
            if ($username && $password) {
                return !is_wp_error(username_password_login($username, $password));
            }
        }
        
        // Basic auth fallback
        if ($auth_type === 'basic') {
            $auth = $this->get_basic_auth();
            if ($auth) {
                return !is_wp_error(wp_authenticate($auth['username'], $auth['password']));
            }
        }
        
        return new WP_Error(
            'rest_forbidden',
            'Authentication required',
            ['status' => 401]
        );
    }
    
    /**
     * Get authentication type from environment.
     */
    private function get_auth_type() {
        if (defined('WP_APPLICATION_PASSWORDS_ENABLED') && WP_APPLICATION_PASSWORDS_ENABLED) {
            return 'application_password';
        }
        if (defined('BASIC_AUTH_ENABLED') && BASIC_AUTH_ENABLED) {
            return 'basic';
        }
        return 'application_password'; // Default
    }
    
    /**
     * Get Basic Auth credentials from header.
     */
    private function get_basic_auth() {
        $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (strpos($auth_header, 'Basic ') === 0) {
            $creds = base64_decode(substr($auth_header, 6));
            if (strpos($creds, ':') !== false) {
                list($username, $password) = explode(':', $creds, 2);
                return ['username' => $username, 'password' => $password];
            }
        }
        return null;
    }
    
    /**
     * Get transient from WordPress.
     */
    public function get_transient($request) {
        $key = self::TRANSIENT_PREFIX . sanitize_key($request->get_param('key'));
        $data = get_transient($key);
        
        if ($data === false) {
            return new WP_Error(
                'transient_not_found',
                'Transient not found or expired',
                ['status' => 404]
            );
        }
        
        return rest_ensure_response([
            'success' => true,
            'data' => $data,
            'key' => $request->get_param('key'),
        ]);
    }
    
    /**
     * Set transient in WordPress.
     */
    public function set_transient($request) {
        $key = self::TRANSIENT_PREFIX . sanitize_key($request->get_param('key'));
        $data = $request->get_param('data');
        $ttl = (int) $request->get_param('ttl') ?: self::DEFAULT_TTL;
        
        // WordPress transients can only store serializable data
        // Convert any non-serializable types
        $data = $this->prepare_for_serialization($data);
        
        if (set_transient($key, $data, $ttl)) {
            return rest_ensure_response([
                'success' => true,
                'message' => 'Transient set successfully',
                'key' => $request->get_param('key'),
                'ttl' => $ttl,
            ]);
        }
        
        return new WP_Error(
            'transient_set_failed',
            'Failed to set transient',
            ['status' => 500]
        );
    }
    
    /**
     * Delete transient from WordPress.
     */
    public function delete_transient($request) {
        $key = self::TRANSIENT_PREFIX . sanitize_key($request->get_param('key'));
        
        if (delete_transient($key)) {
            return rest_ensure_response([
                'success' => true,
                'message' => 'Transient deleted',
                'key' => $request->get_param('key'),
            ]);
        }
        
        return new WP_Error(
            'transient_delete_failed',
            'Transient not found or could not be deleted',
            ['status' => 404]
        );
    }
    
    /**
     * Flush all StoryOS transients.
     */
    public function flush_all_transients() {
        global $wpdb;
        
        // Delete all transients with our prefix
        $like_pattern = $wpdb->esc_like(self::TRANSIENT_PREFIX) . '%';
        $results = $wpdb->delete(
            $wpdb->options,
            ['option_name' => $wpdb->prepare('LIKE %s', $like_pattern)],
            ['%s']
        );
        
        // Also delete _transient_timeout_ entries
        $timeout_pattern = '_transient_timeout_' . self::TRANSIENT_PREFIX;
        $timeout_results = $wpdb->delete(
            $wpdb->options,
            ['option_name' => $wpdb->prepare('LIKE %s', $timeout_pattern)],
            ['%s']
        );
        
        return rest_ensure_response([
            'success' => true,
            'message' => 'All StoryOS transients flushed',
            'transients_deleted' => $results ?: 0,
            'timeouts_deleted' => $timeout_results ?: 0,
        ]);
    }
    
    /**
     * Get cache statistics.
     */
    public function get_stats() {
        global $wpdb;
        
        $like_pattern = $wpdb->esc_like(self::TRANSIENT_PREFIX) . '%';
        
        // Count transients
        $transient_count = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->options}
            WHERE option_name LIKE '%s'
        ", $like_pattern);
        
        // Get total size
        $total_size = $wpdb->get_var("
            SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options}
            WHERE option_name LIKE '%s'
        ", $like_pattern);
        
        // Get oldest and newest
        $oldest = $wpdb->get_row("
            SELECT option_name, option_value FROM {$wpdb->options}
            WHERE option_name LIKE '%s'
            ORDER BY option_name ASC
            LIMIT 1
        ", $like_pattern);
        
        $newest = $wpdb->get_row("
            SELECT option_name, option_value FROM {$wpdb->options}
            WHERE option_name LIKE '%s'
            ORDER BY option_name DESC
            LIMIT 1
        ", $like_pattern);
        
        return rest_ensure_response([
            'success' => true,
            'stats' => [
                'transient_count' => (int) $transient_count,
                'total_size_bytes' => (int) ($total_size ?: 0),
                'total_size_human' => size_format((int) ($total_size ?: 0), 2),
                'prefix' => self::TRANSIENT_PREFIX,
                'default_ttl' => self::DEFAULT_TTL,
            ],
        ]);
    }
    
    /**
     * Prepare data for WordPress transient storage (must be serializable).
     */
    private function prepare_for_serialization($data) {
        // Convert DateTime objects to ISO strings
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if ($value instanceof DateTime) {
                    $data[$key] = $value->format('c'); // ISO 8601
                } elseif (is_object($value)) {
                    $data[$key] = json_encode($value);
                } elseif (is_array($value)) {
                    $data[$key] = $this->prepare_for_serialization($value);
                }
            }
        }
        return $data;
    }
    
    /**
     * Flush transients on cron (optional cleanup).
     */
    public function flush_transients_on_cron() {
        if (wp_next_scheduled('storyos_flush_transients') === false) {
            wp_schedule_event(time(), 'daily', 'storyos_flush_transients');
        }
    }
}

// Initialize the plugin
new StoryOS_Transient_Cache();

/**
 * Schedule daily transient cleanup.
 */
register_deactivation_hook(__FILE__, function() {
    wp_clear_scheduled_hook('storyos_flush_transients');
});
