<?php
/**
 * includes/config-crypto.php
 *
 * Two-way AES-256-CBC encryption / decryption for the HA token.
 *
 * WHY AES AND NOT MD5
 * -------------------
 * MD5 is a one-way hash — once applied the original value cannot be
 * recovered. Because we need to restore the token when importing a
 * config file, we need reversible (symmetric) encryption instead.
 *
 * KEY DERIVATION
 * --------------
 * The encryption key is derived from WordPress's own AUTH_KEY constant
 * (defined in wp-config.php). This means:
 *   - The key is unique to each WordPress installation.
 *   - No extra key needs to be stored anywhere.
 *   - A config YAML copied to a *different* WordPress install will NOT
 *     decrypt correctly — the user will need to re-enter their token
 *     on import, which is the correct and safe behaviour.
 *
 * STORAGE FORMAT
 * --------------
 * Encrypted values are stored as:  ENC:<base64-encoded-payload>
 * The payload itself is:           <16-byte IV> + <ciphertext>
 * This prefix lets the importer know the value needs decrypting.
 *
 * FALLBACK
 * --------
 * If the openssl extension is not available, the token is stored as a
 * masked placeholder string so the YAML is still valid and importable
 * (the user will need to re-enter the token on import).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// -------------------------------------------------------
// Derive a 32-byte AES key from the WordPress AUTH_KEY
// -------------------------------------------------------
function ha_pf_crypto_key() {
    $source = defined( 'AUTH_KEY' ) ? AUTH_KEY : wp_salt( 'auth' );
    // SHA-256 always produces exactly 32 bytes — perfect for AES-256
    return hash( 'sha256', $source, true );
}

// -------------------------------------------------------
// Encrypt a plaintext string
// Returns:  'ENC:<base64>' on success
//           'ENC_UNAVAILABLE' if openssl is missing
// -------------------------------------------------------
function ha_pf_encrypt( $plaintext ) {
    if ( ! function_exists( 'openssl_encrypt' ) ) {
        return 'ENC_UNAVAILABLE';
    }

    $key    = ha_pf_crypto_key();
    $iv     = openssl_random_pseudo_bytes( 16 );   // 128-bit IV for CBC
    $cipher = openssl_encrypt( $plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

    if ( $cipher === false ) {
        return 'ENC_UNAVAILABLE';
    }

    // Prepend IV so decrypt() can recover it without storing it separately
    return 'ENC:' . base64_encode( $iv . $cipher );
}

// -------------------------------------------------------
// Decrypt a value previously produced by ha_pf_encrypt()
// Returns the original plaintext, or empty string on failure.
// -------------------------------------------------------
function ha_pf_decrypt( $stored ) {
    if ( ! function_exists( 'openssl_decrypt' ) ) {
        return '';
    }

    // Must start with our marker prefix
    if ( strpos( $stored, 'ENC:' ) !== 0 ) {
        return '';
    }

    $payload = base64_decode( substr( $stored, 4 ), true );
    if ( $payload === false || strlen( $payload ) < 17 ) {
        return '';
    }

    $key    = ha_pf_crypto_key();
    $iv     = substr( $payload, 0, 16 );
    $cipher = substr( $payload, 16 );

    $plain = openssl_decrypt( $cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

    return ( $plain !== false ) ? $plain : '';
}

// -------------------------------------------------------
// Helper: is a stored value an encrypted blob?
// Used by the future import routine to decide whether to decrypt.
// -------------------------------------------------------
function ha_pf_is_encrypted( $value ) {
    return is_string( $value ) && strpos( $value, 'ENC:' ) === 0;
}
