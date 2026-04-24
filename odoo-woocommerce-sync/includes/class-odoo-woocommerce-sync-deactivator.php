<?php
namespace DWDOdooWooSync;

if (!defined('ABSPATH')) {
    exit;
}

class Odoo_WooCommerce_Sync_Deactivator
{
    public static function deactivate()
    {
        wp_clear_scheduled_hook('odoo_sync_stock_hourly');
        wp_clear_scheduled_hook('odoo_sync_stock_daily');
        Odoo_WooCommerce_Sync::deactivate_cron_jobs();
    }
}
