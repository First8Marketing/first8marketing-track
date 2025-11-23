<?php
/**
 * Encryption Helper
 *
 * Provides encryption/decryption functions for sensitive data storage.
 * Uses WordPress's built-in salts for encryption key derivation.
 *
 * @package First8Marketing_Track
 * @since 1.0.0
 */

namespace First8Marketing\Track;

defined( 'ABSPATH' ) || exit;

/**
 * Encryption Helper Class
 */
class Encryption_Helper {

	/**
	 * Encrypt data using AES-256-CBC with PBKDF2 key derivation
	 *
	 * @param string $plaintext Data to encrypt.
	 * @return string Base64 encoded encrypted data, or empty string on failure.
	 */
	public static function encrypt( $plaintext ) {
		if ( empty( $plaintext ) ) {
			return '';
		}

		// Get WordPress salt for key derivation
		$salt = wp_salt( 'auth' );
		
		// Use PBKDF2 with 100,000 iterations for secure key derivation
		// This is MUCH more secure than single SHA256 hash
		$key = hash_pbkdf2( 'sha256', $salt, 'first8marketing_encryption', 100000, 32, true );
		
		// Generate random IV
		$iv_length = openssl_cipher_iv_length( 'aes-256-cbc' );
		$iv = openssl_random_pseudo_bytes( $iv_length );
		
		// Encrypt
		$encrypted = openssl_encrypt(
			$plaintext,
			'aes-256-cbc',
			$key,
			OPENSSL_RAW_DATA,
			$iv
		);

		if ( false === $encrypted ) {
			// Sanitized error message - no path disclosure
			error_log( 'First8Marketing: Encryption operation failed' );
			return '';
		}

		// Combine IV and encrypted data, then base64 encode
		return base64_encode( $iv . $encrypted );
	}

	/**
	 * Decrypt data using AES-256-CBC with PBKDF2 key derivation
	 *
	 * @param string $ciphertext Base64 encoded encrypted data.
	 * @return string Decrypted plaintext, or empty string on failure.
	 */
	public static function decrypt( $ciphertext ) {
		if ( empty( $ciphertext ) ) {
			return '';
		}

		// Decode from base64
		$data = base64_decode( $ciphertext, true );
		if ( false === $data ) {
			// Sanitized error message - no sensitive data disclosure
			error_log( 'First8Marketing: Invalid encrypted data format' );
			return '';
		}

		// Get WordPress salt for key derivation
		$salt = wp_salt( 'auth' );
		
		// Use PBKDF2 with 100,000 iterations (matches encrypt method)
		$key = hash_pbkdf2( 'sha256', $salt, 'first8marketing_encryption', 100000, 32, true );
		
		// Extract IV and encrypted data
		$iv_length = openssl_cipher_iv_length( 'aes-256-cbc' );
		$iv = substr( $data, 0, $iv_length );
		$encrypted = substr( $data, $iv_length );

		// Decrypt
		$decrypted = openssl_decrypt(
			$encrypted,
			'aes-256-cbc',
			$key,
			OPENSSL_RAW_DATA,
			$iv
		);

		if ( false === $decrypted ) {
			// Sanitized error message - no sensitive data or path disclosure
			error_log( 'First8Marketing: Decryption operation failed' );
			return '';
		}

		return $decrypted;
	}

	/**
	 * Migrate plain text value to encrypted value
	 *
	 * @param string $option_name Option name to migrate.
	 * @return bool True if migrated, false if already encrypted or failed.
	 */
	public static function migrate_to_encrypted( $option_name ) {
		$value = get_option( $option_name, '' );
		
		if ( empty( $value ) ) {
			return false;
		}

		// Check if already encrypted by trying to decrypt
		$test_decrypt = self::decrypt( $value );
		if ( ! empty( $test_decrypt ) && $test_decrypt !== $value ) {
			// Already encrypted
			return false;
		}

		// Encrypt and update
		$encrypted = self::encrypt( $value );
		if ( ! empty( $encrypted ) ) {
			update_option( $option_name, $encrypted );
			return true;
		}

		return false;
	}
}