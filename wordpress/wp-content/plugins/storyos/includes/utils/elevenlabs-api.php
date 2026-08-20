<?php
/**
 * ElevenLabs REST API Connection adapter.
 *
 * @package StoryOS
 */

namespace StoryOS\Utils;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** ElevenLabs generative-audio API client. */
class ElevenLabs_API {

	/** Default API base URL. */
	const ENDPOINT = 'https://api.elevenlabs.io/v1';

	/** HTTP timeout in seconds. */
	const TIMEOUT = 120;

	/** Test unsaved Setup Wizard credentials and return the available catalog. */
	public static function test_configuration( string $endpoint, string $credential_reference ) {
		return self::catalog_for( $endpoint, $credential_reference );
	}

	/** Return available models and voices for a saved Connection. */
	public static function catalog( int $connection_id ) {
		$connection = Connection_Repository::get( $connection_id );
		if ( ! is_array( $connection ) || 'elevenlabs' !== ( $connection['provider_type'] ?? '' ) ) {
			return new WP_Error( 'elevenlabs_connection_invalid', __( 'Select an ElevenLabs Connection first.', 'storyos' ) );
		}

		$voice_ids = json_decode( (string) ( $connection['model_access'] ?? '' ), true );
		$voice_ids = is_array( $voice_ids ) ? array_slice( array_values( array_filter( array_map( 'strval', $voice_ids ) ) ), 0, 20 ) : [];
		return self::catalog_for( (string) $connection['endpoint_url'], (string) $connection['credential_reference'], $voice_ids );
	}

	/** Run an endpoint-specific audio Template synchronously. */
	public static function run_template( string $template_ref, string $prompt, array $parameters, int $connection_id = 0 ) {
		$connection = Connection_Repository::get( $connection_id );
		if ( ! is_array( $connection ) || 'elevenlabs' !== ( $connection['provider_type'] ?? '' ) ) {
			return new WP_Error( 'elevenlabs_connection_invalid', __( 'The selected Connection is not an ElevenLabs Connection.', 'storyos' ) );
		}

		$text = trim( wp_strip_all_tags( $prompt ) );
		if ( '' === $text ) {
			return new WP_Error( 'elevenlabs_text_missing', __( 'Enter a generation prompt.', 'storyos' ) );
		}

		$output_format = sanitize_key( (string) ( $parameters['output_format'] ?? 'mp3_44100_128' ) );
		$parts = explode( ':', trim( $template_ref ), 2 );
		$method = sanitize_key( str_replace( '-', '_', $parts[0] ) );
		$voice_id = (string) ( $parts[1] ?? '' );
		if ( ! in_array( $method, [ 'text_to_speech', 'text_to_dialogue', 'sound_effects', 'music', 'voice_design' ], true ) ) {
			// Backward compatibility for Templates that stored only a voice ID.
			$method = 'text_to_speech';
			$voice_id = trim( $template_ref );
		}

		switch ( $method ) {
			case 'text_to_dialogue':
				$body = self::allowed_parameters( $parameters, [ 'model_id', 'language_code', 'settings', 'pronunciation_dictionary_locators', 'seed', 'apply_text_normalization' ] );
				$body['model_id'] = (string) ( $body['model_id'] ?? 'eleven_v3' );
				$body['inputs'] = isset( $parameters['inputs'] ) && is_array( $parameters['inputs'] )
					? $parameters['inputs']
					: [ [ 'text' => $text, 'voice_id' => (string) ( $parameters['voice_id'] ?? '' ) ] ];
				if ( '' === (string) ( $body['inputs'][0]['voice_id'] ?? '' ) ) {
					return new WP_Error( 'elevenlabs_voice_missing', __( 'The dialogue Template requires at least one voice ID.', 'storyos' ) );
				}
				return self::post_audio( $connection, '/text-to-dialogue', $body, $output_format, $method );

			case 'sound_effects':
				$body = self::allowed_parameters( $parameters, [ 'loop', 'duration_seconds', 'prompt_influence', 'model_id' ] );
				$body['text'] = $text;
				$body['model_id'] = (string) ( $body['model_id'] ?? 'eleven_text_to_sound_v2' );
				return self::post_audio( $connection, '/sound-generation', $body, $output_format, $method );

			case 'music':
				$body = self::allowed_parameters( $parameters, [ 'composition_plan', 'music_length_ms', 'model_id', 'seed', 'force_instrumental', 'finetune_id', 'respect_sections_durations', 'store_for_inpainting', 'sign_with_c2pa' ] );
				if ( empty( $body['composition_plan'] ) ) {
					$body['prompt'] = $text;
				}
				$body['model_id'] = (string) ( $body['model_id'] ?? 'music_v2' );
				return self::post_audio( $connection, '/music', $body, $output_format, $method );

			case 'voice_design':
				$body = self::allowed_parameters( $parameters, [ 'model_id', 'text', 'auto_generate_text', 'loudness', 'seed', 'guidance_scale', 'should_enhance', 'quality' ] );
				$body['voice_description'] = $text;
				$body['model_id'] = (string) ( $body['model_id'] ?? 'eleven_multilingual_ttv_v2' );
				return self::post_voice_design( $connection, $body, $output_format );

			case 'text_to_speech':
			default:
				if ( '' === $voice_id ) {
					$voice_id = (string) ( $parameters['voice_id'] ?? '' );
				}
				if ( '' === $voice_id ) {
					return new WP_Error( 'elevenlabs_voice_missing', __( 'The text-to-speech Template requires a voice ID.', 'storyos' ) );
				}
				$body = self::allowed_parameters( $parameters, [ 'model_id', 'language_code', 'voice_settings', 'seed', 'previous_text', 'next_text', 'apply_text_normalization', 'apply_language_text_normalization' ] );
				$body['text'] = $text;
				$body['model_id'] = (string) ( $body['model_id'] ?? $connection['model'] ?? 'eleven_multilingual_v2' );
				return self::post_audio( $connection, '/text-to-speech/' . rawurlencode( $voice_id ), $body, $output_format, $method );
		}
	}

	/** POST a JSON body and return binary audio for immediate WordPress import. */
	private static function post_audio( array $connection, string $path, array $body, string $output_format, string $method ) {
		$response = self::post( $connection, $path, $body, $output_format );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$data = (string) wp_remote_retrieve_body( $response );
		if ( '' === $data ) {
			return new WP_Error( 'elevenlabs_audio_empty', __( 'ElevenLabs returned an empty audio response.', 'storyos' ) );
		}
		$mime = trim( explode( ';', (string) wp_remote_retrieve_header( $response, 'content-type' ) )[0] );
		return [ 'job_id' => wp_generate_uuid4(), 'status' => 'completed', 'audio_data' => $data, 'audio_mime' => $mime ?: self::mime_for_format( $output_format ), 'audio_format' => $output_format, 'method' => $method ];
	}

	/** POST Voice Design and return every generated preview for import. */
	private static function post_voice_design( array $connection, array $body, string $output_format ) {
		$response = self::post( $connection, '/text-to-voice/design', $body, $output_format );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $decoded ) ) {
			return new WP_Error( 'elevenlabs_invalid_response', __( 'ElevenLabs Voice Design returned invalid JSON.', 'storyos' ) );
		}
		$items = [];
		foreach ( (array) ( $decoded['previews'] ?? [] ) as $preview ) {
			$data = is_array( $preview ) ? base64_decode( (string) ( $preview['audio_base_64'] ?? '' ), true ) : false;
			if ( false !== $data && '' !== $data ) {
				$items[] = [ 'data' => $data, 'mime' => (string) ( $preview['media_type'] ?? self::mime_for_format( $output_format ) ), 'generated_voice_id' => (string) ( $preview['generated_voice_id'] ?? '' ) ];
			}
		}
		if ( empty( $items ) ) {
			return new WP_Error( 'elevenlabs_audio_empty', __( 'ElevenLabs Voice Design returned no audio previews.', 'storyos' ) );
		}
		return [ 'job_id' => wp_generate_uuid4(), 'status' => 'completed', 'audio_items' => $items, 'method' => 'voice_design', 'preview_text' => (string) ( $decoded['text'] ?? '' ) ];
	}

	/** Perform one authenticated JSON POST and validate the HTTP response. */
	private static function post( array $connection, string $path, array $body, string $output_format ) {
		$headers = self::headers( (string) $connection['credential_reference'] );
		if ( is_wp_error( $headers ) ) {
			return $headers;
		}
		$url = add_query_arg( 'output_format', $output_format, self::v1_endpoint( (string) $connection['endpoint_url'] ) . $path );
		$response = wp_remote_post( $url, [ 'timeout' => self::TIMEOUT, 'limit_response_size' => 52428800, 'headers' => $headers, 'body' => wp_json_encode( $body ) ] );
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'elevenlabs_unreachable', $response->get_error_message() );
		}
		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'elevenlabs_request_failed', self::error_message( (string) wp_remote_retrieve_body( $response ), $code ), [ 'status' => $code ] );
		}
		return $response;
	}

	/** Copy only endpoint-supported Template inputs into an API body. */
	private static function allowed_parameters( array $parameters, array $allowed ): array {
		return array_intersect_key( $parameters, array_flip( $allowed ) );
	}

	/** Load models and the first page of voices using unsaved credentials. */
	private static function catalog_for( string $endpoint, string $credential_reference, array $voice_ids = [] ) {
		$headers = self::headers( $credential_reference );
		if ( is_wp_error( $headers ) ) {
			return $headers;
		}

		$models = self::json_get( self::v1_endpoint( $endpoint ) . '/models', $headers );
		if ( is_wp_error( $models ) ) {
			return $models;
		}
		$voices = [ 'voices' => [] ];
		if ( ! empty( $voice_ids ) ) {
			foreach ( $voice_ids as $voice_id ) {
				$voice = self::json_get( self::v1_endpoint( $endpoint ) . '/voices/' . rawurlencode( $voice_id ), $headers );
				if ( is_wp_error( $voice ) ) {
					return $voice;
				}
				$voices['voices'][] = $voice;
			}
		} else {
			$voices = self::json_get( self::api_root( $endpoint ) . '/v2/voices?page_size=100&include_total_count=false', $headers );
			if ( is_wp_error( $voices ) ) {
				return $voices;
			}
		}

		$all_models = array_values( array_filter( (array) $models, static function ( $model ): bool {
			return is_array( $model ) && ! empty( $model['model_id'] );
		} ) );
		return [
			'models' => $all_models,
			'text_to_speech_models' => array_values( array_filter( $all_models, static function ( $model ): bool {
				return is_array( $model ) && ! empty( $model['model_id'] ) && ! empty( $model['can_do_text_to_speech'] );
			} ) ),
			'voices' => array_values( array_filter( (array) ( $voices['voices'] ?? [] ), static function ( $voice ): bool {
				return is_array( $voice ) && ! empty( $voice['voice_id'] );
			} ) ),
		];
	}

	/** GET a JSON API endpoint. */
	private static function json_get( string $url, array $headers ) {
		$response = wp_remote_get( $url, [ 'timeout' => 30, 'headers' => $headers ] );
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'elevenlabs_unreachable', $response->get_error_message() );
		}
		$code = wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_body( $response );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'elevenlabs_request_failed', self::error_message( $body, $code ), [ 'status' => $code ] );
		}
		$decoded = json_decode( $body, true );
		return is_array( $decoded ) ? $decoded : new WP_Error( 'elevenlabs_invalid_response', __( 'ElevenLabs returned invalid JSON.', 'storyos' ) );
	}

	/** Build authenticated JSON headers. */
	private static function headers( string $credential_reference ) {
		$key = self::resolve_credential( $credential_reference );
		if ( '' === $key ) {
			return new WP_Error( 'elevenlabs_credential_missing', __( 'Set an ElevenLabs API key or env://ELEVENLABS_API_KEY reference on this Connection.', 'storyos' ) );
		}
		return [ 'Accept' => 'application/json', 'Content-Type' => 'application/json', 'xi-api-key' => $key ];
	}

	/** Resolve a literal key or env:// environment-variable reference. */
	private static function resolve_credential( string $reference ): string {
		$reference = trim( $reference );
		if ( 0 === strpos( $reference, 'env://' ) ) {
			$name = substr( $reference, 6 );
			if ( ! preg_match( '/^[A-Z_][A-Z0-9_]*$/', $name ) ) {
				return '';
			}
			$value = getenv( $name );
			return false === $value ? '' : trim( (string) $value );
		}
		return $reference;
	}

	/** Normalize a configured endpoint to ElevenLabs API v1. */
	private static function v1_endpoint( string $endpoint ): string {
		return self::api_root( $endpoint ) . '/v1';
	}

	/** Normalize a configured endpoint to the API origin. */
	private static function api_root( string $endpoint ): string {
		$endpoint = untrailingslashit( esc_url_raw( $endpoint ?: self::ENDPOINT ) );
		return (string) preg_replace( '#/v1$#', '', $endpoint );
	}

	/** Extract an API error without exposing credentials or binary content. */
	private static function error_message( string $body, int $code ): string {
		$decoded = json_decode( $body, true );
		$message = is_array( $decoded ) ? ( $decoded['detail']['message'] ?? $decoded['detail'] ?? $decoded['message'] ?? '' ) : '';
		return is_string( $message ) && '' !== $message ? $message : sprintf( 'ElevenLabs returned HTTP %d.', $code );
	}

	/** Infer a response mime from an ElevenLabs output-format identifier. */
	private static function mime_for_format( string $format ): string {
		if ( 0 === strpos( $format, 'wav_' ) ) {
			return 'audio/wav';
		}
		if ( 0 === strpos( $format, 'pcm_' ) ) {
			return 'audio/L16';
		}
		if ( 0 === strpos( $format, 'ulaw_' ) ) {
			return 'audio/basic';
		}
		return 'audio/mpeg';
	}
}
