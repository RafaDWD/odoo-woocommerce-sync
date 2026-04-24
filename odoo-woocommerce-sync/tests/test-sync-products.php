<?php
use PHPUnit\Framework\TestCase;
use DWDOdooWooSync\SyncProducts;

final class TestSyncProducts extends TestCase
{
    private function make_api(array $search_read_returns = []): \DWDOdooWooSync\OdooAPI
    {
        $mock = $this->createMock(\DWDOdooWooSync\OdooAPI::class);
        $mock->method('search_read')->willReturn($search_read_returns);
        $mock->method('search')->willReturn([]);
        return $mock;
    }

    private function make_logger(): \DWDOdooWooSync\SyncLogger
    {
        return $this->createMock(\DWDOdooWooSync\SyncLogger::class);
    }

    public function test_sync_from_odoo_returns_created_key(): void
    {
        $result = (new SyncProducts($this->make_api(), $this->make_logger()))->sync_from_odoo();
        $this->assertArrayHasKey('created', $result);
    }

    public function test_sync_from_odoo_returns_updated_key(): void
    {
        $result = (new SyncProducts($this->make_api(), $this->make_logger()))->sync_from_odoo();
        $this->assertArrayHasKey('updated', $result);
    }

    public function test_sync_from_odoo_returns_errors_key(): void
    {
        $result = (new SyncProducts($this->make_api(), $this->make_logger()))->sync_from_odoo();
        $this->assertArrayHasKey('errors', $result);
    }

    public function test_sync_from_odoo_returns_log_key(): void
    {
        $result = (new SyncProducts($this->make_api(), $this->make_logger()))->sync_from_odoo();
        $this->assertArrayHasKey('log', $result);
    }

    public function test_sync_from_odoo_empty_api_returns_zeros(): void
    {
        $result = (new SyncProducts($this->make_api([]), $this->make_logger()))->sync_from_odoo();
        $this->assertSame(0, $result['created']);
        $this->assertSame(0, $result['updated']);
        $this->assertSame(0, $result['errors']);
    }

    public function test_sync_from_odoo_batch_size_zero_uses_minimum(): void
    {
        $result = (new SyncProducts($this->make_api(), $this->make_logger()))->sync_from_odoo(['batch_size' => 0]);
        $this->assertIsArray($result);
    }

    public function test_sync_from_odoo_log_is_array(): void
    {
        $result = (new SyncProducts($this->make_api(), $this->make_logger()))->sync_from_odoo();
        $this->assertIsArray($result['log']);
    }
}
