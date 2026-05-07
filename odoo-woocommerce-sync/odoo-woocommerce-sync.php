<?php
/**
 * Plugin Name: Odoo Woocommerce Sync - By DWD Multicom
 * Plugin URI: https://dwdmulticom.com.br/
 * Description: Sincronizacao base entre Odoo e WooCommerce.
 * Version: 1.2.1
 * Author: DWD Multicom
 * Text Domain: odoo-woocommerce-sync
 * Domain Path: /languages
 * Requires PHP: 7.4
 *
 * @package OdooWooCommerceSync
 */

if (!defined('ABSPATH')) {
    exit;
}

define('DWD_OWS_VERSION', '1.2.1');
define('DWD_OWS_PLUGIN_FILE', __FILE__);
define('DWD_OWS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DWD_OWS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DWD_OWS_TEXT_DOMAIN', 'odoo-woocommerce-sync');

require_once DWD_OWS_PLUGIN_DIR . 'includes/class-odoo-woocommerce-sync-activator.php';
require_once DWD_OWS_PLUGIN_DIR . 'includes/class-odoo-woocommerce-sync-deactivator.php';
require_once DWD_OWS_PLUGIN_DIR . 'includes/class-odoo-woocommerce-sync.php';

function activate_odoo_woocommerce_sync()
{
    DWDOdooWooSync\Odoo_WooCommerce_Sync_Activator::activate();
}

function deactivate_odoo_woocommerce_sync()
{
    DWDOdooWooSync\Odoo_WooCommerce_Sync_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_odoo_woocommerce_sync');
register_deactivation_hook(__FILE__, 'deactivate_odoo_woocommerce_sync');

function run_odoo_woocommerce_sync()
{
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__('DWD Multicom Odoo WooCommerce Sync requer WooCommerce ativo.', DWD_OWS_TEXT_DOMAIN);
            echo '</p></div>';
        });
        return;
    }

    $plugin = new DWDOdooWooSync\Odoo_WooCommerce_Sync();
    $plugin->run();
}

add_action('plugins_loaded', 'run_odoo_woocommerce_sync');
