<?php
namespace DWDOdooWooSync;

if (!defined('ABSPATH')) {
    exit;
}

class SyncOrders
{
    protected $api;
    protected $logger;
    protected $customers_sync;

    public function __construct(OdooAPI $api, SyncLogger $logger, ?SyncCustomers $customers_sync = null)
    {
        $this->api = $api;
        $this->logger = $logger;
        $this->customers_sync = $customers_sync ?: new SyncCustomers($api, $logger);
    }

    public function export_order_to_odoo(int $wc_order_id)
    {
        try {
            $order = wc_get_order($wc_order_id);
            if (!$order) {
                throw new \RuntimeException(__('Pedido WooCommerce nao encontrado.', DWD_OWS_TEXT_DOMAIN));
            }

            $partner_id = $this->customers_sync->sync_customer($order);
            if ($partner_id <= 0) {
                throw new \RuntimeException(__('Nao foi possivel sincronizar cliente antes do pedido.', DWD_OWS_TEXT_DOMAIN));
            }

            $order_line = [];
            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                if (!$product) {
                    continue;
                }
                $odoo_product_id = (int) get_post_meta($product->get_id(), '_odoo_product_id', true);
                if ($odoo_product_id <= 0) {
                    continue;
                }

                $order_line[] = [0, 0, [
                    'product_id' => $odoo_product_id,
                    'product_uom_qty' => (float) $item->get_quantity(),
                    'price_unit' => (float) $item->get_total(),
                    'name' => (string) $item->get_name(),
                    'tax_id' => [[6, 0, []]],
                ]];
            }

            $settings = get_option('odoo_woocommerce_sync_settings', []);
            $payload = [
                'partner_id' => $partner_id,
                'company_id' => (int) ($settings['company_id'] ?? 0),
                'client_order_ref' => (string) $order->get_order_number(),
                'date_order' => gmdate('c', $order->get_date_created() ? $order->get_date_created()->getTimestamp() : time()),
                'order_line' => $order_line,
                'note' => (string) $order->get_customer_note(),
            ];

            $odoo_order_id = $this->api->create('sale.order', $payload);
            if ($odoo_order_id <= 0) {
                throw new \RuntimeException(__('Falha ao criar pedido no Odoo.', DWD_OWS_TEXT_DOMAIN));
            }

            if ($order->has_status('processing')) {
                $this->api->call('sale.order', 'action_confirm', [[$odoo_order_id]]);
            }

            update_post_meta($wc_order_id, '_odoo_order_id', $odoo_order_id);
            update_post_meta($wc_order_id, '_odoo_order_name', 'SO' . $odoo_order_id);
            $this->logger->log('success', 'order', 'to_odoo', (string) $wc_order_id, __('Pedido exportado para Odoo.', DWD_OWS_TEXT_DOMAIN), (string) $odoo_order_id, $payload);

            return $odoo_order_id;
        } catch (\Throwable $e) {
            $this->logger->log('error', 'order', 'to_odoo', (string) $wc_order_id, $e->getMessage());
            return false;
        }
    }

    public function sync_order_status(int $wc_order_id, string $new_status): void
    {
        try {
            $odoo_order_id = (int) get_post_meta($wc_order_id, '_odoo_order_id', true);
            if ($odoo_order_id <= 0) {
                return;
            }

            $map = [
                'processing' => 'action_confirm',
                'completed' => 'action_done',
                'cancelled' => 'action_cancel',
            ];
            if (!isset($map[$new_status])) {
                return;
            }

            $this->api->call('sale.order', $map[$new_status], [[$odoo_order_id]]);
            $this->logger->log('success', 'order', 'to_odoo', (string) $wc_order_id, __('Status do pedido sincronizado com Odoo.', DWD_OWS_TEXT_DOMAIN), (string) $odoo_order_id, ['new_status' => $new_status]);
        } catch (\Throwable $e) {
            $this->logger->log('error', 'order', 'to_odoo', (string) $wc_order_id, $e->getMessage(), null, ['new_status' => $new_status]);
        }
    }

    public function import_orders_from_odoo(): array
    {
        $result = ['created' => 0, 'errors' => 0];
        try {
            $orders = $this->api->search_read('sale.order', [['state', 'in', ['sale', 'done']]], ['id', 'name', 'amount_total', 'date_order'], 20, 0);
            foreach ($orders as $odoo_order) {
                try {
                    $odoo_id = (int) ($odoo_order['id'] ?? 0);
                    if ($odoo_id <= 0 || $this->order_exists_by_odoo_id($odoo_id)) {
                        continue;
                    }

                    $wc_order = wc_create_order();
                    $wc_order->set_customer_note(sprintf(__('Importado do Odoo: %s', DWD_OWS_TEXT_DOMAIN), (string) ($odoo_order['name'] ?? '')));
                    $wc_order->add_order_note(sprintf(__('Pedido Odoo #%d importado.', DWD_OWS_TEXT_DOMAIN), $odoo_id));
                    $wc_order->save();
                    update_post_meta($wc_order->get_id(), '_odoo_order_id', $odoo_id);
                    update_post_meta($wc_order->get_id(), '_odoo_order_name', (string) ($odoo_order['name'] ?? ''));
                    $result['created']++;
                } catch (\Throwable $e) {
                    $result['errors']++;
                    $this->logger->log('error', 'order', 'from_odoo', (string) ($odoo_order['id'] ?? ''), $e->getMessage());
                }
            }
        } catch (\Throwable $e) {
            $result['errors']++;
            $this->logger->log('error', 'order', 'from_odoo', 'batch', $e->getMessage());
        }

        return $result;
    }

    public function on_order_status_changed(int $order_id, string $old_status, string $new_status): void
    {
        $this->sync_order_status($order_id, $new_status);
    }

    public function on_checkout_order_processed(int $order_id): void
    {
        $this->export_order_to_odoo($order_id);
    }

    private function order_exists_by_odoo_id(int $odoo_order_id): bool
    {
        $query = new \WP_Query([
            'post_type' => wc_get_order_types(),
            'post_status' => array_keys(wc_get_order_statuses()),
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => '_odoo_order_id',
                    'value' => $odoo_order_id,
                    'compare' => '=',
                ],
            ],
        ]);
        return !empty($query->posts);
    }
}
