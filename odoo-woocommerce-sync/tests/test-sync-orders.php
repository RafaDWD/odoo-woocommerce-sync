<?php
use PHPUnit\Framework\TestCase;
use DWDOdooWooSync\SyncOrders;

final class TestSyncOrders extends TestCase
{
    private function make_api(array $returns = []): \DWDOdooWooSync\OdooAPI
    {
        $mock = $this->createMock(\DWDOdooWooSync\OdooAPI::class);
        $mock->method('search_read')->willReturn($returns);
        $mock->method('create')->willReturn(99);
        $mock->method('write')->willReturn(true);
        $mock->method('call')->willReturn(true);
        return $mock;
    }

    private function make_logger(): \DWDOdooWooSync\SyncLogger
    {
        return $this->createMock(\DWDOdooWooSync\SyncLogger::class);
    }

    public function test_import_orders_returns_created_key(): void
    {
        $orders = new SyncOrders($this->make_api([]), $this->make_logger());
        $result = $orders->import_orders_from_odoo();
        $this->assertArrayHasKey('created', $result);
    }

    public function test_import_orders_returns_errors_key(): void
    {
        $orders = new SyncOrders($this->make_api([]), $this->make_logger());
        $result = $orders->import_orders_from_odoo();
        $this->assertArrayHasKey('errors', $result);
    }

    public function test_import_orders_empty_api_returns_zero_created(): void
    {
        $orders = new SyncOrders($this->make_api([]), $this->make_logger());
        $result = $orders->import_orders_from_odoo();
        $this->assertSame(0, $result['created']);
    }

    public function test_sync_order_status_unknown_status_does_nothing(): void
    {
        $api    = $this->make_api();
        $api->expects($this->never())->method('call');
        $orders = new SyncOrders($api, $this->make_logger());
        $orders->sync_order_status(1, 'unknown-status');
    }
}
