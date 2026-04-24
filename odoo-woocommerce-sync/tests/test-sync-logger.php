<?php
use PHPUnit\Framework\TestCase;

/**
 * Stub de wpdb para testes do SyncLogger sem banco de dados real.
 */
class WpdbStub
{
    public $prefix       = 'wp_';
    public $last_query   = '';
    public $insert_data  = [];

    public function prepare($query, ...$args): string
    {
        $i = 0;
        return preg_replace_callback('/%[sd]/', static function () use (&$i, $args) {
            return isset($args[$i]) ? (string) $args[$i++] : '?';
        }, $query);
    }

    public function insert($table, $data, $format = null): int
    {
        $this->insert_data = $data;
        return 1;
    }

    public function get_var($query): string
    {
        $this->last_query = (string) $query;
        return '0';
    }

    public function get_results($query, $output = ARRAY_A): array
    {
        $this->last_query = (string) $query;
        return [];
    }

    public function get_row($query, $output = ARRAY_A): ?array
    {
        return null;
    }

    public function query($query): int
    {
        $this->last_query = (string) $query;
        return 0;
    }

    public function update($table, $data, $where, $format = null, $where_format = null): int
    {
        return 1;
    }

    public function get_charset_collate(): string
    {
        return 'DEFAULT CHARACTER SET utf8mb4';
    }
}

if (!defined('ARRAY_A')) define('ARRAY_A', 'ARRAY_A');

final class TestSyncLogger extends TestCase
{
    /** @var WpdbStub */
    private $stub;
    private $original_wpdb;

    protected function setUp(): void
    {
        global $wpdb;
        $this->original_wpdb = $wpdb ?? null;
        $this->stub = new WpdbStub();
        $wpdb = $this->stub;
    }

    protected function tearDown(): void
    {
        global $wpdb;
        $wpdb = $this->original_wpdb;
    }

    public function test_log_inserts_correct_sync_type(): void
    {
        $logger = new \DWDOdooWooSync\SyncLogger();
        $logger->log('success', 'product', 'to_odoo', '42', 'Produto exportado.', '99', []);
        $this->assertSame('product', $this->stub->insert_data['sync_type']);
    }

    public function test_log_inserts_correct_direction(): void
    {
        $logger = new \DWDOdooWooSync\SyncLogger();
        $logger->log('success', 'product', 'to_odoo', '42', 'Mensagem.', null, []);
        $this->assertSame('to_odoo', $this->stub->insert_data['direction']);
    }

    public function test_log_inserts_status(): void
    {
        $logger = new \DWDOdooWooSync\SyncLogger();
        $logger->log('error', 'order', 'from_odoo', '10', 'Erro.', null, []);
        $this->assertSame('error', $this->stub->insert_data['status']);
    }

    public function test_get_logs_returns_items_and_pagination(): void
    {
        $logger = new \DWDOdooWooSync\SyncLogger();
        $result = $logger->get_logs([], 1, 10);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('pagination', $result);
    }

    public function test_get_logs_pagination_has_correct_page(): void
    {
        $logger = new \DWDOdooWooSync\SyncLogger();
        $result = $logger->get_logs([], 3, 10);
        $this->assertSame(3, $result['pagination']['page']);
    }

    public function test_get_logs_enforces_minimum_per_page(): void
    {
        $logger = new \DWDOdooWooSync\SyncLogger();
        $result = $logger->get_logs([], 1, 0);
        $this->assertSame(1, $result['pagination']['per_page']);
    }

    public function test_cleanup_old_logs_enforces_minimum_days(): void
    {
        $logger = new \DWDOdooWooSync\SyncLogger();
        $logger->cleanup_old_logs(0);
        $this->assertStringContainsString('INTERVAL 1 DAY', $this->stub->last_query);
    }

    public function test_get_logs_items_is_array(): void
    {
        $logger = new \DWDOdooWooSync\SyncLogger();
        $result = $logger->get_logs([], 1, 5);
        $this->assertIsArray($result['items']);
    }
}
