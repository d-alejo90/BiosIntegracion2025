<?php

namespace Tests\Unit\Services;

use App\Services\BillingAddressValidatorService;
use PHPUnit\Framework\TestCase;

class BillingAddressValidatorServiceTest extends TestCase
{
    private BillingAddressValidatorService $service;
    private string $logFile;

    protected function setUp(): void
    {
        parent::setUp();

        // Use simple filename for Logger (it creates directory structure)
        $this->logFile = 'billing_validator_test_' . uniqid() . '.log';

        $this->service = new BillingAddressValidatorService($this->logFile);
    }

    /**
     * Test: Billing address completa - debe usarla
     */
    public function testCompleteBillingAddressIsUsed()
    {
        $billingAddress = [
            'address1' => 'Calle 123 #45-67',
            'address2' => 'Apto 801',
            'city' => 'Bogotá',
            'province' => 'Cundinamarca',
            'country' => 'Colombia'
        ];

        $shippingAddress = [
            'address1' => 'Carrera 7 #12-34',
            'city' => 'Medellín'
        ];

        $result = $this->service->validateAndGetBillingAddress($billingAddress, $shippingAddress);

        $this->assertEquals($billingAddress, $result);
        $this->assertEquals('Calle 123 #45-67', $result['address1']);
    }

    /**
     * Test: Billing address vacía - debe usar shipping
     */
    public function testEmptyBillingAddressUsesShipping()
    {
        $billingAddress = [];

        $shippingAddress = [
            'address1' => 'Carrera 7 #12-34',
            'address2' => '',
            'city' => 'Medellín',
            'province' => 'Antioquia',
            'country' => 'Colombia'
        ];

        $result = $this->service->validateAndGetBillingAddress($billingAddress, $shippingAddress);

        $this->assertEquals($shippingAddress, $result);
        $this->assertEquals('Carrera 7 #12-34', $result['address1']);

        // Verificar que se registró en log
        $this->assertFileExists($this->logFile);
        $logContent = file_get_contents($this->logFile);
        $this->assertStringContainsString('Address replacement', $logContent);
        $this->assertStringContainsString('Billing address incomplete or empty', $logContent);
    }

    /**
     * Test: Billing address sin address1 - debe usar shipping
     */
    public function testBillingAddressWithoutAddress1UsesShipping()
    {
        $billingAddress = [
            'address1' => '',
            'city' => 'Bogotá'
        ];

        $shippingAddress = [
            'address1' => 'Carrera 7 #12-34',
            'city' => 'Medellín'
        ];

        $result = $this->service->validateAndGetBillingAddress($billingAddress, $shippingAddress);

        $this->assertEquals($shippingAddress, $result);
    }

    /**
     * Test: Billing address sin city - debe usar shipping
     */
    public function testBillingAddressWithoutCityUsesShipping()
    {
        $billingAddress = [
            'address1' => 'Calle 123',
            'city' => ''
        ];

        $shippingAddress = [
            'address1' => 'Carrera 7 #12-34',
            'city' => 'Medellín'
        ];

        $result = $this->service->validateAndGetBillingAddress($billingAddress, $shippingAddress);

        $this->assertEquals($shippingAddress, $result);
    }

    /**
     * Test: Billing address solo con address1 y city - debe usarla (mínimo requerido)
     */
    public function testBillingAddressWithOnlyRequiredFieldsIsValid()
    {
        $billingAddress = [
            'address1' => 'Calle 123 #45-67',
            'city' => 'Bogotá'
        ];

        $shippingAddress = [
            'address1' => 'Carrera 7 #12-34',
            'city' => 'Medellín'
        ];

        $result = $this->service->validateAndGetBillingAddress($billingAddress, $shippingAddress);

        // Debe usar billing porque tiene los campos mínimos
        $this->assertEquals($billingAddress, $result);
        $this->assertEquals('Calle 123 #45-67', $result['address1']);
    }

    /**
     * Test: Cédula proporcionada - debe usarla
     */
    public function testProvidedCedulaIsUsed()
    {
        $cedula = '123456789';
        $result = $this->service->validateAndGetCedula($cedula);

        $this->assertEquals('123456789', $result);
    }

    /**
     * Test: Cédula null - debe usar default
     */
    public function testNullCedulaUsesDefault()
    {
        $result = $this->service->validateAndGetCedula(null);

        $this->assertEquals('222222222222', $result);

        // Verificar log
        $this->assertFileExists($this->logFile);
        $logContent = file_get_contents($this->logFile);
        $this->assertStringContainsString('Default cedula used', $logContent);
        $this->assertStringContainsString('222222222222', $logContent);
    }

    /**
     * Test: Cédula vacía - debe usar default
     */
    public function testEmptyCedulaUsesDefault()
    {
        $result = $this->service->validateAndGetCedula('');

        $this->assertEquals('222222222222', $result);
    }

    /**
     * Test: Cédula con solo espacios - debe usar default
     */
    public function testWhitespaceCedulaUsesDefault()
    {
        $result = $this->service->validateAndGetCedula('   ');

        $this->assertEquals('222222222222', $result);
    }

    /**
     * Test: Cédula con espacios al inicio y final - debe hacer trim
     */
    public function testCedulaWithSpacesIsTrimmed()
    {
        $result = $this->service->validateAndGetCedula('  987654321  ');

        $this->assertEquals('987654321', $result);
    }

    /**
     * Test: Cédula con orderId - debe incluir en log
     */
    public function testCedulaDefaultWithOrderIdLogsCorrectly()
    {
        $orderId = 'ORDER123';
        $result = $this->service->validateAndGetCedula(null, $orderId);

        $this->assertEquals('222222222222', $result);

        $logContent = file_get_contents($this->logFile);
        $this->assertStringContainsString('for order ORDER123', $logContent);
    }

    /**
     * Test: Cédula sin orderId - no debe incluir order info en log
     */
    public function testCedulaDefaultWithoutOrderIdLogsWithoutOrderInfo()
    {
        $result = $this->service->validateAndGetCedula(null, null);

        $this->assertEquals('222222222222', $result);

        $logContent = file_get_contents($this->logFile);
        $this->assertStringContainsString('Default cedula used', $logContent);
        $this->assertStringNotContainsString('for order', $logContent);
    }

    /**
     * Test: Billing address con address1 solo espacios - debe usar shipping
     */
    public function testBillingAddressWithWhitespaceAddress1UsesShipping()
    {
        $billingAddress = [
            'address1' => '   ',
            'city' => 'Bogotá'
        ];

        $shippingAddress = [
            'address1' => 'Carrera 7 #12-34',
            'city' => 'Medellín'
        ];

        $result = $this->service->validateAndGetBillingAddress($billingAddress, $shippingAddress);

        $this->assertEquals($shippingAddress, $result);
    }

    /**
     * Test: Billing address con city solo espacios - debe usar shipping
     */
    public function testBillingAddressWithWhitespaceCityUsesShipping()
    {
        $billingAddress = [
            'address1' => 'Calle 123',
            'city' => '   '
        ];

        $shippingAddress = [
            'address1' => 'Carrera 7 #12-34',
            'city' => 'Medellín'
        ];

        $result = $this->service->validateAndGetBillingAddress($billingAddress, $shippingAddress);

        $this->assertEquals($shippingAddress, $result);
    }

    /**
     * Test: Log debe incluir información de dirección original y replacement
     */
    public function testLogIncludesOriginalAndReplacementAddress()
    {
        $billingAddress = [
            'address1' => '',
            'city' => ''
        ];

        $shippingAddress = [
            'address1' => 'Carrera 7 #12-34',
            'address2' => 'Apto 501',
            'city' => 'Medellín',
            'province' => 'Antioquia'
        ];

        $this->service->validateAndGetBillingAddress($billingAddress, $shippingAddress);

        $logContent = file_get_contents($this->logFile);
        $this->assertStringContainsString('Original: [incomplete]', $logContent);
        $this->assertStringContainsString('Carrera 7 #12-34', $logContent);
        $this->assertStringContainsString('Medellín', $logContent);
    }

    /**
     * Test: Scenario 1 del plan - cedula proporcionada, billing completa
     */
    public function testPlanScenario1_ProvidedCedulaAndCompleteBilling()
    {
        $cedula = '123456789';
        $billingAddress = [
            'address1' => 'Calle 123 #45-67',
            'city' => 'Bogotá'
        ];
        $shippingAddress = [
            'address1' => 'Carrera 7',
            'city' => 'Medellín'
        ];

        $resultCedula = $this->service->validateAndGetCedula($cedula);
        $resultAddress = $this->service->validateAndGetBillingAddress($billingAddress, $shippingAddress);

        $this->assertEquals('123456789', $resultCedula);
        $this->assertEquals($billingAddress, $resultAddress);
    }

    /**
     * Test: Scenario 2 del plan - cedula proporcionada, billing vacía
     */
    public function testPlanScenario2_ProvidedCedulaAndEmptyBilling()
    {
        $cedula = '123456789';
        $billingAddress = [];
        $shippingAddress = [
            'address1' => 'Carrera 7 #12-34',
            'city' => 'Medellín'
        ];

        $resultCedula = $this->service->validateAndGetCedula($cedula);
        $resultAddress = $this->service->validateAndGetBillingAddress($billingAddress, $shippingAddress);

        $this->assertEquals('123456789', $resultCedula);
        $this->assertEquals($shippingAddress, $resultAddress);
    }

    /**
     * Test: Scenario 3 del plan - cedula null, billing completa
     */
    public function testPlanScenario3_NullCedulaAndCompleteBilling()
    {
        $cedula = null;
        $billingAddress = [
            'address1' => 'Calle 123 #45-67',
            'city' => 'Bogotá'
        ];
        $shippingAddress = [
            'address1' => 'Carrera 7',
            'city' => 'Medellín'
        ];

        $resultCedula = $this->service->validateAndGetCedula($cedula);
        $resultAddress = $this->service->validateAndGetBillingAddress($billingAddress, $shippingAddress);

        $this->assertEquals('222222222222', $resultCedula);
        $this->assertEquals($billingAddress, $resultAddress);
    }

    /**
     * Test: Scenario 4 del plan - cedula null, billing vacía
     */
    public function testPlanScenario4_NullCedulaAndEmptyBilling()
    {
        $cedula = null;
        $billingAddress = [];
        $shippingAddress = [
            'address1' => 'Carrera 7 #12-34',
            'city' => 'Medellín'
        ];

        $resultCedula = $this->service->validateAndGetCedula($cedula);
        $resultAddress = $this->service->validateAndGetBillingAddress($billingAddress, $shippingAddress);

        $this->assertEquals('222222222222', $resultCedula);
        $this->assertEquals($shippingAddress, $resultAddress);
    }
}
