<?php

namespace Tests\Unit\Services;

use App\Services\AddressSplitterService;
use PHPUnit\Framework\TestCase;

class AddressSplitterServiceTest extends TestCase
{
    private AddressSplitterService $service;
    private string $logFile;

    protected function setUp(): void
    {
        parent::setUp();

        // Use simple filename for Logger (it creates directory structure)
        $this->logFile = 'address_splitter_test_' . uniqid() . '.log';

        $this->service = new AddressSplitterService($this->logFile);
    }

    /**
     * Test: Dirección corta que cabe completa en address1
     */
    public function testAddressFitsInAddress1()
    {
        $address = "Calle 123";
        $result = $this->service->splitAddress($address);

        $this->assertEquals($address, $result['address1']);
        $this->assertEquals('', $result['address2']);
    }

    /**
     * Test: Dirección que excede MAX_ADDRESS_LENGTH chars pero cabe en MAX_TOTAL_LENGTH chars total
     */
    public function testAddressSplitsAtMaxCharacters()
    {
        // 74 caracteres total
        $address = "Calle 123 #45-67 Apartamento 890 Torre B Conjunto Residencial Los Robles";
        $result = $this->service->splitAddress($address);

        // Debe dividir en el último espacio antes de MAX_ADDRESS_LENGTH chars
        $this->assertLessThanOrEqual(AddressSplitterService::MAX_ADDRESS_LENGTH, strlen($result['address1']));
        $this->assertLessThanOrEqual(AddressSplitterService::MAX_ADDRESS_LENGTH, strlen($result['address2']));
        $this->assertNotEmpty($result['address2']);

        // Verificar que no se divide una palabra a la mitad
        $this->assertStringEndsNotWith(' ', $result['address1']);
        $this->assertStringStartsNotWith(' ', $result['address2']);
    }

    /**
     * Test: Palabra única sin espacios mayor a MAX_ADDRESS_LENGTH caracteres (corte forzoso)
     */
    public function testSingleWordLongerThanMaxCharsForcedCut()
    {
        $address = "DireccionSinEspaciosMuyLargaQueSuperaElLimiteDeCaracteresSinPoderDividirse";
        $result = $this->service->splitAddress($address);

        // Debe cortar exactamente en MAX_ADDRESS_LENGTH caracteres
        $this->assertEquals(AddressSplitterService::MAX_ADDRESS_LENGTH, strlen($result['address1']));
        $this->assertNotEmpty($result['address2']);

        // El resultado combinado debe preservar la dirección original (hasta MAX_TOTAL_LENGTH chars)
        $combined = $result['address1'] . $result['address2'];
        $this->assertStringStartsWith($combined, $address);
    }

    /**
     * Test: Dirección que excede MAX_TOTAL_LENGTH caracteres total (truncamiento con log)
     */
    public function testAddressExceedsMaxTotalCharsTruncatesAndLogs()
    {
        // 120 caracteres
        $address = str_repeat("Direccion muy larga ", 6); // "Direccion muy larga " * 6 = 120 chars
        $orderId = "TEST123";

        $result = $this->service->splitAddress($address, $orderId);

        // Debe tener address1 y address2 con max MAX_ADDRESS_LENGTH chars cada uno
        $this->assertLessThanOrEqual(AddressSplitterService::MAX_ADDRESS_LENGTH, strlen($result['address1']));
        $this->assertLessThanOrEqual(AddressSplitterService::MAX_ADDRESS_LENGTH, strlen($result['address2']));
    }

    /**
     * Test: Dirección vacía
     */
    public function testEmptyAddress()
    {
        $result = $this->service->splitAddress('');

        $this->assertEquals('', $result['address1']);
        $this->assertEquals('', $result['address2']);
    }

    /**
     * Test: Dirección con múltiples espacios consecutivos
     */
    public function testAddressWithMultipleSpaces()
    {
        $address = "Calle 123    #45-67    Apartamento  890";
        $result = $this->service->splitAddress($address);

        // El servicio preserva espacios internos, solo hace trim de inicio/final
        $this->assertNotEmpty($result['address1']);
        // Si la dirección cabe en address1, debe estar completa
        if (strlen($address) <= AddressSplitterService::MAX_ADDRESS_LENGTH) {
            $this->assertEquals($address, $result['address1']);
            $this->assertEquals('', $result['address2']);
        }
    }

    /**
     * Test: Dirección con caracteres especiales
     */
    public function testAddressWithSpecialCharacters()
    {
        $address = "Calle 123 #45-67 Apto 8-B (Conjunto Palmas) Torre Á Piso 10º";
        $result = $this->service->splitAddress($address);

        // Los caracteres especiales no deben afectar la división
        $this->assertNotEmpty($result['address1']);

        // Reconstruir y verificar que los caracteres especiales se preservan
        $combined = $result['address1'] . ' ' . $result['address2'];
        $this->assertStringContainsString('#45-67', $combined);
        $this->assertStringContainsString('Á', $combined);
        $this->assertStringContainsString('10º', $combined);
    }

    /**
     * Test: Dirección exactamente de MAX_ADDRESS_LENGTH caracteres
     */
    public function testAddressExactlyMaxAddressLength()
    {
        $address = str_repeat("A", AddressSplitterService::MAX_ADDRESS_LENGTH);
        $result = $this->service->splitAddress($address);

        $this->assertEquals(AddressSplitterService::MAX_ADDRESS_LENGTH, strlen($result['address1']));
        $this->assertEquals('', $result['address2']);
    }

    /**
     * Test: Dirección de MAX_ADDRESS_LENGTH + 1 caracteres (debe dividir)
     */
    public function testAddressMaxPlusOneCharactersMustSplit()
    {
        $address = str_repeat("A", AddressSplitterService::MAX_ADDRESS_LENGTH + 1);
        $result = $this->service->splitAddress($address);

        $this->assertEquals(AddressSplitterService::MAX_ADDRESS_LENGTH, strlen($result['address1']));
        $this->assertEquals(1, strlen($result['address2']));
    }

    /**
     * Test: Dirección exactamente de MAX_TOTAL_LENGTH caracteres (no debe truncar)
     */
    public function testAddressExactlyMaxTotalLengthNoTruncation()
    {
        // MAX_ADDRESS_LENGTH chars + espacio + (MAX_ADDRESS_LENGTH - 1) chars = MAX_TOTAL_LENGTH chars
        $address = str_repeat("A", AddressSplitterService::MAX_ADDRESS_LENGTH) . " " .
                   str_repeat("B", AddressSplitterService::MAX_ADDRESS_LENGTH - 1);
        $result = $this->service->splitAddress($address);

        // No debe haber truncamiento
        $this->assertLessThanOrEqual(AddressSplitterService::MAX_ADDRESS_LENGTH, strlen($result['address1']));
        $this->assertLessThanOrEqual(AddressSplitterService::MAX_ADDRESS_LENGTH, strlen($result['address2']));

        // No debe escribirse log de truncamiento
        if (file_exists($this->logFile)) {
            $logContent = file_get_contents($this->logFile);
            // Si existe el archivo, no debe contener WARNING (o estar vacío)
            if (!empty($logContent)) {
                $this->assertStringNotContainsString('WARNING: Address truncated', $logContent);
            }
        }
    }

    /**
     * Test: Dirección con espacios al inicio y final (debe hacer trim)
     */
    public function testAddressWithLeadingAndTrailingSpaces()
    {
        $address = "   Calle 123 #45-67   ";
        $result = $this->service->splitAddress($address);

        $this->assertEquals("Calle 123 #45-67", $result['address1']);
        $this->assertEquals('', $result['address2']);
    }

    /**
     * Test: Caso real - dirección colombiana típica
     */
    public function testRealColombianAddressExample()
    {
        $address = "Carrera 15 #123-45 Apartamento 801 Torre Norte Conjunto Residencial Los Arrayanes Etapa 2";
        $result = $this->service->splitAddress($address);

        // Verificar que se dividió correctamente
        $this->assertLessThanOrEqual(AddressSplitterService::MAX_ADDRESS_LENGTH, strlen($result['address1']));
        $this->assertLessThanOrEqual(AddressSplitterService::MAX_ADDRESS_LENGTH, strlen($result['address2']));

        // La dirección combinada debe contener elementos clave
        $combined = $result['address1'] . ' ' . $result['address2'];
        $this->assertStringContainsString('Carrera 15', $combined);
        $this->assertStringContainsString('#123-45', $combined);
    }

    /**
     * Test: Sin orderId proporcionado (no debe fallar)
     */
    public function testTruncationWithoutOrderId()
    {
        $address = str_repeat("Direccion muy larga ", 6); // 120 chars
        $result = $this->service->splitAddress($address, null);

        // Debe truncar normalmente
        $this->assertLessThanOrEqual(AddressSplitterService::MAX_ADDRESS_LENGTH, strlen($result['address1']));
        $this->assertLessThanOrEqual(AddressSplitterService::MAX_ADDRESS_LENGTH, strlen($result['address2']));
    }

    /**
     * Test: Escenario A - address2 NO existe, debe dividir
     */
    public function testSplitAddressWithOriginalScenarioA()
    {
        $address1 = "Calle 123 Torre 5 Apartamento 301 Edificio Central";
        $address2 = null;

        $result = $this->service->splitAddressWithOriginal($address1, $address2);

        $this->assertLessThanOrEqual(AddressSplitterService::MAX_ADDRESS_LENGTH, strlen($result['address1']));
        $this->assertLessThanOrEqual(AddressSplitterService::MAX_ADDRESS_LENGTH, strlen($result['address2']));
        $this->assertEquals($address1, $result['full_address']);
        $this->assertTrue($result['was_split']);
        $this->assertFalse($result['had_original_address2']);
    }

    /**
     * Test: Escenario B - address2 SÍ existe, debe truncar ambos
     */
    public function testSplitAddressWithOriginalScenarioB()
    {
        $address1 = "Transversal 65a #32 56";
        $address2 = "Torre 2 Apt 607";

        $result = $this->service->splitAddressWithOriginal($address1, $address2);

        $this->assertLessThanOrEqual(AddressSplitterService::MAX_ADDRESS_LENGTH, strlen($result['address1']));
        $this->assertLessThanOrEqual(AddressSplitterService::MAX_ADDRESS_LENGTH, strlen($result['address2']));
        $this->assertEquals("Transversal 65a #32 56 Torre 2 Apt 607", $result['full_address']);
        $this->assertFalse($result['was_split']);
        $this->assertTrue($result['had_original_address2']);
    }

    /**
     * Test: Escenario A con dirección corta que cabe en MAX_ADDRESS_LENGTH caracteres
     */
    public function testSplitAddressWithOriginalShortAddress()
    {
        $address1 = "Calle 123";
        $address2 = null;

        $result = $this->service->splitAddressWithOriginal($address1, $address2);

        $this->assertEquals("Calle 123", $result['address1']);
        $this->assertEquals('', $result['address2']);
        $this->assertEquals("Calle 123", $result['full_address']);
        $this->assertFalse($result['was_split']);
        $this->assertFalse($result['had_original_address2']);
    }

    /**
     * Test: Escenario B con address2 vacío (debe tratarse como Escenario A)
     */
    public function testSplitAddressWithOriginalEmptyAddress2()
    {
        $address1 = "Carrera 15 #123-45 Apartamento 502 Torre Ejecutiva";
        $address2 = "";

        $result = $this->service->splitAddressWithOriginal($address1, $address2);

        $this->assertFalse($result['had_original_address2']);
        $this->assertTrue($result['was_split']);
    }

    /**
     * Test: Verificar que full_address nunca pierde información
     */
    public function testFullAddressNeverLosesInformation()
    {
        $testCases = [
            ['Calle muy larga con muchos caracteres', null],
            ['Transversal 65a #32 56', 'Torre 2 Apt 607 Interior 303'],
            ['Dirección super ultra mega larga', 'Con complemento también largo'],
        ];

        foreach ($testCases as [$addr1, $addr2]) {
            $result = $this->service->splitAddressWithOriginal($addr1, $addr2);

            $expected = trim($addr1 . ($addr2 ? ' ' . $addr2 : ''));
            $this->assertEquals($expected, $result['full_address']);
        }
    }

    /**
     * Test: Logging cuando se hace split (Escenario A)
     */
    public function testLoggingWhenSplitOccurs()
    {
        $address1 = "Calle 123 Torre 5 Apartamento 890 Conjunto Los Pinos";
        $orderId = "TEST456";

        $result = $this->service->splitAddressWithOriginal($address1, null, $orderId);

        // Verificar que se procesó correctamente
        $this->assertNotEmpty($result['address1']);
        $this->assertNotEmpty($result['address2']);
        $this->assertTrue($result['was_split']);
        $this->assertEquals($address1, $result['full_address']);
    }

    /**
     * Test: Logging cuando se trunca (Escenario B)
     */
    public function testLoggingWhenTruncateOccurs()
    {
        $address1 = "Transversal 65a #32 56 Extra";
        $address2 = "Torre 2 Apt 607 Interior 303";
        $orderId = "TEST789";

        $result = $this->service->splitAddressWithOriginal($address1, $address2, $orderId);

        // Verificar que se procesó correctamente
        $this->assertNotEmpty($result['address1']);
        $this->assertNotEmpty($result['address2']);
        $this->assertTrue($result['had_original_address2']);
        $this->assertFalse($result['was_split']);
        $this->assertStringContainsString($address1, $result['full_address']);
        $this->assertStringContainsString($address2, $result['full_address']);
    }

    /**
     * Test: Edge case - Ambos campos largos en Escenario B
     */
    public function testScenarioBWithBothFieldsExceedingLimit()
    {
        $address1 = "Avenida Libertador General San Martin";
        $address2 = "Torre Ejecutiva Piso 15 Oficina 1501";

        $result = $this->service->splitAddressWithOriginal($address1, $address2);

        // Ambos deben truncarse respetando word boundary (máximo MAX_ADDRESS_LENGTH chars)
        $this->assertLessThanOrEqual(AddressSplitterService::MAX_ADDRESS_LENGTH, strlen($result['address1']));
        $this->assertLessThanOrEqual(AddressSplitterService::MAX_ADDRESS_LENGTH, strlen($result['address2']));
        $this->assertNotEmpty($result['address1']);
        $this->assertNotEmpty($result['address2']);

        // full_address debe tener la dirección completa
        $fullExpected = "Avenida Libertador General San Martin Torre Ejecutiva Piso 15 Oficina 1501";
        $this->assertEquals($fullExpected, $result['full_address']);

        $this->assertTrue($result['had_original_address2']);
        $this->assertFalse($result['was_split']);
    }
}
