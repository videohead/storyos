<?php
/**
 * Plugin Name: Send to ComfyUI
 * Description: Adds a "Send to ComfyUI" button to WordPress posts and forwards jobs to a configurable ComfyUI endpoint.
 * Requires Plugins: story-os, simple-custom-fields
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('is_plugin_active')) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

function comfy_generate_get_settings() {
    $defaults = [
        'endpoint_url' => '',
        'username' => '',
        'password' => '',
        'auth_token' => '',
    ];

    $settings = get_option('comfy_generate_button_settings', []);

    if (!is_array($settings)) {
        $settings = [];
    }

    return wp_parse_args($settings, $defaults);
}

function comfy_generate_sanitize_settings($input) {
    $output = [
        'endpoint_url' => '',
        'username' => '',
        'password' => '',
        'auth_token' => '',
    ];

    if (!is_array($input)) {
        return $output;
    }

    if (!empty($input['endpoint_url'])) {
        $output['endpoint_url'] = esc_url_raw(trim((string) $input['endpoint_url']));
    }

    if (!empty($input['username'])) {
        $output['username'] = sanitize_text_field((string) $input['username']);
    }

    if (!empty($input['password'])) {
        $output['password'] = sanitize_text_field((string) $input['password']);
    }

    if (!empty($input['auth_token'])) {
        $output['auth_token'] = sanitize_text_field((string) $input['auth_token']);
    }

    return $output;
}

function comfy_generate_register_settings() {
    register_setting(
        'comfy_generate_button_settings_group',
        'comfy_generate_button_settings',
        [
            'type' => 'array',
            'sanitize_callback' => 'comfy_generate_sanitize_settings',
            'default' => [
                'endpoint_url' => '',
                'username' => '',
                'password' => '',
                'auth_token' => '',
            ],
        ]
    );
}
add_action('admin_init', 'comfy_generate_register_settings');

function comfy_generate_add_settings_page() {
    add_options_page(
        __('Send to ComfyUI', 'comfy-generate-button'),
        __('Send to ComfyUI', 'comfy-generate-button'),
        'manage_options',
        'comfy-generate-button',
        'comfy_generate_render_settings_page'
    );
}
add_action('admin_menu', 'comfy_generate_add_settings_page');

function comfy_generate_render_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $settings = comfy_generate_get_settings();
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Send to ComfyUI Settings', 'comfy-generate-button'); ?></h1>
        <p><?php esc_html_e('Configure the ComfyUI endpoint and optional authentication.', 'comfy-generate-button'); ?></p>

        <form method="post" action="options.php">
            <?php settings_fields('comfy_generate_button_settings_group'); ?>

            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="comfy-endpoint-url"><?php esc_html_e('ComfyUI Endpoint URL', 'comfy-generate-button'); ?></label>
                        </th>
                        <td>
                            <input
                                id="comfy-endpoint-url"
                                name="comfy_generate_button_settings[endpoint_url]"
                                type="url"
                                class="regular-text"
                                placeholder="http://127.0.0.1:8000/generate"
                                value="<?php echo esc_attr($settings['endpoint_url']); ?>"
                            />
                            <p class="description"><?php esc_html_e('Example: http://10.0.0.34:8000/generate', 'comfy-generate-button'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="comfy-username"><?php esc_html_e('Username', 'comfy-generate-button'); ?></label>
                        </th>
                        <td>
                            <input
                                id="comfy-username"
                                name="comfy_generate_button_settings[username]"
                                type="text"
                                class="regular-text"
                                autocomplete="off"
                                value="<?php echo esc_attr($settings['username']); ?>"
                            />
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="comfy-password"><?php esc_html_e('Password', 'comfy-generate-button'); ?></label>
                        </th>
                        <td>
                            <input
                                id="comfy-password"
                                name="comfy_generate_button_settings[password]"
                                type="password"
                                class="regular-text"
                                autocomplete="new-password"
                                value="<?php echo esc_attr($settings['password']); ?>"
                            />
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="comfy-auth-token"><?php esc_html_e('Auth Token', 'comfy-generate-button'); ?></label>
                        </th>
                        <td>
                            <input
                                id="comfy-auth-token"
                                name="comfy_generate_button_settings[auth_token]"
                                type="text"
                                class="regular-text"
                                autocomplete="off"
                                value="<?php echo esc_attr($settings['auth_token']); ?>"
                            />
                            <p class="description"><?php esc_html_e('If token is present, Bearer auth is used. Otherwise, Basic auth is used when username/password are set.', 'comfy-generate-button'); ?></p>
                        </td>
                    </tr>
                </tbody>
            </table>

            <?php submit_button(__('Save Settings', 'comfy-generate-button')); ?>
        </form>
    </div>
    <?php
}

function comfy_generate_ajax_send_to_comfyui() {
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => __('You are not allowed to run this action.', 'comfy-generate-button')], 403);
    }

    check_ajax_referer('comfy_generate_send_nonce', 'nonce');

    $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;

    if ($post_id <= 0) {
        wp_send_json_error(['message' => __('Invalid post ID.', 'comfy-generate-button')], 400);
    }

    $settings = comfy_generate_get_settings();
    $endpoint_url = trim((string) $settings['endpoint_url']);

    if (empty($endpoint_url)) {
        wp_send_json_error(['message' => __('ComfyUI endpoint URL is not configured.', 'comfy-generate-button')], 400);
    }

    $headers = [
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
    ];

    if (!empty($settings['auth_token'])) {
        $headers['Authorization'] = 'Bearer ' . $settings['auth_token'];
    } elseif (!empty($settings['username']) || !empty($settings['password'])) {
        $headers['Authorization'] = 'Basic ' . base64_encode($settings['username'] . ':' . $settings['password']);
    }

    $request_body = wp_json_encode([
        'post_id' => $post_id,
    ]);

    $response = wp_remote_post($endpoint_url, [
        'timeout' => 30,
        'headers' => $headers,
        'body' => $request_body,
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error([
            'message' => __('Request failed.', 'comfy-generate-button'),
            'details' => $response->get_error_message(),
        ], 500);
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $raw_body = wp_remote_retrieve_body($response);
    $decoded = json_decode($raw_body, true);

    if ($status_code < 200 || $status_code >= 300) {
        wp_send_json_error([
            'message' => __('ComfyUI endpoint returned an error.', 'comfy-generate-button'),
            'status_code' => $status_code,
            'response' => $decoded ?: $raw_body,
        ], 500);
    }

    wp_send_json_success([
        'status_code' => $status_code,
        'response' => $decoded ?: $raw_body,
    ]);
}
add_action('wp_ajax_comfy_generate_send_to_comfyui', 'comfy_generate_ajax_send_to_comfyui');

/**
 * Check that required plugins are active before loading this plugin.
 */
add_action('plugins_loaded', function() {
    $required_groups = [
        'StoryOS' => [
            'storyos/storyos.php',
            'story-os/story-os.php',
        ],
        'SCF (Simple Custom Fields)' => [
            'secure-custom-fields/secure-custom-fields.php',
            'secure-custom-fields/acf.php',
            'simple-custom-fields/scf.php',
        ],
    ];

    $missing = [];

    foreach ($required_groups as $label => $candidates) {
        $active = false;

        foreach ($candidates as $plugin_file) {
            if (is_plugin_active($plugin_file)) {
                $active = true;
                break;
            }
        }

        if (!$active) {
            $missing[] = $label;
        }
    }

    if (!empty($missing)) {
        add_action('admin_notices', function() use ($missing) {
            echo '<div class="error"><p>';
            printf(
                esc_html__('Send to ComfyUI cannot run because the following plugins are missing or inactive: %s. Please install and activate them.', 'comfy-generate-button'),
                implode(', ', $missing)
            );
            echo '</p></div>';
        });
        return;
    }

    // All dependencies met — register the button in the post editor.
    add_action('post_submitbox_misc_actions', function() {
    global $post;

    if (!$post) return;

    $nonce = wp_create_nonce('comfy_generate_send_nonce');
    $ajax_url = admin_url('admin-ajax.php');

    ?>
    <div id="comfy-generate-box" style="padding:10px; border-top:1px solid #ddd;">
        <button id="comfy-generate-btn"
                class="button button-primary"
                style="width:100%; margin-top:10px;">
            Send to ComfyUI
        </button>

        <div id="comfy-generate-status"
             style="margin-top:10px; font-weight:bold;"></div>

        <script>
            document.addEventListener("DOMContentLoaded", function() {
                const btn = document.getElementById("comfy-generate-btn");
                const status = document.getElementById("comfy-generate-status");

                btn.addEventListener("click", function() {
                    status.textContent = "Sending request to ComfyUI...";
                    btn.disabled = true;

                    fetch("<?php echo esc_url($ajax_url); ?>", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
                        },
                        body: new URLSearchParams({
                            action: "comfy_generate_send_to_comfyui",
                            nonce: "<?php echo esc_js($nonce); ?>",
                            post_id: "<?php echo (int) $post->ID; ?>"
                        }).toString()
                    })
                    .then(response => response.json())
                    .then(data => {
                        btn.disabled = false;

                        if (!data || typeof data !== "object") {
                            status.textContent = "Error: Invalid response from server.";
                            return;
                        }

                        if (data.success && data.data && data.data.response) {
                            const payload = data.data.response;
                            const jobId = payload.job_id || payload.id || payload.queue_id;

                            if (jobId) {
                                status.textContent = "Job queued: " + jobId;
                            } else {
                                status.textContent = "Request sent successfully.";
                            }
                        } else {
                            const err = data.data && (data.data.message || data.data.details)
                                ? (data.data.message + (data.data.details ? " " + data.data.details : ""))
                                : "Unknown error.";
                            status.textContent = "Error: " + err;
                        }
                    })
                    .catch(err => {
                        btn.disabled = false;
                        status.textContent = "Request failed: " + err;
                    });
                });
            });
        </script>
    </div>
    <?php
    }); // end post_submitbox_misc_actions
});// end plugins_loaded
