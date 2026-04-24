<?php
namespace DWDOdooWooSync;

if (!defined('ABSPATH')) {
    exit;
}

class Odoo_WooCommerce_Sync_Admin
{
    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    public function enqueue_styles($hook)
    {
        if (strpos((string) $hook, 'odoo-woocommerce-sync') === false) {
            return;
        }
        wp_enqueue_style($this->plugin_name, DWD_OWS_PLUGIN_URL . 'admin/css/admin-style.css', [], $this->version);
    }

    public function enqueue_scripts($hook)
    {
        if (strpos((string) $hook, 'odoo-woocommerce-sync') === false) {
            return;
        }
        wp_enqueue_script($this->plugin_name, DWD_OWS_PLUGIN_URL . 'admin/js/admin-script.js', ['jquery'], $this->version, true);
        wp_localize_script($this->plugin_name, 'odooSyncAdmin', [
            'nonce' => wp_create_nonce('odoo_sync_nonce'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'messages' => [
                'testing' => __('Testando conexao...', DWD_OWS_TEXT_DOMAIN),
                'syncing' => __('Executando sincronizacao...', DWD_OWS_TEXT_DOMAIN),
            ],
        ]);
    }

    public function add_plugin_admin_menu()
    {
        add_menu_page(
            __('Odoo Sync', DWD_OWS_TEXT_DOMAIN),
            __('Odoo Sync', DWD_OWS_TEXT_DOMAIN),
            'manage_options',
            'odoo-woocommerce-sync',
            [$this, 'display_admin_page'],
            'dashicons-update'
        );

        add_submenu_page('odoo-woocommerce-sync', __('Dashboard', DWD_OWS_TEXT_DOMAIN), __('Dashboard', DWD_OWS_TEXT_DOMAIN), 'manage_options', 'odoo-woocommerce-sync', [$this, 'display_admin_page']);
        add_submenu_page('odoo-woocommerce-sync', __('Produtos', DWD_OWS_TEXT_DOMAIN), __('Produtos', DWD_OWS_TEXT_DOMAIN), 'manage_options', 'odoo-woocommerce-sync-products', [$this, 'display_products_page']);
        add_submenu_page('odoo-woocommerce-sync', __('Pedidos', DWD_OWS_TEXT_DOMAIN), __('Pedidos', DWD_OWS_TEXT_DOMAIN), 'manage_options', 'odoo-woocommerce-sync-orders', [$this, 'display_orders_page']);
        add_submenu_page('odoo-woocommerce-sync', __('Estoque', DWD_OWS_TEXT_DOMAIN), __('Estoque', DWD_OWS_TEXT_DOMAIN), 'manage_options', 'odoo-woocommerce-sync-stock', [$this, 'display_stock_page']);
        add_submenu_page('odoo-woocommerce-sync', __('Logs', DWD_OWS_TEXT_DOMAIN), __('Logs', DWD_OWS_TEXT_DOMAIN), 'manage_options', 'odoo-woocommerce-sync-logs', [$this, 'display_logs_page']);
        add_submenu_page('odoo-woocommerce-sync', __('Configuracoes', DWD_OWS_TEXT_DOMAIN), __('Configuracoes', DWD_OWS_TEXT_DOMAIN), 'manage_options', 'odoo-woocommerce-sync-settings', [$this, 'display_settings_page']);
    }

    public function register_settings()
    {
        register_setting('odoo_woocommerce_sync_settings_group', 'odoo_woocommerce_sync_settings', [$this, 'sanitize_settings']);
        add_action('wp_ajax_odoo_sync_test_connection', [$this, 'ajax_test_connection']);
        add_action('wp_ajax_odoo_sync_load_companies', [$this, 'ajax_load_companies']);
        add_action('wp_ajax_odoo_sync_run_manual_sync', [$this, 'ajax_run_manual_sync']);
        add_action('wp_ajax_odoo_sync_cleanup_logs', [$this, 'ajax_cleanup_logs']);
        add_action('wp_ajax_odoo_sync_export_logs', [$this, 'ajax_export_logs']);
        add_action('wp_ajax_odoo_sync_reprocess_failures', [$this, 'ajax_reprocess_failures']);
    }

    public function sanitize_settings($input)
    {
        $current = get_option('odoo_woocommerce_sync_settings', []);
        $output = is_array($current) ? $current : [];
        $input = is_array($input) ? $input : [];

        $output['odoo_url'] = esc_url_raw($input['odoo_url'] ?? '');
        $output['odoo_db'] = sanitize_text_field($input['odoo_db'] ?? '');
        $output['odoo_user'] = sanitize_text_field($input['odoo_user'] ?? '');
        $output['odoo_password'] = $this->encrypt_secret((string) ($input['odoo_password'] ?? ''), (string) ($output['odoo_password'] ?? ''));
        $output['company_id'] = absint($input['company_id'] ?? 0);
        $output['protocol'] = in_array(($input['protocol'] ?? 'jsonrpc'), ['xmlrpc', 'jsonrpc'], true) ? $input['protocol'] : 'jsonrpc';
        $output['sync_products'] = !empty($input['sync_products']) ? 1 : 0;
        $output['sync_orders'] = !empty($input['sync_orders']) ? 1 : 0;
        $output['sync_stock'] = !empty($input['sync_stock']) ? 1 : 0;
        $output['product_direction'] = in_array(($input['product_direction'] ?? 'odoo_to_wc'), ['odoo_to_wc', 'wc_to_odoo', 'bidirectional'], true) ? $input['product_direction'] : 'odoo_to_wc';
        $output['product_fields'] = $this->sanitize_checkbox_array($input['product_fields'] ?? [], ['name', 'description', 'price', 'sku', 'categories', 'attributes', 'images']);
        $output['translation_enabled'] = !empty($input['translation_enabled']) ? 1 : 0;
        $output['translation_service'] = in_array(($input['translation_service'] ?? 'google'), ['google', 'deepl'], true) ? $input['translation_service'] : 'google';
        $new_api_key = sanitize_text_field($input['translation_api_key'] ?? '');
        $output['translation_api_key'] = $new_api_key !== '' ? $new_api_key : (string) ($output['translation_api_key'] ?? '');
        $output['source_lang'] = sanitize_text_field($input['source_lang'] ?? 'auto');
        $output['target_lang'] = sanitize_text_field($input['target_lang'] ?? 'pt_BR');
        $output['order_status_map'] = $this->sanitize_status_map($input['order_status_map'] ?? []);
        $output['export_customer_with_order'] = !empty($input['export_customer_with_order']) ? 1 : 0;
        $output['stock_frequency'] = in_array(($input['stock_frequency'] ?? 'hourly'), ['realtime', 'hourly', 'daily'], true) ? $input['stock_frequency'] : 'hourly';
        $output['stock_location_id'] = sanitize_text_field($input['stock_location_id'] ?? '');

        return $output;
    }

    public function display_admin_page()
    {
        $_GET['tab'] = 'dashboard';
        include DWD_OWS_PLUGIN_DIR . 'admin/partials/admin-display.php';
    }

    public function display_products_page()
    {
        $_GET['tab'] = 'products';
        include DWD_OWS_PLUGIN_DIR . 'admin/partials/admin-display.php';
    }

    public function display_orders_page()
    {
        $_GET['tab'] = 'orders';
        include DWD_OWS_PLUGIN_DIR . 'admin/partials/admin-display.php';
    }

    public function display_stock_page()
    {
        $_GET['tab'] = 'stock';
        include DWD_OWS_PLUGIN_DIR . 'admin/partials/admin-display.php';
    }

    public function display_logs_page()
    {
        $_GET['tab'] = 'logs';
        include DWD_OWS_PLUGIN_DIR . 'admin/partials/admin-display.php';
    }

    public function display_settings_page()
    {
        $_GET['tab'] = 'credentials';
        include DWD_OWS_PLUGIN_DIR . 'admin/partials/admin-display.php';
    }

    public function ajax_test_connection()
    {
        check_ajax_referer('odoo_sync_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Acesso negado.', DWD_OWS_TEXT_DOMAIN)], 403);
        }

        $api = $this->build_api_from_settings();
        $result = $api->test_connection();
        if (!empty($result['success'])) {
            wp_send_json_success($result);
        }
        wp_send_json_error($result, 400);
    }

    public function ajax_load_companies()
    {
        check_ajax_referer('odoo_sync_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Acesso negado.', DWD_OWS_TEXT_DOMAIN)], 403);
        }

        try {
            $api = $this->build_api_from_settings();
            $api->authenticate();
            wp_send_json_success(['companies' => $api->get_companies()]);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()], 400);
        }
    }

    public function ajax_run_manual_sync()
    {
        check_ajax_referer('odoo_sync_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Acesso negado.', DWD_OWS_TEXT_DOMAIN)], 403);
        }

        $type  = sanitize_key($_POST['sync_type'] ?? '');
        $valid = ['products', 'orders', 'stock'];
        if (!in_array($type, $valid, true)) {
            wp_send_json_error(['message' => __('Tipo de sincronizacao invalido.', DWD_OWS_TEXT_DOMAIN)], 400);
        }

        $api    = $this->build_api_from_settings();
        $logger = new \DWDOdooWooSync\SyncLogger();

        try {
            switch ($type) {
                case 'products':
                    $sync   = new \DWDOdooWooSync\SyncProducts($api, $logger, new \DWDOdooWooSync\TranslationService($logger));
                    $result = $sync->sync_from_odoo();
                    break;

                case 'orders':
                    $customers = new \DWDOdooWooSync\SyncCustomers($api, $logger);
                    $orders    = new \DWDOdooWooSync\SyncOrders($api, $logger, $customers);
                    $result    = $orders->import_orders_from_odoo();
                    break;

                case 'stock':
                    $customers = new \DWDOdooWooSync\SyncCustomers($api, $logger);
                    $orders    = new \DWDOdooWooSync\SyncOrders($api, $logger, $customers);
                    $stock     = new \DWDOdooWooSync\SyncStock($api, $logger, $orders);
                    $result    = $stock->update_woocommerce_stock_from_odoo();
                    break;

                default:
                    $result = ['errors' => 1];
            }

            wp_send_json_success([
                'message'  => __('Sincronizacao concluida.', DWD_OWS_TEXT_DOMAIN),
                'result'   => $result,
                'progress' => [10, 30, 60, 90, 100],
            ]);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    public function ajax_cleanup_logs()
    {
        check_ajax_referer('odoo_sync_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Acesso negado.', DWD_OWS_TEXT_DOMAIN)], 403);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'odoo_sync_logs';
        $deleted = $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)", 30));
        wp_send_json_success(['message' => sprintf(__('Logs antigos removidos: %d', DWD_OWS_TEXT_DOMAIN), (int) $deleted)]);
    }

    public function ajax_export_logs()
    {
        // Verifica nonce e permissão antes de qualquer output
        if (!check_ajax_referer('odoo_sync_nonce', 'nonce', false) || !current_user_can('manage_options')) {
            status_header(403);
            exit('Acesso negado.');
        }

        $logger   = new \DWDOdooWooSync\SyncLogger();
        $data     = $logger->get_logs([], 1, 500);
        $logs     = $data['items'];
        $filename = 'odoo-sync-logs-' . gmdate('Ymd-His') . '.csv';

        // Envia diretamente como download — sem salvar arquivo público
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        $fh = fopen('php://output', 'w');
        fputcsv($fh, ['created_at', 'sync_type', 'direction', 'entity_id', 'status', 'message']);
        foreach ($logs as $row) {
            fputcsv($fh, [
                $row['created_at'] ?? '',
                $row['sync_type']  ?? '',
                $row['direction']  ?? '',
                $row['entity_id']  ?? '',
                $row['status']     ?? '',
                $row['message']    ?? '',
            ]);
        }
        fclose($fh);
        exit;
    }

    public function ajax_reprocess_failures()
    {
        check_ajax_referer('odoo_sync_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Acesso negado.', DWD_OWS_TEXT_DOMAIN)], 403);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'odoo_sync_logs';

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE status = %s AND reprocessed = %d ORDER BY created_at DESC LIMIT %d",
                'error', 0, 50
            ),
            ARRAY_A
        );

        if (empty($rows)) {
            wp_send_json_success(['message' => __('Nenhuma falha pendente para reprocessar.', DWD_OWS_TEXT_DOMAIN)]);
            return;
        }

        $log_ids = array_column($rows, 'id');
        $logger  = new \DWDOdooWooSync\SyncLogger();
        $result  = $logger->reprocess_failed($log_ids);

        wp_send_json_success([
            'message' => sprintf(
                __('Reprocessados: %1$d | Sucesso: %2$d | Falhas: %3$d', DWD_OWS_TEXT_DOMAIN),
                $result['processed'],
                $result['succeeded'],
                $result['failed']
            ),
            'details' => $result['messages'],
        ]);
    }

    private function sanitize_checkbox_array($values, array $allowed): array
    {
        $values = is_array($values) ? $values : [];
        $sanitized = [];
        foreach ($values as $value) {
            $key = sanitize_key($value);
            if (in_array($key, $allowed, true)) {
                $sanitized[] = $key;
            }
        }
        return array_values(array_unique($sanitized));
    }

    private function sanitize_status_map($map): array
    {
        $default = [
            'pending' => 'draft',
            'processing' => 'sale',
            'completed' => 'done',
            'cancelled' => 'cancel',
            'refunded' => 'cancel',
        ];
        if (!is_array($map)) {
            return $default;
        }
        foreach ($default as $wc => $odoo) {
            $default[$wc] = sanitize_text_field($map[$wc] ?? $odoo);
        }
        return $default;
    }

    private function encrypt_secret(string $new_value, string $current_encrypted): string
    {
        if ($new_value === '') {
            return $current_encrypted;
        }
        if (!function_exists('openssl_encrypt')) {
            return $new_value;
        }
        $key = hash('sha256', (string) AUTH_KEY, true);
        $iv  = random_bytes(16);
        $raw = openssl_encrypt($new_value, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($raw === false) {
            return $new_value;
        }
        return base64_encode($iv . $raw);
    }

    private function decrypt_secret(string $encrypted): string
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

        // Fallback: formato antigo (IV fixo) — migração transparente na próxima gravação
        $iv_legacy = substr(hash('sha256', (string) SECURE_AUTH_KEY), 0, 16);
        $plain = openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv_legacy);
        return $plain !== false ? $plain : $encrypted;
    }

    private function build_api_from_settings(): \DWDOdooWooSync\OdooAPI
    {
        $settings = get_option('odoo_woocommerce_sync_settings', []);
        return new \DWDOdooWooSync\OdooAPI([
            'url' => $settings['odoo_url'] ?? '',
            'db' => $settings['odoo_db'] ?? '',
            'username' => $settings['odoo_user'] ?? '',
            'password' => $this->decrypt_secret((string) ($settings['odoo_password'] ?? '')),
            'company_id' => (int) ($settings['company_id'] ?? 0),
            'protocol' => $settings['protocol'] ?? 'jsonrpc',
        ]);
    }
}
