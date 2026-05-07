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
    <tr>
        <th><label for="rest_token"><?php esc_html_e('Token REST API', 'odoo-woocommerce-sync'); ?></label></th>
        <td>
            <input class="regular-text" type="password" id="rest_token"
                name="odoo_woocommerce_sync_settings[rest_token]"
                value=""
                placeholder="<?php esc_attr_e('Digite para atualizar o token', 'odoo-woocommerce-sync'); ?>" />
            <?php if (!empty($settings['rest_token'])) : ?>
                <span class="description"><?php esc_html_e('Token configurado. Deixe vazio para manter.', 'odoo-woocommerce-sync'); ?></span>
            <?php endif; ?>
            <p class="description"><?php esc_html_e('Usado no header X-Odoo-Sync-Token para autenticar chamadas REST externas.', 'odoo-woocommerce-sync'); ?></p>
        </td>
    </tr>
    <tr>
        <th><label for="webhook_token"><?php esc_html_e('Token Webhook de Estoque', 'odoo-woocommerce-sync'); ?></label></th>
        <td>
            <input class="regular-text" type="password" id="webhook_token"
                name="odoo_woocommerce_sync_settings[webhook_token]"
                value=""
                placeholder="<?php esc_attr_e('Digite para atualizar o token', 'odoo-woocommerce-sync'); ?>" />
            <?php if (!empty($settings['webhook_token'])) : ?>
                <span class="description"><?php esc_html_e('Token configurado. Deixe vazio para manter.', 'odoo-woocommerce-sync'); ?></span>
            <?php else : ?>
                <span class="description" style="color:#d63638"><?php esc_html_e('Token nao configurado — webhook de estoque recusara todas as requisicoes.', 'odoo-woocommerce-sync'); ?></span>
            <?php endif; ?>
            <p class="description"><?php esc_html_e('Obrigatorio para receber atualizacoes de estoque via webhook do Odoo.', 'odoo-woocommerce-sync'); ?></p>
        </td>
    </tr>
    <tr>
        <th><?php esc_html_e('Webhook sem token (inseguro)', 'odoo-woocommerce-sync'); ?></th>
        <td>
            <label>
                <input type="checkbox"
                    name="odoo_woocommerce_sync_settings[allow_unauthenticated_stock_webhook]"
                    value="1"
                    <?php checked(!empty($settings['allow_unauthenticated_stock_webhook'])); ?> />
                <?php esc_html_e('Permitir webhook de estoque sem autenticacao quando webhook_token estiver vazio', 'odoo-woocommerce-sync'); ?>
            </label>
            <p class="description" style="color:#d63638">
                <?php esc_html_e('Nao recomendado: qualquer pessoa que alcance esse endpoint pode alterar o estoque.', 'odoo-woocommerce-sync'); ?>
            </p>
        </td>
    </tr>
</table>
<p>
    <button type="button" class="button button-secondary" id="odoo-test-connection"><?php esc_html_e('Testar Conexao', 'odoo-woocommerce-sync'); ?></button>
    <span id="odoo-test-connection-result" class="odoo-inline-result"></span>
</p>
