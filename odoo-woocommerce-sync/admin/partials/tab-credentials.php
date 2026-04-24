<?php
if (!defined('ABSPATH')) {
    exit;
}
$settings = get_option('odoo_woocommerce_sync_settings', []);
?>
<table class="form-table" role="presentation">
    <tr>
        <th><label for="odoo_url"><?php esc_html_e('URL do Odoo', 'odoo-woocommerce-sync'); ?></label></th>
        <td><input class="regular-text" id="odoo_url" name="odoo_woocommerce_sync_settings[odoo_url]" value="<?php echo esc_attr($settings['odoo_url'] ?? ''); ?>" /></td>
    </tr>
    <tr>
        <th><label for="odoo_db"><?php esc_html_e('Banco de Dados', 'odoo-woocommerce-sync'); ?></label></th>
        <td><input class="regular-text" id="odoo_db" name="odoo_woocommerce_sync_settings[odoo_db]" value="<?php echo esc_attr($settings['odoo_db'] ?? ''); ?>" /></td>
    </tr>
    <tr>
        <th><label for="odoo_user"><?php esc_html_e('Usuario', 'odoo-woocommerce-sync'); ?></label></th>
        <td><input class="regular-text" id="odoo_user" name="odoo_woocommerce_sync_settings[odoo_user]" value="<?php echo esc_attr($settings['odoo_user'] ?? ''); ?>" /></td>
    </tr>
    <tr>
        <th><label for="odoo_password"><?php esc_html_e('Senha', 'odoo-woocommerce-sync'); ?></label></th>
        <td><input class="regular-text" type="password" id="odoo_password" name="odoo_woocommerce_sync_settings[odoo_password]" value="" placeholder="<?php esc_attr_e('Digite para atualizar a senha', 'odoo-woocommerce-sync'); ?>" /></td>
    </tr>
    <tr>
        <th><label for="company_id"><?php esc_html_e('Empresa (Company ID)', 'odoo-woocommerce-sync'); ?></label></th>
        <td>
            <select id="company_id" name="odoo_woocommerce_sync_settings[company_id]" data-selected="<?php echo esc_attr((string) ($settings['company_id'] ?? 0)); ?>">
                <option value="0"><?php esc_html_e('Selecione', 'odoo-woocommerce-sync'); ?></option>
            </select>
            <button type="button" class="button" id="odoo-load-companies"><?php esc_html_e('Carregar empresas', 'odoo-woocommerce-sync'); ?></button>
        </td>
    </tr>
    <tr>
        <th><?php esc_html_e('Protocolo', 'odoo-woocommerce-sync'); ?></th>
        <td>
            <label><input type="radio" name="odoo_woocommerce_sync_settings[protocol]" value="jsonrpc" <?php checked(($settings['protocol'] ?? 'jsonrpc'), 'jsonrpc'); ?> /> JSON-RPC</label><br />
            <label><input type="radio" name="odoo_woocommerce_sync_settings[protocol]" value="xmlrpc" <?php checked(($settings['protocol'] ?? ''), 'xmlrpc'); ?> /> XML-RPC</label>
        </td>
    </tr>
</table>
<p>
    <button type="button" class="button button-secondary" id="odoo-test-connection"><?php esc_html_e('Testar Conexao', 'odoo-woocommerce-sync'); ?></button>
    <span id="odoo-test-connection-result" class="odoo-inline-result"></span>
</p>
