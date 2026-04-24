<?php
namespace DWDOdooWooSync;

if (!defined('ABSPATH')) {
    exit;
}

class SyncCustomers
{
    protected $api;
    protected $logger;

    public function __construct(OdooAPI $api, SyncLogger $logger)
    {
        $this->api = $api;
        $this->logger = $logger;
    }

    public function sync_customer(\WC_Order $order): int
    {
        try {
            $email = sanitize_email((string) $order->get_billing_email());
            if ($email === '') {
                throw new \RuntimeException(__('Pedido sem e-mail do cliente.', DWD_OWS_TEXT_DOMAIN));
            }

            $partner_id = 0;
            $existing = $this->api->search_read('res.partner', [['email', '=', $email]], ['id', 'email'], 1, 0);
            if (!empty($existing[0]['id'])) {
                $partner_id = (int) $existing[0]['id'];
            }

            $country_code = (string) $order->get_billing_country();
            $country_id = $country_code !== '' ? $this->get_country_id($country_code) : 0;
            $company_id = (int) (get_option('odoo_woocommerce_sync_settings', [])['company_id'] ?? 0);

            $values = [
                'name' => trim((string) $order->get_formatted_billing_full_name()),
                'email' => $email,
                'phone' => (string) $order->get_billing_phone(),
                'street' => trim((string) $order->get_billing_address_1() . ' ' . (string) $order->get_billing_address_2()),
                'city' => (string) $order->get_billing_city(),
                'zip' => (string) $order->get_billing_postcode(),
                'customer_rank' => 1,
            ];

            if ($country_id > 0) {
                $values['country_id'] = $country_id;
            }
            if ($company_id > 0) {
                $values['company_id'] = $company_id;
            }

            if ($partner_id > 0) {
                $this->api->write('res.partner', [$partner_id], $values);
            } else {
                $partner_id = $this->api->create('res.partner', $values);
            }

            if ($partner_id <= 0) {
                throw new \RuntimeException(__('Nao foi possivel sincronizar cliente no Odoo.', DWD_OWS_TEXT_DOMAIN));
            }

            $customer_user_id = (int) $order->get_customer_id();
            if ($customer_user_id > 0) {
                update_user_meta($customer_user_id, '_odoo_partner_id', $partner_id);
            }

            $this->logger->log('success', 'customer', 'to_odoo', (string) $order->get_id(), __('Cliente sincronizado com sucesso.', DWD_OWS_TEXT_DOMAIN), (string) $partner_id, ['email' => $email]);
            return $partner_id;
        } catch (\Throwable $e) {
            $this->logger->log('error', 'customer', 'to_odoo', (string) $order->get_id(), $e->getMessage());
            return 0;
        }
    }

    public function get_country_id(string $country_code): int
    {
        $country_code = strtoupper(sanitize_text_field($country_code));
        if ($country_code === '') {
            return 0;
        }

        $cache_key = 'odoo_country_id_' . md5($country_code);
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return (int) $cached;
        }

        try {
            $rows = $this->api->search_read('res.country', [['code', '=', $country_code]], ['id', 'code'], 1, 0);
            $country_id = !empty($rows[0]['id']) ? (int) $rows[0]['id'] : 0;
            set_transient($cache_key, $country_id, DAY_IN_SECONDS);
            return $country_id;
        } catch (\Throwable $e) {
            $this->logger->log('warning', 'customer', 'to_odoo', $country_code, $e->getMessage());
            return 0;
        }
    }
}
