<?php

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Mockery;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Any common setup for all tests
    }

    protected function tearDown(): void
    {
        if (class_exists('Mockery')) {
            Mockery::close();
        }
        parent::tearDown();
    }

    /**
     * Create a sample order data array for testing
     */
    protected function getSampleOrderData($overrides = []): array
    {
        $defaultData = [
            'id' => 12345,
            'name' => '#1001',
            'customer' => ['id' => 67890, 'tags' => 'vip'],
            'email' => 'test@example.com',
            'created_at' => '2025-01-01T10:00:00-05:00',
            'currency' => 'COP',
            'note' => 'Test order',
            'tags' => 'urgent',
            'cedula' => ['value' => '1234567890'],
            'cedulaFacturacion' => ['value' => '1234567890'],
            'shipping_address' => [
                'city' => 'Bogotá',
                'address1' => 'Calle 123',
                'address2' => 'Apto 456',
                'phone' => '3001234567',
                'province' => 'Bogotá, D.C'
            ],
            'billing_address' => [
                'city' => 'Bogotá',
                'address1' => 'Calle 123',
                'address2' => 'Apto 456',
                'first_name' => 'Juan',
                'last_name' => 'Pérez',
                'province' => 'Bogotá, D.C'
            ],
            'line_items' => [
                [
                    'sku' => 'TEST-001',
                    'quantity' => 2,
                    'variant_title' => 'Medium',
                    'price' => '50000',
                    'tax_lines' => [['price' => '9500']],
                    'discount_allocations' => [['amount' => '5000']]
                ]
            ],
            'total_shipping_price_set' => ['shop_money' => ['amount' => '10000']]
        ];

        return array_merge_recursive($defaultData, $overrides);
    }

    /**
     * Create a sample store config for testing
     */
    protected function getSampleStoreConfig($overrides = []): array
    {
        $defaultConfig = [
            'codigoCia' => '001',
            'storeName' => 'mizooco',
            'shopifyConfig' => [
                'api_key' => 'test_api_key',
                'access_token' => 'test_access_token',
                'shop_domain' => 'test.myshopify.com'
            ]
        ];

        return array_merge_recursive($defaultConfig, $overrides);
    }
}