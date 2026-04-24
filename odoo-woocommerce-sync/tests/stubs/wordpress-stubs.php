<?php
/**
 * Stubs mínimos das funções WordPress para testes unitários sem WP instalado.
 */

function __($text, $domain = '') { return $text; }
function _e($text, $domain = '') { echo $text; }
function esc_html($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
function esc_attr($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
function sanitize_text_field($str) { return trim(strip_tags((string) $str)); }
function sanitize_email($email) { return filter_var((string) $email, FILTER_SANITIZE_EMAIL); }
function sanitize_key($str) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $str)); }
function absint($val) { return abs((int) $val); }

function get_option($key, $default = false) { return $default; }
function update_option($key, $value, $autoload = null) { return true; }
function add_option($key, $value = '', $deprecated = '', $autoload = 'yes') { return true; }
function delete_option($key) { return true; }

function get_post_meta($post_id, $key = '', $single = false) { return $single ? '' : []; }
function update_post_meta($post_id, $key, $value, $prev = '') { return true; }
function delete_post_meta($post_id, $key, $value = '') { return true; }

function update_user_meta($user_id, $key, $value, $prev = '') { return true; }
function get_user_meta($user_id, $key = '', $single = false) { return $single ? '' : []; }

function get_transient($transient) { return false; }
function set_transient($transient, $value, $expiration = 0) { return true; }
function delete_transient($transient) { return true; }

function wp_generate_uuid4() {
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

function wp_json_encode($data, $options = 0, $depth = 512) {
    return json_encode($data, $options, $depth);
}

function is_wp_error($thing) {
    return $thing instanceof WP_Error;
}

function wp_strip_all_tags($string, $remove_breaks = false) {
    return strip_tags((string) $string);
}

function wp_kses_post($string) {
    return (string) $string;
}

function wp_mkdir_p($dir) {
    return @mkdir($dir, 0755, true);
}

function trailingslashit($str) {
    return rtrim((string) $str, '/\\') . '/';
}

if (!class_exists('WP_Error')) {
    class WP_Error {
        private $code;
        private $message;
        public function __construct($code = '', $message = '', $data = '') {
            $this->code    = $code;
            $this->message = $message;
        }
        public function get_error_code() { return $this->code; }
        public function get_error_message($code = '') { return $this->message; }
    }
}
