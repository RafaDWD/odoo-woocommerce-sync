<?php
namespace DWDOdooWooSync;

if (!defined('ABSPATH')) {
    exit;
}

class Odoo_WooCommerce_Sync_Public
{
    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    public function init()
    {
        // Espaco para hooks publicos e endpoints futuros.
    }
}
