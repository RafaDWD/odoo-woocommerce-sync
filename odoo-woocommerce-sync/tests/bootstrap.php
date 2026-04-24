<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/stubs/wordpress-stubs.php';

// Constantes mínimas usadas pelo plugin
if (!defined('ABSPATH'))              define('ABSPATH', '/');
if (!defined('DWD_OWS_TEXT_DOMAIN'))  define('DWD_OWS_TEXT_DOMAIN', 'odoo-woocommerce-sync');
if (!defined('DWD_OWS_VERSION'))      define('DWD_OWS_VERSION', '1.0.0');
if (!defined('DWD_OWS_PLUGIN_DIR'))   define('DWD_OWS_PLUGIN_DIR', dirname(__DIR__) . '/');
if (!defined('AUTH_KEY'))             define('AUTH_KEY', 'test-auth-key-32-bytes-for-test!!');
if (!defined('SECURE_AUTH_KEY'))      define('SECURE_AUTH_KEY', 'test-secure-auth-key-32bytes!!!');
if (!defined('MINUTE_IN_SECONDS'))    define('MINUTE_IN_SECONDS', 60);
if (!defined('DAY_IN_SECONDS'))       define('DAY_IN_SECONDS', 86400);
if (!defined('OPENSSL_RAW_DATA'))     define('OPENSSL_RAW_DATA', 1);

// Carrega as classes do plugin
require_once DWD_OWS_PLUGIN_DIR . 'includes/class-odoo-api.php';
require_once DWD_OWS_PLUGIN_DIR . 'includes/class-sync-logger.php';
require_once DWD_OWS_PLUGIN_DIR . 'includes/class-sync-customers.php';
require_once DWD_OWS_PLUGIN_DIR . 'includes/class-translation-service.php';
require_once DWD_OWS_PLUGIN_DIR . 'includes/class-sync-products.php';
require_once DWD_OWS_PLUGIN_DIR . 'includes/class-sync-orders.php';
require_once DWD_OWS_PLUGIN_DIR . 'includes/class-sync-stock.php';
