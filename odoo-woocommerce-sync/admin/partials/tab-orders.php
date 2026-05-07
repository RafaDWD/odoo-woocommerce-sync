<?php
if (!defined('ABSPATH')) {
    exit;
}

$settings = get_option('odoo_woocommerce_sync_settings', []);
?>
<input type="hidden" name="odoo_woocommerce_sync_settings[_active_tab]" value="orders" />
<?php
$status_map = $settings['order_status_map'] ?? [
    'pending' => 'draft',
    'processing' => 'sale',
    'completed' => 'done',
    'cancelled' => 'cancel',
    'refunded' => 'cancel',
];
?>
<table class="form-table" role="presentation">
    <tr>
        <th><?php esc_html_e('Habilitar', 'odoo-woocommerce-sync'); ?></th>
        <td><label><input type="checkbox" name="odoo_woocommerce_sync_settings[sync_orders]" value="1" <?php checked(!empty($settings['sync_orders'])); ?> /> <?php esc_html_e('Ativar sync de pedidos', 'odoo-woocommerce-sync'); ?></label></td>
    </tr>
    <tr>
        <th><?php esc_html_e('Mapeamento de status', 'odoo-woocommerce-sync'); ?></th>
        <td>
            <?php foreach ($status_map as $wc_status => $odoo_status) : ?>
                <label><?php echo esc_html($wc_status); ?> -> </label>
                <input type="text" name="odoo_woocommerce_sync_settings[order_status_map][<?php echo esc_attr($wc_status); ?>]" value="<?php echo esc_attr($odoo_status); ?>" /><br />
            <?php endforeach; ?>
        </td>
    </tr>
    <tr>
        <th><?php esc_html_e('Exportar cliente com pedido', 'odoo-woocommerce-sync'); ?></th>
        <td><label><input type="checkbox" name="odoo_woocommerce_sync_settings[export_customer_with_order]" value="1" <?php checked(!empty($settings['export_customer_with_order'])); ?> /> <?php esc_html_e('Sim', 'odoo-woocommerce-sync'); ?></label></td>
    </tr>
</table>
<p><button type="button" class="button button-secondary odoo-run-sync" data-sync="orders"><?php esc_html_e('Sincronizar Pedidos Agora', 'odoo-woocommerce-sync'); ?></button></p>
