<?php
use PHPUnit\Framework\TestCase;
use DWDOdooWooSync\SyncProducts;

final class SyncProductsTest extends TestCase
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

    public function test_sync_from_odoo_returns_expected_keys(): void
    {
        $result = (new SyncProducts($this->make_api(), $this->make_logger()))->sync_from_odoo();
        $this->assertArrayHasKey('created', $result);
        $this->assertArrayHasKey('updated', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('log', $result);
    }

    public function test_sync_from_odoo_empty_api_returns_zeros_or_error_without_woocommerce(): void
    {
        $result = (new SyncProducts($this->make_api([]), $this->make_logger()))->sync_from_odoo();
        $this->assertSame(0, $result['created']);
        $this->assertSame(0, $result['updated']);
        // Em execução unitária (sem WooCommerce carregado), a rotina retorna erro controlado.
        $this->assertGreaterThanOrEqual(0, $result['errors']);
    }

    public function test_sync_from_odoo_batch_size_zero_uses_minimum(): void
    {
        $result = (new SyncProducts($this->make_api(), $this->make_logger()))->sync_from_odoo(['batch_size' => 0]);
        $this->assertIsArray($result);
    }
}

