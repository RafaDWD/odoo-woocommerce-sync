<?php
if (!defined('ABSPATH')) {
    exit;
}

$settings = get_option('odoo_woocommerce_sync_settings', []);
?>
<table class="form-table" role="presentation">
    <tr>
        <th><?php esc_html_e('Habilitar', 'odoo-woocommerce-sync'); ?></th>
        <td><label><input type="checkbox" name="odoo_woocommerce_sync_settings[sync_stock]" value="1" <?php checked(!empty($settings['sync_stock'])); ?> /> <?php esc_html_e('Ativar sync de estoque', 'odoo-woocommerce-sync'); ?></label></td>
    </tr>
    <tr>
        <th><?php esc_html_e('Frequencia', 'odoo-woocommerce-sync'); ?></th>
        <td>
            <select name="odoo_woocommerce_sync_settings[stock_frequency]">
                <option value="realtime" <?php selected(($settings['stock_frequency'] ?? ''), 'realtime'); ?>><?php esc_html_e('Tempo real (webhook)', 'odoo-woocommerce-sync'); ?></option>
                <option value="hourly" <?php selected(($settings['stock_frequency'] ?? 'hourly'), 'hourly'); ?>><?php esc_html_e('A cada hora', 'odoo-woocommerce-sync'); ?></option>
                <option value="daily" <?php selected(($settings['stock_frequency'] ?? ''), 'daily'); ?>><?php esc_html_e('Diariamente', 'odoo-woocommerce-sync'); ?></option>
            </select>
        </td>
    </tr>
    <tr>
        <th><?php esc_html_e('Stock Location ID (Odoo)', 'odoo-woocommerce-sync'); ?></th>
        <td><input type="text" name="odoo_woocommerce_sync_settings[stock_location_id]" value="<?php echo esc_attr($settings['stock_location_id'] ?? ''); ?>" /></td>
    </tr>
</table>
<p><button type="button" class="button button-secondary odoo-run-sync" data-sync="stock"><?php esc_html_e('Sincronizar Estoque Agora', 'odoo-woocommerce-sync'); ?></button></p>
