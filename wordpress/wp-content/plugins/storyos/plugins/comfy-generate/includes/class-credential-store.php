<?php
/**
 * Encrypted provider credential storage for the StoryOS Generation Engine.
 *
 * @package StoryOSGenerationEngine
 */

namespace StoryOSGenerationEngine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores provider credentials encrypted in WordPress options.
 */
class Credential_Store {

	/**
	 * WordPress option containing encrypted credentials.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'storyos_generation_engine_credentials';

	/**
	 * Resolve the encryption key from outside WordPress database storage.
	 *
	 * @return string
	 */
	private static function get_key(): string {
		$key = getenv( 'STORYOS_CREDENTIAL_ENCRYPTION_KEY' );
		if ( false === $key || '' === $key ) {
			return '';
		}

		$decoded = base64_decode( $key, true );
		return false !== $decoded ? $decoded : $key;
	}

	/**
	 * Store an encrypted credential for a connection.
	 *
	 * @param int    $connection_id Connection identifier.
	 * @param string $credential Raw credential value.
	 * @return bool
	 */
	public static function store( int $connection_id, string $credential ): bool {
		$key = self::get_key();
		if ( $connection_id < 1 || '' === $credential || 32 !== strlen( $key ) || ! function_exists( 'sodium_crypto_secretbox' ) ) {
			return false;
		}

		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$ciphertext = sodium_crypto_secretbox( $credential, $nonce, $key );
		$credentials = get_option( self::OPTION_NAME, [] );
		if ( ! is_array( $credentials ) ) {
			$credentials = [];
		}

		$credentials[ (string) $connection_id ] = [
			'ciphertext' => base64_encode( $ciphertext ),
			'nonce'      => base64_encode( $nonce ),
			'updated_at' => current_time( 'mysql', true ),
		];

		return update_option( self::OPTION_NAME, $credentials, false );
	}

	/**
	 * Resolve a credential at runtime.
	 *
	 * @param int $connection_id Connection identifier.
	 * @return string
	 */
	public static function resolve( int $connection_id ): string {
		$key = self::get_key();
		$credentials = get_option( self::OPTION_NAME, [] );
		$record = is_array( $credentials ) ? ( $credentials[ (string) $connection_id ] ?? [] ) : [];
		if ( '' === $key || 32 !== strlen( $key ) || empty( $record['ciphertext'] ) || empty( $record['nonce'] ) ) {
			return '';
		}

		try {
			return sodium_crypto_secretbox_open(
			base64_decode( $record['ciphertext'], true ),
			base64_decode( $record['nonce'], true ),
			$key
		) ?: '';
		} catch ( \Throwable $error ) {
			return '';
		}
	}

	/**
	 * Return non-secret credential metadata.
	 *
	 * @param int $connection_id Connection identifier.
	 * @return array
	 */
	public static function get_metadata( int $connection_id ): array {
		$credentials = get_option( self::OPTION_NAME, [] );
		$record = is_array( $credentials ) ? ( $credentials[ (string) $connection_id ] ?? [] ) : [];

		return [
			'configured' => ! empty( $record['ciphertext'] ) && ! empty( $record['nonce'] ),
			'updated_at' => sanitize_text_field( (string) ( $record['updated_at'] ?? '' ) ),
		];
	}
}
