<?php
if (!defined('ABSPATH')) {
    exit;
}

// $tab é injetada por render_admin_tab(); fallback para GET se acessado diretamente
if (!isset($tab)) {
    $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'dashboard';
}
?>
<div class="wrap odoo-sync-admin">
    <h1><?php esc_html_e('DWD Multicom Odoo WooCommerce Sync', 'odoo-woocommerce-sync'); ?></h1>
    <h2 class="nav-tab-wrapper">
        <a class="nav-tab <?php echo $tab === 'dashboard' ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=odoo-woocommerce-sync')); ?>"><?php esc_html_e('Dashboard', 'odoo-woocommerce-sync'); ?></a>
        <a class="nav-tab <?php echo $tab === 'credentials' ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=odoo-woocommerce-sync&tab=credentials')); ?>"><?php esc_html_e('Credenciais', 'odoo-woocommerce-sync'); ?></a>
        <a class="nav-tab <?php echo $tab === 'products' ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=odoo-woocommerce-sync&tab=products')); ?>"><?php esc_html_e('Produtos', 'odoo-woocommerce-sync'); ?></a>
        <a class="nav-tab <?php echo $tab === 'orders' ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=odoo-woocommerce-sync&tab=orders')); ?>"><?php esc_html_e('Pedidos', 'odoo-woocommerce-sync'); ?></a>
        <a class="nav-tab <?php echo $tab === 'stock' ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=odoo-woocommerce-sync&tab=stock')); ?>"><?php esc_html_e('Estoque', 'odoo-woocommerce-sync'); ?></a>
        <a class="nav-tab <?php echo $tab === 'logs' ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=odoo-woocommerce-sync&tab=logs')); ?>"><?php esc_html_e('Logs', 'odoo-woocommerce-sync'); ?></a>
    </h2>

    <form method="post" action="options.php">
        <?php settings_fields('odoo_woocommerce_sync_settings_group'); ?>
        <?php
        switch ($tab) {
            case 'dashboard':
                include DWD_OWS_PLUGIN_DIR . 'admin/partials/tab-dashboard.php';
                break;
            case 'products':
                include DWD_OWS_PLUGIN_DIR . 'admin/partials/tab-products.php';
                break;
            case 'orders':
                include DWD_OWS_PLUGIN_DIR . 'admin/partials/tab-orders.php';
                break;
            case 'stock':
                include DWD_OWS_PLUGIN_DIR . 'admin/partials/tab-stock.php';
                break;
            case 'logs':
                include DWD_OWS_PLUGIN_DIR . 'admin/partials/tab-logs.php';
                break;
            case 'credentials':
            default:
                include DWD_OWS_PLUGIN_DIR . 'admin/partials/tab-credentials.php';
                break;
        }
        submit_button(__('Salvar configuracoes', 'odoo-woocommerce-sync'));
        ?>
    </form>
</div>
