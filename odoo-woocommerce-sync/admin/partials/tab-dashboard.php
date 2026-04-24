<?php
if (!defined('ABSPATH')) {
    exit;
}

$logger = new \DWDOdooWooSync\SyncLogger();
$logs = $logger->get_logs(10);
$products_synced = 0;
$orders_today = 0;
$errors_pending = 0;
$today = gmdate('Y-m-d');
$last_sync = '';

foreach ($logs as $log) {
    if ($log['sync_type'] === 'product' && $log['status'] === 'success') {
        $products_synced++;
    }
    if ($log['sync_type'] === 'order' && strpos((string) $log['created_at'], $today) === 0) {
        $orders_today++;
    }
    if ($log['status'] === 'error') {
        $errors_pending++;
    }
    if ($last_sync === '') {
        $last_sync = (string) ($log['created_at'] ?? '');
    }
}

$settings = get_option('odoo_woocommerce_sync_settings', []);
$connection_ok = !empty($settings['odoo_url']) && !empty($settings['odoo_db']) && !empty($settings['odoo_user']);
?>
<div class="odoo-sync-cards">
    <div class="odoo-sync-card">
        <h3><?php esc_html_e('Produtos sincronizados', 'odoo-woocommerce-sync'); ?></h3>
        <p><?php echo esc_html((string) $products_synced); ?></p>
    </div>
    <div class="odoo-sync-card">
        <h3><?php esc_html_e('Pedidos hoje', 'odoo-woocommerce-sync'); ?></h3>
        <p><?php echo esc_html((string) $orders_today); ?></p>
    </div>
    <div class="odoo-sync-card">
        <h3><?php esc_html_e('Erros pendentes', 'odoo-woocommerce-sync'); ?></h3>
        <p><?php echo esc_html((string) $errors_pending); ?></p>
    </div>
    <div class="odoo-sync-card">
        <h3><?php esc_html_e('Status da conexao Odoo', 'odoo-woocommerce-sync'); ?></h3>
        <p class="<?php echo $connection_ok ? 'ok' : 'fail'; ?>">
            <?php echo $connection_ok ? esc_html__('Configurada', 'odoo-woocommerce-sync') : esc_html__('Nao configurada', 'odoo-woocommerce-sync'); ?>
        </p>
        <small><?php echo $last_sync ? esc_html(sprintf(__('Ultima sincronizacao: %s', 'odoo-woocommerce-sync'), $last_sync)) : esc_html__('Sem sincronizacoes ainda', 'odoo-woocommerce-sync'); ?></small>
    </div>
</div>

<h3><?php esc_html_e('Ultimas 10 sincronizacoes', 'odoo-woocommerce-sync'); ?></h3>
<table class="widefat striped">
    <thead><tr><th><?php esc_html_e('Data/Hora', 'odoo-woocommerce-sync'); ?></th><th><?php esc_html_e('Tipo', 'odoo-woocommerce-sync'); ?></th><th><?php esc_html_e('Status', 'odoo-woocommerce-sync'); ?></th><th><?php esc_html_e('Mensagem', 'odoo-woocommerce-sync'); ?></th></tr></thead>
    <tbody>
    <?php if (empty($logs)) : ?>
        <tr><td colspan="4"><?php esc_html_e('Nenhuma sincronizacao encontrada.', 'odoo-woocommerce-sync'); ?></td></tr>
    <?php else : foreach ($logs as $log) : ?>
        <tr>
            <td><?php echo esc_html($log['created_at']); ?></td>
            <td><?php echo esc_html($log['sync_type']); ?></td>
            <td><?php echo esc_html($log['status']); ?></td>
            <td><?php echo esc_html($log['message']); ?></td>
        </tr>
    <?php endforeach; endif; ?>
    </tbody>
</table>
