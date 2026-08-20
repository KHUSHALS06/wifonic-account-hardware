<?php
/**
 * bcrypt_compat.php
 *
 * PHP 5.4 does not have password_hash()/password_verify() (added in PHP 5.5).
 * This file provides bcrypt hashing using crypt(), which IS available in 5.4,
 * so hashes stay fully compatible with index.php's existing crypt()-based
 * verification. Include this once (e.g. from auth.php) and use
 * bcrypt_hash() / bcrypt_verify() anywhere password_hash()/password_verify()
 * would normally be used.
 *
 * Do NOT use md5()/sha1() as a substitute - they are not acceptable for
 * password storage.
 */

if (!function_exists('bcrypt_hash')) {

    function bcrypt_hash($password, $cost = 10) {

        if (!defined('CRYPT_BLOWFISH') || CRYPT_BLOWFISH != 1) {
            // Should not happen on any modern Linux/CentOS build, but fail
            // loudly rather than silently falling back to a weak hash.
            trigger_error('CRYPT_BLOWFISH is not supported on this PHP build.', E_USER_ERROR);
        }

        $cost = (int)$cost;

        if ($cost < 4) {
            $cost = 4;
        } elseif ($cost > 31) {
            $cost = 31;
        }

        $cost_str = str_pad($cost, 2, '0', STR_PAD_LEFT);

        // 16 random bytes -> 22-char base64-ish salt alphabet crypt() expects.
        if (function_exists('openssl_random_pseudo_bytes')) {

            $raw_salt = openssl_random_pseudo_bytes(16);

        } else {

            $raw_salt = '';

            for ($i = 0; $i < 16; $i++) {
                $raw_salt .= chr(mt_rand(0, 255));
            }
        }

        $salt = substr(
            strtr(base64_encode($raw_salt), '+', '.'),
            0,
            22
        );

        $hash = crypt($password, '$2y$' . $cost_str . '$' . $salt);

        if (!$hash || strlen($hash) < 60) {
            trigger_error('bcrypt_hash(): crypt() failed to produce a valid hash.', E_USER_ERROR);
        }

        return $hash;
    }
}


if (!function_exists('bcrypt_verify')) {

    function bcrypt_verify($password, $hash) {

        if (!is_string($hash) || $hash === '') {
            return false;
        }

        $check = crypt($password, $hash);

        if (!is_string($check) || strlen($check) !== strlen($hash)) {
            return false;
        }

        // Constant-time comparison (timing-safe), since hash_equals()
        // is only available from PHP 5.6+.
        $status = 0;

        for ($i = 0; $i < strlen($check); $i++) {
            $status |= (ord($check[$i]) ^ ord($hash[$i]));
        }

        return $status === 0;
    }
}
