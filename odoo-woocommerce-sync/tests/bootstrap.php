<?php
require_once __DIR__ . '/stubs/wordpress-stubs.php';

// Constantes mínimas usadas pelo plugin
if (!defined('ABSPATH'))              define('ABSPATH', '/');
if (!defined('DWD_OWS_TEXT_DOMAIN'))  define('DWD_OWS_TEXT_DOMAIN', 'odoo-woocommerce-sync');
if (!defined('DWD_OWS_VERSION'))      define('DWD_OWS_VERSION', '1.2.0');
if (!defined('DWD_OWS_PLUGIN_DIR'))   define('DWD_OWS_PLUGIN_DIR', dirname(__DIR__) . '/');
if (!defined('AUTH_KEY'))             define('AUTH_KEY', 'test-auth-key-32-bytes-for-test!!');
if (!defined('SECURE_AUTH_KEY'))      define('SECURE_AUTH_KEY', 'test-secure-auth-key-32bytes!!!');
if (!defined('MINUTE_IN_SECONDS'))    define('MINUTE_IN_SECONDS', 60);
if (!defined('DAY_IN_SECONDS'))       define('DAY_IN_SECONDS', 86400);
if (!defined('OPENSSL_RAW_DATA'))     define('OPENSSL_RAW_DATA', 1);

// Stub global wpdb (mínimo) para evitar warnings em testes que instanciam SyncLogger.
global $wpdb;
if (!isset($wpdb) || $wpdb === null) {
    $wpdb = new class {
        public $prefix = 'wp_';
        public function prepare($query, ...$args): string { return (string) $query; }
        public function insert($table, $data, $format = null): int { return 1; }
        public function get_var($query) { return '0'; }
        public function get_results($query, $output = ARRAY_A) { return []; }
        public function get_row($query, $output = ARRAY_A) { return null; }
        public function query($query) { return 0; }
        public function update($table, $data, $where, $format = null, $where_format = null) { return 1; }
        public function get_charset_collate(): string { return 'DEFAULT CHARACTER SET utf8mb4'; }
    };
}

// Carrega as classes do plugin
require_once DWD_OWS_PLUGIN_DIR . 'includes/class-odoo-api.php';
require_once DWD_OWS_PLUGIN_DIR . 'includes/class-sync-logger.php';
require_once DWD_OWS_PLUGIN_DIR . 'includes/class-sync-customers.php';
require_once DWD_OWS_PLUGIN_DIR . 'includes/class-translation-service.php';
require_once DWD_OWS_PLUGIN_DIR . 'includes/class-sync-products.php';
require_once DWD_OWS_PLUGIN_DIR . 'includes/class-sync-orders.php';
require_once DWD_OWS_PLUGIN_DIR . 'includes/class-sync-stock.php';
