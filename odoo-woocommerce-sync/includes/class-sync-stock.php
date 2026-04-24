<?php
namespace DWDOdooWooSync;

if (!defined('ABSPATH')) {
    exit;
}

class SyncStock
{
    protected $api;
    protected $logger;
    protected $orders_sync;
    protected $settings;

    public function __construct(OdooAPI $api, SyncLogger $logger, ?SyncOrders $orders_sync = null)
    {
        $this->api = $api;
        $this->logger = $logger;
        $this->orders_sync = $orders_sync;
        $this->settings = get_option('odoo_woocommerce_sync_settings', []);
    }

    public function update_woocommerce_stock_from_odoo(): array
    {
        $result = ['updated' => 0, 'errors' => 0];

        try {
            $query = new \WP_Query([
                'post_type' => 'product',
                'post_status' => 'any',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'meta_query' => [
                    [
                        'key' => '_odoo_product_id',
                        'compare' => 'EXISTS',
                    ],
                ],
            ]);

            $product_ids = array_map('intval', (array) $query->posts);
            $chunks = array_chunk($product_ids, 50);

            foreach ($chunks as $chunk) {
                $odoo_map = [];
                foreach ($chunk as $wc_product_id) {
                    $odoo_id = (int) get_post_meta($wc_product_id, '_odoo_product_id', true);
                    if ($odoo_id > 0) {
                        $odoo_map[$odoo_id] = $wc_product_id;
                    }
                }
                if (empty($odoo_map)) {
                    continue;
                }

                $domain = [['id', 'in', array_keys($odoo_map)]];
                $rows = $this->api->search_read('product.product', $domain, ['id', 'qty_available'], 200, 0);
                foreach ($rows as $row) {
                    try {
                        $odoo_id = (int) ($row['id'] ?? 0);
                        if ($odoo_id <= 0 || !isset($odoo_map[$odoo_id])) {
                            continue;
                        }
                        $wc_product_id = (int) $odoo_map[$odoo_id];
                        $qty = isset($row['qty_available']) ? (float) $row['qty_available'] : 0.0;
                        wc_update_product_stock($wc_product_id, $qty);
                        $result['updated']++;
                    } catch (\Throwable $e) {
                        $result['errors']++;
                        $this->logger->log('error', 'stock', 'from_odoo', (string) ($row['id'] ?? ''), $e->getMessage());
                    }
                }
            }

            $this->logger->log('success', 'stock', 'from_odoo', 'batch', __('Sincronizacao de estoque finalizada.', DWD_OWS_TEXT_DOMAIN), null, $result);
        } catch (\Throwable $e) {
            $result['errors']++;
            $this->logger->log('error', 'stock', 'from_odoo', 'batch', $e->getMessage());
        }

        return $result;
    }

    public function update_odoo_stock_from_woocommerce_sale(int $order_id): void
    {
        try {
            if (empty($this->settings['sync_stock'])) {
                return;
            }
            $odoo_order_id = (int) get_post_meta($order_id, '_odoo_order_id', true);
            if ($odoo_order_id > 0) {
                // O Odoo ajusta estoque ao confirmar pedido; aqui mantemos consistencia.
                $this->logger->log('success', 'stock', 'to_odoo', (string) $order_id, __('Pedido ja exportado ao Odoo, estoque sera consistente no lado Odoo.', DWD_OWS_TEXT_DOMAIN), (string) $odoo_order_id);
                return;
            }

            if ($this->orders_sync) {
                $this->orders_sync->export_order_to_odoo($order_id);
                $this->logger->log('warning', 'stock', 'to_odoo', (string) $order_id, __('Pedido nao exportado; exportacao forcada para manter consistencia de estoque.', DWD_OWS_TEXT_DOMAIN));
            }
        } catch (\Throwable $e) {
            $this->logger->log('error', 'stock', 'to_odoo', (string) $order_id, $e->getMessage());
        }
    }

    public function schedule_stock_sync(): void
    {
        $frequency = (string) ($this->settings['stock_frequency'] ?? 'hourly');

        wp_clear_scheduled_hook('odoo_sync_stock_hourly');
        wp_clear_scheduled_hook('odoo_sync_stock_daily');

        if ($frequency === 'realtime') {
            return;
        }

        if ($frequency === 'daily' && !wp_next_scheduled('odoo_sync_stock_daily')) {
            wp_schedule_event(time() + 60, 'daily', 'odoo_sync_stock_daily');
        }

        if ($frequency === 'hourly' && !wp_next_scheduled('odoo_sync_stock_hourly')) {
            wp_schedule_event(time() + 60, 'hourly', 'odoo_sync_stock_hourly');
        }
    }

    public function register_webhook_endpoint(): void
    {
        register_rest_route('odoo-sync/v1', '/stock-webhook', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_stock_webhook'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function handle_stock_webhook(\WP_REST_Request $request): \WP_REST_Response
    {
        $expected = (string) ($this->settings['webhook_token'] ?? '');

        if ($expected === '') {
            return new \WP_REST_Response(
                ['success' => false, 'message' => __('Webhook nao configurado. Defina um webhook_token nas configuracoes.', DWD_OWS_TEXT_DOMAIN)],
                403
            );
        }

        $token = (string) $request->get_header('x-odoo-sync-token');
        if (!hash_equals($expected, $token)) {
            return new \WP_REST_Response(
                ['success' => false, 'message' => __('Token invalido.', DWD_OWS_TEXT_DOMAIN)],
                403
            );
        }

        $product_id  = (int) $request->get_param('product_id');
        $new_qty     = (float) $request->get_param('new_qty');
        $location_id = (int) $request->get_param('location_id');

        if ($product_id <= 0) {
            return new \WP_REST_Response(
                ['success' => false, 'message' => __('product_id invalido.', DWD_OWS_TEXT_DOMAIN)],
                400
            );
        }

        $wc_product_id = $this->find_wc_product_by_odoo_id($product_id);
        if ($wc_product_id <= 0) {
            return new \WP_REST_Response(
                ['success' => false, 'message' => __('Produto WooCommerce nao encontrado.', DWD_OWS_TEXT_DOMAIN)],
                404
            );
        }

        wc_update_product_stock($wc_product_id, $new_qty);
        $this->logger->log(
            'success', 'stock', 'from_odoo', (string) $wc_product_id,
            __('Webhook de estoque processado.', DWD_OWS_TEXT_DOMAIN),
            (string) $product_id,
            ['location_id' => $location_id, 'new_qty' => $new_qty]
        );

        return new \WP_REST_Response(['success' => true], 200);
    }

    public function get_odoo_stock_location_qty(int $product_id, int $location_id): float
    {
        $cache_key = 'odoo_stock_qty_' . md5($product_id . ':' . $location_id);
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return (float) $cached;
        }

        try {
            $rows = $this->api->search_read(
                'stock.quant',
                [['product_id', '=', $product_id], ['location_id', '=', $location_id]],
                ['quantity'],
                1,
                0
            );
            $qty = !empty($rows[0]['quantity']) ? (float) $rows[0]['quantity'] : 0.0;
            set_transient($cache_key, $qty, 5 * MINUTE_IN_SECONDS);
            return $qty;
        } catch (\Throwable $e) {
            $this->logger->log('error', 'stock', 'from_odoo', (string) $product_id, $e->getMessage(), null, ['location_id' => $location_id]);
            return 0.0;
        }
    }

    public function on_order_status_processing(int $order_id): void
    {
        $this->update_odoo_stock_from_woocommerce_sale($order_id);
    }

    private function find_wc_product_by_odoo_id(int $odoo_product_id): int
    {
        $query = new \WP_Query([
            'post_type' => 'product',
            'post_status' => 'any',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => '_odoo_product_id',
                    'value' => $odoo_product_id,
                    'compare' => '=',
                ],
            ],
        ]);

        return !empty($query->posts[0]) ? (int) $query->posts[0] : 0;
    }
}
