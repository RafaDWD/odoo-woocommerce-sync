<?php
namespace DWDOdooWooSync;

if (!defined('ABSPATH')) {
    exit;
}

class Odoo_WooCommerce_Sync_Activator
{
    public static function activate()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'odoo_sync_logs';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            sync_type varchar(20) NOT NULL,
            direction varchar(10) NOT NULL,
            entity_id varchar(100) DEFAULT NULL,
            odoo_id varchar(100) DEFAULT NULL,
            status varchar(10) NOT NULL,
            message text DEFAULT NULL,
            details longtext DEFAULT NULL,
            reprocessed tinyint(1) DEFAULT 0,
            PRIMARY KEY (id),
            KEY sync_type (sync_type),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        add_option('odoo_woocommerce_sync_version', DWD_OWS_VERSION);
        add_option('odoo_woocommerce_sync_settings', []);
        add_option('odoo_sync_stores', []);

        Odoo_WooCommerce_Sync::activate_cron_jobs();
    }
}
