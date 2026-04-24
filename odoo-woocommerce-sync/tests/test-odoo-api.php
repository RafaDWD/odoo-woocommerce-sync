<?php
use PHPUnit\Framework\TestCase;
use DWDOdooWooSync\OdooAPI;

final class TestOdooApi extends TestCase
{
    public function test_constructor_strips_trailing_slash(): void
    {
        $api    = new OdooAPI(['url' => 'https://odoo.example.com/']);
        $config = $api->get_safe_config();
        $this->assertSame('https://odoo.example.com', $config['url']);
    }

    public function test_constructor_defaults_to_jsonrpc(): void
    {
        $api = new OdooAPI([]);
        $this->assertSame('jsonrpc', $api->get_protocol());
    }

    public function test_constructor_accepts_xmlrpc(): void
    {
        $api = new OdooAPI(['protocol' => 'xmlrpc']);
        $this->assertSame('xmlrpc', $api->get_protocol());
    }

    public function test_constructor_rejects_invalid_protocol(): void
    {
        $api = new OdooAPI(['protocol' => 'ftp']);
        $this->assertSame('jsonrpc', $api->get_protocol());
    }

    public function test_timeout_minimum_is_5(): void
    {
        $api    = new OdooAPI(['timeout' => 1]);
        $config = $api->get_safe_config();
        $this->assertSame(5, $config['timeout']);
    }

    public function test_get_uid_returns_null_before_authenticate(): void
    {
        $api = new OdooAPI(['url' => 'https://example.com', 'db' => 'test', 'username' => 'u', 'password' => 'p']);
        $this->assertNull($api->get_uid());
    }

    public function test_get_safe_config_does_not_expose_password(): void
    {
        $api    = new OdooAPI(['password' => 'secret123']);
        $config = $api->get_safe_config();
        $this->assertArrayNotHasKey('password', $config);
    }

    public function test_set_company_id_rejects_negative(): void
    {
        $api = new OdooAPI(['company_id' => 5]);
        $api->set_company_id(-3);
        $config = $api->get_safe_config();
        $this->assertSame(0, $config['company_id']);
    }

    public function test_set_timeout_enforces_minimum(): void
    {
        $api = new OdooAPI([]);
        $api->set_timeout(2);
        $config = $api->get_safe_config();
        $this->assertSame(5, $config['timeout']);
    }

    public function test_authenticate_throws_when_credentials_missing(): void
    {
        $api = new OdooAPI([]);
        $this->expectException(\RuntimeException::class);
        $api->authenticate();
    }

    public function test_reset_session_clears_uid(): void
    {
        $api = new OdooAPI(['url' => 'https://x.com', 'db' => 'x', 'username' => 'u', 'password' => 'p']);
        $api->reset_session();
        $this->assertNull($api->get_uid());
    }

    public function test_verify_ssl_default_is_true(): void
    {
        $api    = new OdooAPI([]);
        $config = $api->get_safe_config();
        $this->assertTrue($config['verify_ssl']);
    }

    public function test_set_verify_ssl_false(): void
    {
        $api = new OdooAPI([]);
        $api->set_verify_ssl(false);
        $config = $api->get_safe_config();
        $this->assertFalse($config['verify_ssl']);
    }
}
