<?php
namespace DWDOdooWooSync;

if (!defined('ABSPATH')) {
    exit;
}

require_once DWD_OWS_PLUGIN_DIR . 'includes/class-odoo-woocommerce-sync-loader.php';
require_once DWD_OWS_PLUGIN_DIR . 'includes/class-odoo-api.php';
require_once DWD_OWS_PLUGIN_DIR . 'includes/class-sync-products.php';
require_once DWD_OWS_PLUGIN_DIR . 'includes/class-sync-orders.php';
require_once DWD_OWS_PLUGIN_DIR . 'includes/class-sync-stock.php';
require_once DWD_OWS_PLUGIN_DIR . 'includes/class-sync-customers.php';
require_once DWD_OWS_PLUGIN_DIR . 'includes/class-sync-logger.php';
require_once DWD_OWS_PLUGIN_DIR . 'includes/class-translation-service.php';
require_once DWD_OWS_PLUGIN_DIR . 'admin/class-odoo-woocommerce-sync-admin.php';
require_once DWD_OWS_PLUGIN_DIR . 'public/class-odoo-woocommerce-sync-public.php';

class Odoo_WooCommerce_Sync
{
    protected $loader;
    protected $plugin_name = 'dwd-odoo-woocommerce-sync';
    protected $version = DWD_OWS_VERSION;
    protected $sync_products;
    protected $sync_orders;
    protected $sync_stock;
    protected $logger;

    public function __construct()
    {
        $this->loader = new Odoo_WooCommerce_Sync_Loader();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->define_i18n_hooks();
        $this->define_sync_hooks();
    }

    private function define_admin_hooks()
    {
        $plugin_admin = new Odoo_WooCommerce_Sync_Admin($this->plugin_name, $this->version);
        $this->loader->add_action('admin_menu', $plugin_admin, 'add_plugin_admin_menu');
        $this->loader->add_action('admin_init', $plugin_admin, 'register_settings');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
    }

    private function define_public_hooks()
    {
        $plugin_public = new Odoo_WooCommerce_Sync_Public($this->plugin_name, $this->version);
        $this->loader->add_action('init', $plugin_public, 'init');
    }

    private function define_i18n_hooks()
    {
        $this->loader->add_action('plugins_loaded', $this, 'load_plugin_textdomain');
    }

    private function define_sync_hooks()
    {
        $settings = get_option('odoo_woocommerce_sync_settings', []);
        $api = new OdooAPI([
            'url' => $settings['odoo_url'] ?? '',
            'db' => $settings['odoo_db'] ?? '',
            'username' => $settings['odoo_user'] ?? '',
            'password' => $this->decrypt_password((string) ($settings['odoo_password'] ?? '')),
            'company_id' => (int) ($settings['company_id'] ?? 0),
            'protocol' => $settings['protocol'] ?? 'jsonrpc',
        ]);
        $this->logger = new SyncLogger();
        $sync_products = new SyncProducts($api, $this->logger, new TranslationService());
        $sync_customers = new SyncCustomers($api, $this->logger);
        $sync_orders = new SyncOrders($api, $this->logger, $sync_customers);
        $sync_stock = new SyncStock($api, $this->logger, $sync_orders);
        $this->sync_products = $sync_products;
        $this->sync_orders = $sync_orders;
        $this->sync_stock = $sync_stock;

        $this->loader->add_filter('cron_schedules', $this, 'register_custom_schedules');
        $this->loader->add_action('woocommerce_update_product', $this->sync_products, 'on_woocommerce_update_product', 10, 1);
        $this->loader->add_action('save_post_product', $this->sync_products, 'on_save_post_product', 10, 3);
        $this->loader->add_action('woocommerce_order_status_changed', $this->sync_orders, 'on_order_status_changed', 10, 3);
        $this->loader->add_action('woocommerce_checkout_order_processed', $this->sync_orders, 'on_checkout_order_processed', 10, 1);
        $this->loader->add_action('rest_api_init', $this->sync_stock, 'register_webhook_endpoint');
        $this->loader->add_action('rest_api_init', $this, 'register_rest_routes');
        $this->loader->add_action('init', $this->sync_stock, 'schedule_stock_sync');
        $this->loader->add_action('odoo_sync_products_cron', $this, 'run_products_cron');
        $this->loader->add_action('odoo_sync_orders_cron', $this, 'run_orders_cron');
        $this->loader->add_action('odoo_sync_stock_cron', $this, 'run_stock_cron');
        $this->loader->add_action('odoo_cleanup_logs_cron', $this, 'run_cleanup_logs_cron');
        $this->loader->add_action('odoo_sync_stock_hourly', $this->sync_stock, 'update_woocommerce_stock_from_odoo');
        $this->loader->add_action('odoo_sync_stock_daily', $this->sync_stock, 'update_woocommerce_stock_from_odoo');
        $this->loader->add_action('woocommerce_order_status_processing', $this->sync_stock, 'on_order_status_processing', 10, 1);
    }

    public function load_plugin_textdomain()
    {
        load_plugin_textdomain(DWD_OWS_TEXT_DOMAIN, false, dirname(plugin_basename(DWD_OWS_PLUGIN_FILE)) . '/languages/');
    }

    public function run()
    {
        $this->loader->run();
    }

    public function get_stores(): array
    {
        $stores = get_option('odoo_sync_stores', []);
        return is_array($stores) ? array_slice($stores, 0, 10, true) : [];
    }

    public function run_sync_for_store(string $store_key): array
    {
        $stores = $this->get_stores();
        if (!isset($stores[$store_key]) || !is_array($stores[$store_key])) {
            return ['success' => false, 'message' => __('Loja nao encontrada.', DWD_OWS_TEXT_DOMAIN)];
        }

        $store = $stores[$store_key];
        $api = new OdooAPI([
            'url' => $store['odoo_url'] ?? '',
            'db' => $store['odoo_db'] ?? '',
            'username' => $store['odoo_user'] ?? '',
            'password' => $this->decrypt_password((string) ($store['odoo_password'] ?? '')),
            'company_id' => (int) ($store['company_id'] ?? 0),
            'protocol' => $store['protocol'] ?? 'jsonrpc',
        ]);

        $logger = new SyncLogger();
        $products = new SyncProducts($api, $logger, new TranslationService());
        $orders = new SyncOrders($api, $logger, new SyncCustomers($api, $logger));
        $stock = new SyncStock($api, $logger, $orders);

        $result = ['success' => true, 'store' => $store_key, 'products' => null, 'orders' => null, 'stock' => null];
        if (!empty($store['sync_products'])) {
            $result['products'] = $products->sync_from_odoo();
        }
        if (!empty($store['sync_orders'])) {
            $result['orders'] = $orders->import_orders_from_odoo();
        }
        if (!empty($store['sync_stock'])) {
            $result['stock'] = $stock->update_woocommerce_stock_from_odoo();
        }
        return $result;
    }

    public function register_custom_schedules(array $schedules): array
    {
        $schedules['odoo_sync_every_5_minutes'] = [
            'interval' => 5 * MINUTE_IN_SECONDS,
            'display' => __('Odoo Sync a cada 5 minutos', DWD_OWS_TEXT_DOMAIN),
        ];
        $schedules['odoo_sync_every_15_minutes'] = [
            'interval' => 15 * MINUTE_IN_SECONDS,
            'display' => __('Odoo Sync a cada 15 minutos', DWD_OWS_TEXT_DOMAIN),
        ];
        return $schedules;
    }

    public static function activate_cron_jobs(): void
    {
        if (!wp_next_scheduled('odoo_sync_products_cron')) {
            wp_schedule_event(time() + 60, 'odoo_sync_every_15_minutes', 'odoo_sync_products_cron');
        }
        if (!wp_next_scheduled('odoo_sync_orders_cron')) {
            wp_schedule_event(time() + 90, 'odoo_sync_every_5_minutes', 'odoo_sync_orders_cron');
        }
        if (!wp_next_scheduled('odoo_sync_stock_cron')) {
            wp_schedule_event(time() + 120, 'hourly', 'odoo_sync_stock_cron');
        }
        if (!wp_next_scheduled('odoo_cleanup_logs_cron')) {
            wp_schedule_event(time() + 300, 'daily', 'odoo_cleanup_logs_cron');
        }
    }

    public static function deactivate_cron_jobs(): void
    {
        wp_clear_scheduled_hook('odoo_sync_products_cron');
        wp_clear_scheduled_hook('odoo_sync_orders_cron');
        wp_clear_scheduled_hook('odoo_sync_stock_cron');
        wp_clear_scheduled_hook('odoo_cleanup_logs_cron');
    }

    public function run_products_cron(): void
    {
        if ($this->sync_products) {
            $this->sync_products->sync_from_odoo();
        }
    }

    public function run_orders_cron(): void
    {
        if ($this->sync_orders) {
            $this->sync_orders->import_orders_from_odoo();
        }
    }

    public function run_stock_cron(): void
    {
        if ($this->sync_stock) {
            $this->sync_stock->update_woocommerce_stock_from_odoo();
        }
    }

    public function run_cleanup_logs_cron(): void
    {
        if ($this->logger) {
            $this->logger->cleanup_old_logs(30);
        }
    }

    public function register_rest_routes(): void
    {
        register_rest_route('odoo-sync/v1', '/status', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_get_status'],
            'permission_callback' => [$this, 'rest_permission_check'],
        ]);
        register_rest_route('odoo-sync/v1', '/sync/products', [
            'methods' => 'POST',
            'callback' => [$this, 'rest_sync_products'],
            'permission_callback' => [$this, 'rest_permission_check'],
        ]);
        register_rest_route('odoo-sync/v1', '/sync/orders', [
            'methods' => 'POST',
            'callback' => [$this, 'rest_sync_orders'],
            'permission_callback' => [$this, 'rest_permission_check'],
        ]);
        register_rest_route('odoo-sync/v1', '/sync/stock', [
            'methods' => 'POST',
            'callback' => [$this, 'rest_sync_stock'],
            'permission_callback' => [$this, 'rest_permission_check'],
        ]);
        register_rest_route('odoo-sync/v1', '/logs', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_get_logs'],
            'permission_callback' => [$this, 'rest_permission_check'],
        ]);
    }

    public function rest_permission_check(\WP_REST_Request $request): bool
    {
        if (current_user_can('manage_options')) {
            return true;
        }
        $settings = get_option('odoo_woocommerce_sync_settings', []);
        $expected = (string) ($settings['rest_token'] ?? '');
        $provided = (string) $request->get_header('x-odoo-sync-token');
        return $expected !== '' && hash_equals($expected, $provided);
    }

    public function rest_get_status(): \WP_REST_Response
    {
        $stores = $this->get_stores();
        $store_status = [];
        foreach ($stores as $key => $store) {
            $store_status[$key] = [
                'name' => $store['name'] ?? $key,
                'sync_products' => !empty($store['sync_products']),
                'sync_orders' => !empty($store['sync_orders']),
                'sync_stock' => !empty($store['sync_stock']),
            ];
        }
        return new \WP_REST_Response(['stores' => $store_status], 200);
    }

    public function rest_sync_products(): \WP_REST_Response
    {
        return new \WP_REST_Response($this->sync_products ? $this->sync_products->sync_from_odoo() : ['error' => true], 200);
    }

    public function rest_sync_orders(): \WP_REST_Response
    {
        return new \WP_REST_Response($this->sync_orders ? $this->sync_orders->import_orders_from_odoo() : ['error' => true], 200);
    }

    public function rest_sync_stock(): \WP_REST_Response
    {
        return new \WP_REST_Response($this->sync_stock ? $this->sync_stock->update_woocommerce_stock_from_odoo() : ['error' => true], 200);
    }

    public function rest_get_logs(): \WP_REST_Response
    {
        $data = $this->logger ? $this->logger->get_logs([], 1, 50) : ['items' => []];
        return new \WP_REST_Response($data, 200);
    }

    private function decrypt_password(string $encrypted): string
    {
        if ($encrypted === '' || !function_exists('openssl_decrypt')) {
            return $encrypted;
        }
        $key = hash('sha256', (string) AUTH_KEY, true);
        $raw = base64_decode($encrypted, true);

        // Formato novo: 16 bytes de IV + ciphertext
        if ($raw !== false && strlen($raw) > 16) {
            $iv     = substr($raw, 0, 16);
            $cipher = substr($raw, 16);
            $plain  = openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
            if ($plain !== false) {
                return $plain;
            }
        }

        // Fallback: formato antigo (IV fixo) — migração transparente
        $iv_legacy = substr(hash('sha256', (string) SECURE_AUTH_KEY), 0, 16);
        $plain = openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv_legacy);
        return $plain !== false ? $plain : $encrypted;
    }
}
