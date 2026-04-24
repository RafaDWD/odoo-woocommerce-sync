<?php
namespace DWDOdooWooSync;

if (!defined('ABSPATH')) {
    exit;
}

class Odoo_WooCommerce_Sync_Loader
{
    protected $actions = [];
    protected $filters = [];

    public function add_action($hook, $component, $callback, $priority = 10, $accepted_args = 1)
    {
        $this->actions[] = compact('hook', 'component', 'callback', 'priority', 'accepted_args');
    }

    public function add_filter($hook, $component, $callback, $priority = 10, $accepted_args = 1)
    {
        $this->filters[] = compact('hook', 'component', 'callback', 'priority', 'accepted_args');
    }

    public function run()
    {
        foreach ($this->filters as $hook) {
            add_filter($hook['hook'], [$hook['component'], $hook['callback']], $hook['priority'], $hook['accepted_args']);
        }
        foreach ($this->actions as $hook) {
            add_action($hook['hook'], [$hook['component'], $hook['callback']], $hook['priority'], $hook['accepted_args']);
        }
    }
}
