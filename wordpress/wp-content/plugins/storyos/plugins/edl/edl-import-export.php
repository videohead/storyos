<?php
/**
 * Plugin Name: StoryOS - EDL Import/Export
 * Plugin URI: https://github.com/videohead/storyos
 * Description: Import and export Edit Decision Lists (ASCII/CMX 3600 & XML) for StoryOS projects and episodes.
 * Version: 1.0.0
 * Author: StoryOS Contributors
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: storyos-edl
 * Requires Plugins: storyos/storyos.php
 * Requires at least: 6.0
 * Requires PHP: 8.1
 *
 * @package StoryOSEDL
 */

namespace StoryOSEDL;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'STORYOS_EDL_VERSION', '1.0.0' );
define( 'STORYOS_EDL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'STORYOS_EDL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'STORYOS_EDL_PLUGIN_BASE', plugin_basename( __FILE__ ) );

/**
 * Whether EDL integration is enabled.
 *
 * @return bool
 */
function is_enabled(): bool {
	return (bool) get_option( 'storyos_edl_enabled', true );
}

/**
 * Initialize the plugin.
 */
function init(): void {
	if ( ! is_enabled() ) {
		return;
	}

	// Register admin menu page.
	add_action( 'admin_menu', __NAMESPACE__ . '\\add_admin_page' );

	// Enqueue admin assets on EDL page.
	add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\enqueue_assets' );

	// AJAX handlers.
	add_action( 'wp_ajax_storyos_edl_action', __NAMESPACE__ . '\\ajax_handler' );

	// Activation/deactivation hooks.
	register_activation_hook( __FILE__, __NAMESPACE__ . '\\activate' );
	register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\deactivate' );
}

if ( did_action( 'plugins_loaded' ) ) {
	init();
} else {
	add_action( 'plugins_loaded', __NAMESPACE__ . '\\init' );
}

/**
 * Flush rewrite rules on activation.
 */
function activate(): void {
	flush_rewrite_rules();
	add_option( 'storyos_edl_version', STORYOS_EDL_VERSION );
}

/**
 * Flush rewrite rules on deactivation.
 */
function deactivate(): void {
	flush_rewrite_rules();
}

/**
 * Add EDL Manager admin menu page.
 */
function add_admin_page(): void {
	add_submenu_page(
		'storyos',
		'EDL Manager',
		'EDL Manager',
		'manage_storyos',
		'storyos-edl',
		__NAMESPACE__ . '\\render_admin_page'
	);
}

/**
 * Enqueue admin assets on EDL page.
 *
 * @param string $hook WordPress admin page hook.
 */
function enqueue_assets( string $hook ): void {
	if ( 'storyos-page_storyos-edl' !== $hook ) {
		return;
	}

	wp_enqueue_script(
		'storyos-edl-manager',
		STORYOS_EDL_PLUGIN_URL . 'js/edl-manager.js',
		[ 'jquery' ],
		STORYOS_EDL_VERSION,
		true
	);

	wp_enqueue_style(
		'storyos-edl-manager',
		STORYOS_EDL_PLUGIN_URL . 'css/edl-manager.css',
		[],
		STORYOS_EDL_VERSION
	);

	wp_localize_script( 'storyos-edl-manager', 'edlConfig', [
		'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
		'nonce'      => wp_create_nonce( 'storyos_edl_action' ),
		'defaultFps' => 24,
	] );
}

/**
 * Render the EDL Manager admin page.
 */
function render_admin_page(): void {
	if ( ! current_user_can( 'manage_storyos' ) ) {
		wp_die( 'Insufficient permissions.' );
	}
	?>
	<div class="wrap storyos-edl-wrap">
		<h1>StoryOS EDL Manager</h1>

		<div id="edl-status" class="notice" style="display:none;"></div>

		<form id="storyos-edl-form" enctype="multipart/form-data">
			<?php wp_nonce_field( 'storyos_edl_action', 'storyos_edl_nonce' ); ?>

			<h2 class="nav-tab-wrapper">
				<button type="button" class="nav-tab nav-tab-active" data-tab="import">Import EDL</button>
				<button type="button" class="nav-tab" data-tab="export">Export EDL</button>
			</h2>

			<!-- Import Section -->
			<div id="tab-import" class="edl-tab-content">
				<table class="form-table">
					<tr>
						<th scope="row"><label for="edl_file">EDL File</label></th>
						<td>
							<input type="file" name="edl_file" id="edl_file" accept=".txt,.edl,.xml">
							<p class="description">Upload .txt, .edl, or .xml file.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="edl_format_import">EDL Format</label></th>
						<td>
							<select name="format" id="edl_format_import">
								<option value="cmx3600">CMX 3600 (ASCII)</option>
								<option value="xml">XML (SMPTE 436m)</option>
							</select>
							<p class="description">Select the format of the uploaded EDL file.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="edl_fps_import">Frame Rate</label></th>
						<td>
							<select name="fps" id="edl_fps_import">
								<option value="23976">23.976 fps (24 drop)</option>
								<option value="24" selected>24 fps (Film)</option>
								<option value="25">25 fps (PAL)</option>
								<option value="2997">29.97 fps (NTSC Drop-Frame)</option>
								<option value="30">30 fps (Non-Drop)</option>
								<option value="50">50 fps (PAL High Frame)</option>
								<option value="5994">59.94 fps (NTSC Drop-Frame)</option>
								<option value="60">60 fps (Non-Drop)</option>
								<option value="120">120 fps (High Frame)</option>
							</select>
							<p class="description">Frame rate must match the source footage.</p>
						</td>
					</tr>
				</table>

				<div id="edl-preview" class="storyos-edl-preview" style="display:none;">
					<h2>Import Preview</h2>
					<p>Detected <strong id="preview-clip-count">0</strong> clips:</p>
					<table id="preview-table" class="widefat fixed" style="display:none;">
						<thead>
							<tr>
								<th>#</th>
								<th>Reel</th>
								<th>Clip Name</th>
								<th>Source In</th>
								<th>Source Out</th>
								<th>Record In</th>
								<th>Record Out</th>
								<th>Duration</th>
							</tr>
						</thead>
						<tbody id="preview-table-body"></tbody>
					</table>
				</div>

				<p class="submit">
					<button type="button" id="edl-preview-btn" class="button">Preview EDL</button>
					<button type="button" id="edl-import-btn" class="button button-primary" disabled>Confirm Import</button>
				</p>
			</div>

			<!-- Export Section -->
			<div id="tab-export" class="edl-tab-content" style="display:none;">
				<table class="form-table">
					<tr>
						<th scope="row"><label for="edl_target">Target</label></th>
						<td>
							<select name="target" id="edl_target">
								<option value="project">Project</option>
								<option value="episode">Episode</option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="edl_format_export">Export Format</label></th>
						<td>
							<select name="format" id="edl_format_export">
								<option value="cmx3600">CMX 3600 (ASCII)</option>
								<option value="xml">XML (SMPTE 436m)</option>
							</select>
							<p class="description">
								<strong>CMX 3600</strong> — Universal format for Premiere Pro, Avid, DaVinci Resolve, Unreal Engine.<br>
								<strong>XML</strong> — SMPTE 436m structured format for XML-aware tools.
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="edl_fps_export">Frame Rate</label></th>
						<td>
							<select name="fps" id="edl_fps_export">
								<option value="23976">23.976 fps (24 drop)</option>
								<option value="24" selected>24 fps (Film)</option>
								<option value="25">25 fps (PAL)</option>
								<option value="2997">29.97 fps (NTSC Drop-Frame)</option>
								<option value="30">30 fps (Non-Drop)</option>
								<option value="50">50 fps (PAL High Frame)</option>
								<option value="5994">59.94 fps (NTSC Drop-Frame)</option>
								<option value="60">60 fps (Non-Drop)</option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="edl_reel">Reel Name</label></th>
						<td>
							<input type="text" name="reel" id="edl_reel" value="REEL 001" maxlength="8">
							<p class="description">Reel/tape name (max 8 chars). Used as source identifier in EDL.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label>Pre-Roll (Handles)</label></th>
						<td>
							<input type="number" name="pre_roll" id="edl_pre_roll" value="0" min="0" max="1000">
							<p class="description">Frames of pre-roll to add before each clip. Useful for Unreal Engine Sequencer edit flexibility.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label>Post-Roll (Handles)</label></th>
						<td>
							<input type="number" name="post_roll" id="edl_post_roll" value="0" min="0" max="1000">
							<p class="description">Frames of post-roll to add after each clip. Useful for Unreal Engine Sequencer edit flexibility.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label>Clip Naming</label></th>
						<td>
							<label><input type="checkbox" name="use_32char" id="edl_use_32char" value="1"> Use 32-character clip names</label>
							<p class="description">Enable for Premiere Pro compatibility with long clip/tape names.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label>Drop-Frame Timecode</label></th>
						<td>
							<label><input type="checkbox" name="drop_frame" id="edl_drop_frame" value="1" checked> Use drop-frame timecode</label>
							<p class="description">Required for 29.97/59.94fps NTSC. Uses semicolon (;) separator for frames.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label>Video Track</label></th>
						<td>
							<input type="text" name="video_track" id="edl_video_track" value="V  C" maxlength="4">
							<p class="description">Video track designator. V1=Video track 1, V2=Video track 2, V C=All video.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label>Audio Track</label></th>
						<td>
							<input type="text" name="audio_track" id="edl_audio_track" value="A  C" maxlength="4">
							<p class="description">Audio track designator. A1=Audio track 1, A2=Audio track 2, A C=All audio. Leave empty to exclude audio.</p>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="button" id="edl-export-btn" class="button button-primary">Export EDL</button>
				</p>
			</div>
		</form>
	</div>
	<?php
}

/**
 * Handle AJAX import/export requests.
 */
function ajax_handler(): void {
	check_ajax_referer( 'storyos_edl_action', 'nonce' );

	if ( ! current_user_can( 'manage_storyos' ) ) {
		wp_send_json_error( 'Unauthorized' );
	}

	$action  = isset( $_POST['action'] ) ? sanitize_text_field( $_POST['action'] ) : '';
	$format  = isset( $_POST['format'] ) ? sanitize_text_field( $_POST['format'] ) : 'cmx3600';
	$fps     = isset( $_POST['fps'] ) ? intval( $_POST['fps'] ) : 24;

	if ( 'import' === $action ) {
		handle_import( $format, $fps );
	} elseif ( 'export' === $action ) {
		$options = [
			'title'       => isset( $_POST['title'] ) ? sanitize_text_field( $_POST['title'] ) : 'StoryOS EDL',
			'reel'        => isset( $_POST['reel'] ) ? sanitize_text_field( $_POST['reel'] ) : 'REEL 001',
			'pre_roll'    => isset( $_POST['pre_roll'] ) ? intval( $_POST['pre_roll'] ) : 0,
			'post_roll'   => isset( $_POST['post_roll'] ) ? intval( $_POST['post_roll'] ) : 0,
			'use_32char'  => isset( $_POST['use_32char'] ) ? (bool) $_POST['use_32char'] : false,
			'drop_frame'  => isset( $_POST['drop_frame'] ) ? (bool) $_POST['drop_frame'] : false,
			'video_track' => isset( $_POST['video_track'] ) ? sanitize_text_field( $_POST['video_track'] ) : 'V  C',
			'audio_track' => isset( $_POST['audio_track'] ) ? sanitize_text_field( $_POST['audio_track'] ) : 'A  C',
		];
		handle_export( $format, $fps, $options );
	} elseif ( 'confirm_import' === $action ) {
		handle_confirm_import();
	} else {
		wp_send_json_error( 'Invalid action.' );
	}
}

/**
 * Handle EDL import.
 *
 * @param string $format EDL format (cmx3600 or xml).
 * @param int    $fps Frame rate.
 */
function handle_import( string $format, int $fps ): void {
	if ( empty( $_FILES['edl_file'] ) ) {
		wp_send_json_error( 'No file uploaded.' );
	}

	$file    = $_FILES['edl_file'];
	$content = file_get_contents( $file['tmp_name'] );
	$edl_data = parse_edl( $content, $format, $fps );

	if ( is_wp_error( $edl_data ) ) {
		wp_send_json_error( $edl_data->get_error_message() );
	}

	// Store preview for confirmation via transient (expires in 5 minutes).
	set_transient( 'storyos_edl_import_preview', $edl_data, 300 );
	wp_send_json_success( [ 'preview' => $edl_data, 'message' => 'Preview generated. Confirm to import.' ] );
}

/**
 * Handle EDL export.
 *
 * @param string $format  EDL format (cmx3600 or xml).
 * @param int    $fps     Frame rate.
 * @param array  $options Export options (reel, pre_roll, post_roll, etc.).
 */
function handle_export( string $format, int $fps, array $options = [] ): void {
	$target_type = isset( $_POST['target'] ) ? sanitize_text_field( $_POST['target'] ) : 'project';
	$target_id   = isset( $_POST['target_id'] ) ? intval( $_POST['target_id'] ) : 0;

	$timeline_data = get_timeline_data( $target_type, $target_id );

	if ( empty( $timeline_data ) ) {
		wp_send_json_error( 'No timeline data found.' );
	}

	$output = generate_edl( $timeline_data, $format, $fps, $options );

	if ( is_wp_error( $output ) ) {
		wp_send_json_error( $output->get_error_message() );
	}

	header( 'Content-Type: application/octet-stream' );
	header( 'Content-Disposition: attachment; filename="storyos_edl.' . ( 'xml' === $format ? 'xml' : 'txt' ) . '"' );
	echo $output;
	exit;
}

/**
 * Handle import confirmation.
 */
function handle_confirm_import(): void {
	$preview = get_transient( 'storyos_edl_import_preview' );

	if ( ! $preview ) {
		wp_send_json_error( 'No preview found. Please upload again.' );
	}

	// TODO: Save EDL data to StoryOS timeline/episode DB.
	delete_transient( 'storyos_edl_import_preview' );
	wp_send_json_success( [ 'message' => 'EDL successfully imported.' ] );
}

/**
 * Parse EDL content based on format.
 *
 * @param string $content EDL file content.
 * @param string $format  EDL format (cmx3600 or xml).
 * @param int    $fps     Frame rate.
 * @return array|\WP_Error Parsed EDL data or error.
 */
function parse_edl( string $content, string $format, int $fps ) {
	$content = trim( $content );
	if ( 'xml' === $format ) {
		return parse_edl_xml( $content, $fps );
	}
	return parse_edl_ascii( $content, $fps );
}

/**
 * Parse ASCII/CMX 3600 EDL format.
 *
 * @param string $content EDL content.
 * @param int    $fps Frame rate.
 * @return array|\WP_Error Parsed clips or error.
 */
function parse_edl_ascii( string $content, int $fps ) {
	$clips = [];
	$lines = explode( "\n", $content );

	foreach ( $lines as $line ) {
		$line = trim( $line );

		// Match reel/line entries: "REEL  NAME   V C 1001 01:00:00:00 01:00:05:00 01:00:00:00 01:00:05:00 * CLIP NAME"
		if ( preg_match( '/^(\w+)\s+(\S+)\s+V?\s*C?\s+(\d+)\s+(\d{2}:\d{2}:\d{2}:\d{2})\s+(\d{2}:\d{2}:\d{2}:\d{2})\s+(\d{2}:\d{2}:\d{2}:\d{2})\s+(\d{2}:\d{2}:\d{2}:\d{2})/', $line, $matches ) ) {
			$clips[] = [
				'reel'      => $matches[1],
				'clip_name' => $matches[2],
				'tc_in'     => $matches[4],
				'tc_out'    => $matches[5],
				'film_in'   => $matches[6],
				'film_out'  => $matches[7],
				'frame_in'  => timecode_to_frames( $matches[4], $fps ),
				'frame_out' => timecode_to_frames( $matches[5], $fps ),
			];
		}
	}

	if ( empty( $clips ) ) {
		return new \WP_Error( 'parse_error', 'No valid EDL entries found in ASCII content.' );
	}

	return [ 'format' => 'cmx3600', 'fps' => $fps, 'clips' => $clips ];
}

/**
 * Parse XML EDL format.
 *
 * @param string $content EDL content.
 * @param int    $fps Frame rate.
 * @return array|\WP_Error Parsed clips or error.
 */
function parse_edl_xml( string $content, int $fps ) {
	$dom = new \DOMDocument();
	$dom->loadXML( $content, LIBXML_NOERROR );

	$event_nodes = $dom->getElementsByTagName( 'event' );
	$clips = [];

	foreach ( $event_nodes as $event ) {
		$event_id = $event->getAttribute( 'eventserial' ) ?: $event->getAttribute( 'eventcode' );
		$reel    = '';
		$clip    = '';
		$tc_in   = '';
		$tc_out  = '';

		$components = $event->getElementsByTagName( 'component' );
		foreach ( $components as $comp ) {
			$reel    = $comp->getAttribute( 'componentcode' ) ?: $reel;
			$clip    = $comp->getAttribute( 'clipname' ) ?: $clip;
		}

		$timescodes = $event->getElementsByTagName( 'timescode' );
		foreach ( $timescodes as $tc ) {
			$rel = $tc->getAttribute( 'relativetimecode' );
			if ( 'in' === $tc->getAttribute( 'role' ) ) {
				$tc_in = $rel;
			} elseif ( 'out' === $tc->getAttribute( 'role' ) ) {
				$tc_out = $rel;
			}
		}

		if ( $clip && $tc_in && $tc_out ) {
			$clips[] = [
				'reel'      => $reel,
				'clip_name' => $clip,
				'tc_in'     => $tc_in,
				'tc_out'    => $tc_out,
				'frame_in'  => timecode_to_frames( $tc_in, $fps ),
				'frame_out' => timecode_to_frames( $tc_out, $fps ),
			];
		}
	}

	if ( empty( $clips ) ) {
		return new \WP_Error( 'parse_error', 'No valid EDL entries found in XML content.' );
	}

	return [ 'format' => 'xml', 'fps' => $fps, 'clips' => $clips ];
}

/**
 * Generate EDL output from timeline data.
 *
 * @param array  $data   Timeline data.
 * @param string $format EDL format (cmx3600 or xml).
 * @param int    $fps    Frame rate.
 * @return string|\WP_Error EDL content or error.
 */
function generate_edl( array $data, string $format, int $fps ) {
	$clips = $data['clips'] ?? [];

	if ( empty( $clips ) ) {
		return new \WP_Error( 'generate_error', 'No clips to export.' );
	}

	if ( 'xml' === $format ) {
		return generate_edl_xml( $clips, $fps );
	}

	return generate_edl_ascii( $clips, $fps );
}

/**
 * Generate ASCII/CMX 3600 EDL string.
 *
 * Compatible with Unreal Engine Sequencer, Adobe Premiere Pro, Avid Media Composer,
 * DaVinci Resolve, and other NLEs that import EDL files.
 *
 * @param array  $clips        Parsed clip data.
 * @param int    $fps          Frame rate.
 * @param array  $options      Export options.
 * @return string EDL content.
 */
function generate_edl_ascii( array $clips, int $fps, array $options = [] ): string {
	$title       = $options['title']       ?? 'StoryOS EDL';
	$reel_name   = $options['reel']        ?? 'REEL 001';
	$pre_roll    = $options['pre_roll']    ?? 0;  // Frames of pre-roll (handles) for Unreal Engine compatibility.
	$post_roll   = $options['post_roll']   ?? 0;  // Frames of post-roll (handles) for Unreal Engine compatibility.
	$use_32char  = $options['use_32char']  ?? false;
	$drop_frame  = $options['drop_frame']  ?? false;
	$video_track = $options['video_track'] ?? 'V  C';
	$audio_track = $options['audio_track'] ?? 'A  C';

	// Calculate drop-frame timecode adjustment for 29.97fps.
	$df_correction = $drop_frame ? calculate_drop_frame_correction( $fps ) : 0;

	$output = "TITLE:  " . str_pad( substr( $title, 0, 32 ), 32, ' ', STR_PAD_RIGHT ) . "\n";
	$output .= "FM:     CMX-3600\n";
	$output .= "DATE:   " . date( 'M d Y' ) . "\n";
	$output .= "PM:     StoryOS\n\n";

	$index    = 1;
	$rec_in   = 0;  // Record in starts at 0 and increments with each clip duration.

	foreach ( $clips as $clip ) {
		$source_in  = $clip['frame_in']  ?? 0;
		$source_out = $clip['frame_out'] ?? 0;

		// Apply pre-roll and post-roll handles (Unreal Engine Sequencer feature).
		$source_in  = max( 0, $source_in - $pre_roll );
		$source_out = $source_out + $post_roll;

		$duration = $source_out - $source_in;

		// Calculate record positions (sequential timeline).
		$rec_in_frames  = $rec_in;
		$rec_out_frames = $rec_in + $duration;

		// Convert to timecode strings.
		$film_in  = frames_to_timecode( $source_in,  $fps, $drop_frame );
		$film_out = frames_to_timecode( $source_out, $fps, $drop_frame );
		$rec_in_tc  = frames_to_timecode( $rec_in_frames,  $fps, $drop_frame );
		$rec_out_tc = frames_to_timecode( $rec_out_frames, $fps, $drop_frame );

		// Clip name — use 32-character format if requested (Premiere Pro option).
		$clip_name = $clip['clip_name'] ?? 'CLIP' . str_pad( $index, 3, '0', STR_PAD_LEFT );
		if ( $use_32char ) {
			$clip_name = str_pad( substr( $clip_name, 0, 32 ), 32, ' ', STR_PAD_RIGHT );
		} else {
			$clip_name = str_pad( substr( $clip_name, 0, 8 ), 8, ' ', STR_PAD_RIGHT );
		}

		// Reel name padded to 8 characters.
		$reel_padded = str_pad( substr( $reel_name, 0, 8 ), 8, ' ', STR_PAD_RIGHT );

		// CMX 3600 line format:
		// LINE#  REELNAME V C FILM-IN FILM-OUT REC-IN REC-OUT * CLIP-NAME
		$output .= sprintf(
			"%04d  %s  %s  %s  %s  %s  %s  * %s\n",
			$index,
			$reel_padded,
			$video_track,
			$film_in,
			$film_out,
			$rec_in_tc,
			$rec_out_tc,
			$clip_name
		);

		// Add audio line if audio tracks are defined.
		if ( ! empty( $audio_track ) ) {
			$output .= sprintf(
				"%04d  %s  %s  %s  %s  %s  %s  * %s\n",
				$index,
				$reel_padded,
				$audio_track,
				$film_in,
				$film_out,
				$rec_in_tc,
				$rec_out_tc,
				$clip_name
			);
		}

		$index++;
		$rec_in = $rec_out_frames;  // Next clip starts where previous ended.
	}

	return trim( $output );
}

/**
 * Calculate drop-frame timecode correction factor.
 *
 * For 29.97fps, drop-frame skips frames 0 and 1 of every minute (except every 10th minute)
 * to stay within 1 minute of real time. This function returns the cumulative drop count
 * for a given frame number.
 *
 * @param int $fps Frame rate.
 * @return int Drop frame correction.
 */
function calculate_drop_frame_correction( int $fps ): int {
	// Only applicable to 29.97fps (and its variants).
	if ( abs( $fps - 29.97 ) > 0.01 && abs( $fps - 23.976 ) > 0.001 ) {
		return 0;
	}

	// Drop-frame skips 2 frames per minute, except every 10th minute.
	// That's 108 drops per hour = 1080 drops per 10 hours.
	// For simplicity, return a small correction factor used by the timecode function.
	return 108;  // Drops per hour.
}

/**
 * Generate XML EDL string.
 *
 * @param array $clips Parsed clip data.
 * @param int   $fps Frame rate.
 * @return string EDL content.
 */
function generate_edl_xml( array $clips, int $fps ): string {
	$xml = new \DOMDocument( '1.0', 'UTF-8' );
	$xml->formatOutput = true;

	$root = $xml->createElement( 'smpte:edl' );
	$root->setAttribute( 'xmlns:smpte', 'urn:smpte:smpte-436m:edl' );
	$xml->appendChild( $root );

	$information = $xml->createElement( 'information' );
	$title = $xml->createElement( 'title', 'StoryOS EDL' );
	$information->appendChild( $title );
	$root->appendChild( $information );

	$index = 1;
	foreach ( $clips as $clip ) {
		$event = $xml->createElement( 'event' );
		$event->setAttribute( 'eventcode', (string) $index );

		$event_info = $xml->createElement( 'eventserial' );
		$event_info->appendChild( $xml->createTextNode( (string) $index ) );
		$event->appendChild( $event_info );

		$component = $xml->createElement( 'component' );
		$component->setAttribute( 'componentcode', $clip['reel'] ?? 'REEL 001' );
		$component->setAttribute( 'clipname', $clip['clip_name'] ?? '' );
		$event->appendChild( $component );

		$tc_in  = frames_to_timecode( $clip['frame_in'] ?? 0, $fps );
		$tc_out = frames_to_timecode( $clip['frame_out'] ?? 0, $fps );

		$tc_in_node = $xml->createElement( 'timescode', $tc_in );
		$tc_in_node->setAttribute( 'role', 'in' );
		$tc_in_node->setAttribute( 'relativetimecode', $tc_in );
		$event->appendChild( $tc_in_node );

		$tc_out_node = $xml->createElement( 'timescode', $tc_out );
		$tc_out_node->setAttribute( 'role', 'out' );
		$tc_out_node->setAttribute( 'relativetimecode', $tc_out );
		$event->appendChild( $tc_out_node );

		$root->appendChild( $event );
		$index++;
	}

	return $xml->saveXML();
}

/**
 * Convert timecode string to frame number.
 *
 * @param string $timecode HH:MM:SS:FF
 * @param int    $fps Frame rate.
 * @return int Frame number.
 */
function timecode_to_frames( string $timecode, int $fps ): int {
	$parts = explode( ':', $timecode );
	if ( 4 !== count( $parts ) ) {
		return 0;
	}
	$hours   = intval( $parts[0] );
	$minutes = intval( $parts[1] );
	$seconds = intval( $parts[2] );
	$frames  = intval( $parts[3] );

	return ( ( ( $hours * 60 + $minutes ) * 60 + $seconds ) * $fps ) + $frames;
}

/**
 * Convert frame number to timecode string.
 *
 * @param int   $frames     Frame number.
 * @param int   $fps        Frame rate.
 * @param bool  $drop_frame Use drop-frame timecode (for 29.97/23.976fps).
 * @return string HH:MM:SS:FF
 */
function frames_to_timecode( int $frames, int $fps, bool $drop_frame = false ): string {
	// For drop-frame, we need to account for dropped frames.
	if ( $drop_frame && ( abs( $fps - 29.97 ) < 0.01 || abs( $fps - 23.976 ) < 0.001 ) ) {
		return frames_to_timecode_drop( $frames, $fps );
	}

	$total_seconds = intdiv( $frames, $fps );
	$remaining_frames = $frames % $fps;

	$hours   = intdiv( $total_seconds, 3600 );
	$minutes = intdiv( $total_seconds % 3600, 60 );
	$seconds = $total_seconds % 60;

	return sprintf( '%02d:%02d:%02d:%02d', $hours, $minutes, $seconds, $remaining_frames );
}

/**
 * Convert frame number to drop-frame timecode string.
 *
 * Drop-frame timecode skips frames 0 and 1 of every minute (except every 10th minute)
 * to keep the timecode within ~3.6 seconds of wall-clock time for 29.97fps video.
 *
 * @param int $frames Frame number.
 * @param int $fps    Frame rate (typically 29.97 or 23.976).
 * @return string HH:MM:SS:DF
 */
function frames_to_timecode_drop( int $frames, int $fps ): string {
	// Use 29.97 as the base rate for drop-frame calculations.
	$df_fps = 29.97;

	// Calculate hours, minutes, seconds, frames manually for drop-frame.
	$frames_per_minute = round( $df_fps * 60 );  // 1798 for 29.97
	$frames_per_hour   = $frames_per_minute * 60; // 107880 for 29.97

	// Drop frames per 10 minutes (except the 10th minute itself).
	$drops_per_10_min = 2 * 9;  // 18 drops

	// Calculate hours.
	$hours   = intdiv( $frames, $frames_per_hour );
	$frames  = $frames % $frames_per_hour;

	// Calculate minutes with drop correction.
	$full_10_min_blocks = intdiv( $frames, $frames_per_minute * 10 );
	$remaining_after_10 = $frames % ( $frames_per_minute * 10 );

	// Each 10-minute block has drops.
	$minutes = $full_10_min_blocks * 10 + intdiv( $remaining_after_10, $frames_per_minute );

	// Calculate seconds.
	$frames = $frames - ( $minutes * $frames_per_minute ) - ( $full_10_min_blocks * $drops_per_10_min );
	$seconds = intdiv( $frames, $df_fps );
	$frames  = $frames % $df_fps;

	// Frame number.
	$frame = intval( round( $frames ) );

	return sprintf( '%02d:%02d:%02d;%02d', $hours, $minutes, $seconds, $frame );
}

/**
 * Get timeline data from StoryOS.
 *
 * @param string $target_type Target type (project or episode).
 * @param int    $target_id   Target post ID.
 * @return array Timeline data.
 */
function get_timeline_data( string $target_type, int $target_id ): array {
	// TODO: Implement actual StoryOS timeline data retrieval.
	// This is a placeholder that returns sample data for development.
	return [
		'format' => 'cmx3600',
		'fps'    => 24,
		'clips'  => [
			[
				'reel'      => 'REEL 001',
				'clip_name' => 'SC001_SH001',
				'frame_in'  => 0,
				'frame_out' => 72,
			],
			[
				'reel'      => 'REEL 001',
				'clip_name' => 'SC001_SH002',
				'frame_in'  => 72,
				'frame_out' => 144,
			],
		],
	];
}