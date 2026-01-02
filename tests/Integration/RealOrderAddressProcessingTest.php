<?php

namespace Tests\Integration;

use App\Services\AddressSplitterService;
use App\Services\BillingAddressValidatorService;
use PHPUnit\Framework\TestCase;

/**
 * Test de integración usando un payload real de orden de Shopify
 * Verifica que los servicios de validación y fragmentación de direcciones
 * funcionan correctamente con datos reales
 */
class RealOrderAddressProcessingTest extends TestCase
{
    private array $orderData;
    private AddressSplitterService $addressSplitter;
    private BillingAddressValidatorService $billingValidator;
    private string $logFile;

    protected function setUp(): void
    {
        parent::setUp();

        // Cargar el orden de ejemplo real
        $orderJsonPath = __DIR__ . '/../../order-example.json';
        $this->assertFileExists($orderJsonPath, 'order-example.json debe existir');

        $orderJson = file_get_contents($orderJsonPath);
        $orderDataFull = json_decode($orderJson, true);
        $this->orderData = $orderDataFull['order'];

        // Inicializar servicios
        $this->logFile = 'real_order_test_' . uniqid() . '.log';
        $this->addressSplitter = new AddressSplitterService($this->logFile);
        $this->billingValidator = new BillingAddressValidatorService($this->logFile);
    }

    /**
     * Test: Procesar direcciones del order-example.json
     *
     * Dirección real: "Transversal 65a #32 56 Torre 2 Apt 607"
     * Total: 45 caracteres
     * Debe dividirse en:
     * - address1: "Transversal 65a #32 56 Torre 2 Apt 607" (si cabe en 40) o dividirse
     */
    public function testRealOrderAddressProcessing()
    {
        // Extraer direcciones del payload
        $billingAddress = $this->orderData['billing_address'];
        $shippingAddress = $this->orderData['shipping_address'];

        $this->assertNotEmpty($billingAddress['address1']);
        $this->assertNotEmpty($shippingAddress['address1']);

        // Combinar address1 y address2 como lo hace CreateOrderService
        $billingFull = trim(
            ($billingAddress['address1'] ?? '') . ' ' .
            ($billingAddress['address2'] ?? '')
        );

        $shippingFull = trim(
            ($shippingAddress['address1'] ?? '') . ' ' .
            ($shippingAddress['address2'] ?? '')
        );

        echo "\n=== Dirección Original ===\n";
        echo "Billing Full: $billingFull\n";
        echo "Longitud: " . strlen($billingFull) . " chars\n";
        echo "Shipping Full: $shippingFull\n";
        echo "Longitud: " . strlen($shippingFull) . " chars\n";

        // Fragmentar dirección de billing
        $billingResult = $this->addressSplitter->splitAddress($billingFull, $this->orderData['id']);

        echo "\n=== Resultado de Fragmentación (Billing) ===\n";
        echo "address1: '{$billingResult['address1']}' (" . strlen($billingResult['address1']) . " chars)\n";
        echo "address2: '{$billingResult['address2']}' (" . strlen($billingResult['address2']) . " chars)\n";

        // Verificaciones
        $this->assertLessThanOrEqual(40, strlen($billingResult['address1']), 'address1 no debe exceder 40 caracteres');
        $this->assertLessThanOrEqual(40, strlen($billingResult['address2']), 'address2 no debe exceder 40 caracteres');

        // La dirección combinada debe contener los elementos clave
        $combined = $billingResult['address1'] . ' ' . $billingResult['address2'];
        $this->assertStringContainsString('Transversal 65a', $combined);
        $this->assertStringContainsString('#32 56', $combined);

        // Fragmentar dirección de shipping
        $shippingResult = $this->addressSplitter->splitAddress($shippingFull, $this->orderData['id']);

        echo "\n=== Resultado de Fragmentación (Shipping) ===\n";
        echo "address1: '{$shippingResult['address1']}' (" . strlen($shippingResult['address1']) . " chars)\n";
        echo "address2: '{$shippingResult['address2']}' (" . strlen($shippingResult['address2']) . " chars)\n";

        $this->assertLessThanOrEqual(40, strlen($shippingResult['address1']));
        $this->assertLessThanOrEqual(40, strlen($shippingResult['address2']));
    }

    /**
     * Test: Validación de billing address con datos reales
     */
    public function testBillingAddressValidationWithRealData()
    {
        $billingAddress = $this->orderData['billing_address'];
        $shippingAddress = $this->orderData['shipping_address'];

        // En este caso, billing address está completa
        $result = $this->billingValidator->validateAndGetBillingAddress(
            $billingAddress,
            $shippingAddress
        );

        echo "\n=== Validación de Billing Address ===\n";
        echo "Original Billing: {$billingAddress['address1']}\n";
        echo "Resultado: {$result['address1']}\n";

        // Debe usar billing porque está completa
        $this->assertEquals($billingAddress['address1'], $result['address1']);
        $this->assertEquals($billingAddress['city'], $result['city']);
    }

    /**
     * Test: Validación de cédula (no existe en el order-example)
     */
    public function testCedulaValidationWithNoMetafield()
    {
        // En order-example.json no hay metafields de cédula
        $cedula = null; // Simula que no viene cédula

        $result = $this->billingValidator->validateAndGetCedula($cedula, $this->orderData['id']);

        echo "\n=== Validación de Cédula ===\n";
        echo "Cédula Original: " . ($cedula ?? 'null') . "\n";
        echo "Cédula Resultante: $result\n";

        // Debe usar cédula por defecto
        $this->assertEquals('222222222222', $result);
    }

    /**
     * Test: Escenario completo - procesar toda la orden
     */
    public function testCompleteOrderProcessing()
    {
        echo "\n=== Procesamiento Completo de Orden ===\n";
        echo "Order ID: {$this->orderData['id']}\n";
        echo "Order Number: {$this->orderData['order_number']}\n";
        echo "Customer: {$this->orderData['customer']['first_name']} {$this->orderData['customer']['last_name']}\n";
        echo "Email: {$this->orderData['email']}\n";

        // 1. Validar billing address
        $validatedBilling = $this->billingValidator->validateAndGetBillingAddress(
            $this->orderData['billing_address'],
            $this->orderData['shipping_address']
        );

        // 2. Fragmentar dirección por defecto del cliente
        $defaultAddress = $this->orderData['customer']['default_address'];
        $defaultFull = trim(
            ($defaultAddress['address1'] ?? '') . ' ' .
            ($defaultAddress['address2'] ?? '')
        );
        $defaultSplit = $this->addressSplitter->splitAddress($defaultFull, $this->orderData['id']);

        // 3. Fragmentar billing (después de validación)
        $billingFull = trim(
            ($validatedBilling['address1'] ?? '') . ' ' .
            ($validatedBilling['address2'] ?? '')
        );
        $billingSplit = $this->addressSplitter->splitAddress($billingFull, $this->orderData['id']);

        // 4. Fragmentar shipping
        $shippingFull = trim(
            ($this->orderData['shipping_address']['address1'] ?? '') . ' ' .
            ($this->orderData['shipping_address']['address2'] ?? '')
        );
        $shippingSplit = $this->addressSplitter->splitAddress($shippingFull, $this->orderData['id']);

        // 5. Validar cédulas
        $cedula = $this->billingValidator->validateAndGetCedula(null, $this->orderData['id']);
        $cedulaBilling = $this->billingValidator->validateAndGetCedula(null, $this->orderData['id']);

        // Mostrar resultados
        echo "\n--- Direcciones Procesadas ---\n";
        echo "Default: address1='{$defaultSplit['address1']}', address2='{$defaultSplit['address2']}'\n";
        echo "Billing: address1='{$billingSplit['address1']}', address2='{$billingSplit['address2']}'\n";
        echo "Shipping: address1='{$shippingSplit['address1']}', address2='{$shippingSplit['address2']}'\n";
        echo "Cédula: $cedula\n";
        echo "Cédula Billing: $cedulaBilling\n";

        // Verificaciones finales
        $this->assertLessThanOrEqual(40, strlen($defaultSplit['address1']));
        $this->assertLessThanOrEqual(40, strlen($defaultSplit['address2']));
        $this->assertLessThanOrEqual(40, strlen($billingSplit['address1']));
        $this->assertLessThanOrEqual(40, strlen($billingSplit['address2']));
        $this->assertLessThanOrEqual(40, strlen($shippingSplit['address1']));
        $this->assertLessThanOrEqual(40, strlen($shippingSplit['address2']));
        $this->assertEquals('222222222222', $cedula);
        $this->assertEquals('222222222222', $cedulaBilling);

        echo "\n✅ Todos los campos procesados correctamente\n";
    }

    /**
     * Test: Verificar el comportamiento con dirección real del order-example.json
     */
    public function testRealAddressHandling()
    {
        $billingAddress = $this->orderData['billing_address'];

        // Dirección completa: "Transversal 65a #32 56 Torre 2 Apt 607"
        $fullAddress = trim(
            $billingAddress['address1'] . ' ' . $billingAddress['address2']
        );

        echo "\n=== Análisis de Dirección Real ===\n";
        echo "Dirección Completa: $fullAddress\n";
        echo "Longitud: " . strlen($fullAddress) . " caracteres\n";
        echo "¿Requiere split? " . (strlen($fullAddress) > 40 ? 'SÍ' : 'NO') . "\n";

        // La dirección del order-example.json tiene 38 caracteres
        // Por lo tanto NO requiere split (cabe en address1)
        $this->assertLessThanOrEqual(40, strlen($fullAddress),
            'La dirección del order-example debe caber en 40 chars');

        // Procesar y verificar que no se divide
        $result = $this->addressSplitter->splitAddress($fullAddress, $this->orderData['id']);

        $this->assertEquals($fullAddress, $result['address1'],
            'Dirección completa debe estar en address1');
        $this->assertEquals('', $result['address2'],
            'address2 debe estar vacío cuando no se requiere split');

        echo "✅ Dirección cabe perfectamente en address1 sin necesidad de split\n";
    }
}
