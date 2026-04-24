<?php
if (!defined('ABSPATH')) {
    exit;
}

$logger = class_exists('\DWDOdooWooSync\SyncLogger') ? new \DWDOdooWooSync\SyncLogger() : null;
$logs = $logger ? $logger->get_logs(20) : [];
?>
<p><?php esc_html_e('Ultimos eventos de sincronizacao:', 'odoo-woocommerce-sync'); ?></p>
<p>
    <button type="button" class="button" id="odoo-reprocess-failures"><?php esc_html_e('Reprocessar Falhas', 'odoo-woocommerce-sync'); ?></button>
    <button type="button" class="button" id="odoo-cleanup-logs"><?php esc_html_e('Limpar Logs Antigos (30 dias)', 'odoo-woocommerce-sync'); ?></button>
    <button type="button" class="button button-primary" id="odoo-export-logs"><?php esc_html_e('Exportar CSV', 'odoo-woocommerce-sync'); ?></button>
    <span id="odoo-logs-result" class="odoo-inline-result"></span>
</p>
<table class="widefat striped">
    <thead>
        <tr>
            <th><?php esc_html_e('Data', 'odoo-woocommerce-sync'); ?></th>
            <th><?php esc_html_e('Tipo', 'odoo-woocommerce-sync'); ?></th>
            <th><?php esc_html_e('Entidade', 'odoo-woocommerce-sync'); ?></th>
            <th><?php esc_html_e('Status', 'odoo-woocommerce-sync'); ?></th>
            <th><?php esc_html_e('Mensagem', 'odoo-woocommerce-sync'); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($logs)) : ?>
            <tr><td colspan="5"><?php esc_html_e('Nenhum log encontrado.', 'odoo-woocommerce-sync'); ?></td></tr>
        <?php else : ?>
            <?php foreach ($logs as $log) : ?>
                <tr>
                    <td><?php echo esc_html($log['created_at']); ?></td>
                    <td><?php echo esc_html($log['sync_type']); ?></td>
                    <td><?php echo esc_html($log['entity_id']); ?></td>
                    <td><?php echo esc_html($log['status']); ?></td>
                    <td><?php echo esc_html($log['message']); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
