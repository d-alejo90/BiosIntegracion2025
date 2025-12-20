<?php

namespace Tests\Unit\Services;

use App\Services\AddressSplitterService;
use PHPUnit\Framework\TestCase;
use org\bovigo\vfs\vfsStream;

class AddressSplitterServiceTest extends TestCase
{
    private AddressSplitterService $service;
    private string $logFile;

    protected function setUp(): void
    {
        parent::setUp();

        // Create virtual filesystem for logs
        $root = vfsStream::setup('logs');
        $this->logFile = vfsStream::url('logs/test.log');

        $this->service = new AddressSplitterService($this->logFile);
    }

    /**
     * Test: Dirección corta que cabe completa en address1
     */
    public function testAddressFitsInAddress1()
    {
        $address = "Calle 123 #45-67 Apt 8";
        $result = $this->service->splitAddress($address);

        $this->assertEquals($address, $result['address1']);
        $this->assertEquals('', $result['address2']);
    }

    /**
     * Test: Dirección que excede 40 chars pero cabe en 80 chars total
     */
    public function testAddressSplitsAt40Characters()
    {
        // 74 caracteres total
        $address = "Calle 123 #45-67 Apartamento 890 Torre B Conjunto Residencial Los Robles";
        $result = $this->service->splitAddress($address);

        // Debe dividir en el último espacio antes de 40 chars
        $this->assertLessThanOrEqual(40, strlen($result['address1']));
        $this->assertLessThanOrEqual(40, strlen($result['address2']));
        $this->assertNotEmpty($result['address2']);

        // Verificar que no se divide una palabra a la mitad
        $this->assertStringEndsNotWith(' ', $result['address1']);
        $this->assertStringStartsNotWith(' ', $result['address2']);
    }

    /**
     * Test: Palabra única sin espacios mayor a 40 caracteres (corte forzoso)
     */
    public function testSingleWordLongerThan40CharsForcedCut()
    {
        $address = "DireccionSinEspaciosMuyLargaQueSuperaElLimiteDeCaracteresSinPoderDividirse";
        $result = $this->service->splitAddress($address);

        // Debe cortar exactamente en 40 caracteres
        $this->assertEquals(40, strlen($result['address1']));
        $this->assertNotEmpty($result['address2']);

        // El resultado combinado debe preservar la dirección original (hasta 80 chars)
        $combined = $result['address1'] . $result['address2'];
        $this->assertStringStartsWith($combined, $address);
    }

    /**
     * Test: Dirección que excede 80 caracteres total (truncamiento con log)
     */
    public function testAddressExceeds80CharsTruncatesAndLogs()
    {
        // 120 caracteres
        $address = str_repeat("Direccion muy larga ", 6); // "Direccion muy larga " * 6 = 120 chars
        $orderId = "TEST123";

        $result = $this->service->splitAddress($address, $orderId);

        // Debe tener address1 y address2 con max 40 chars cada uno
        $this->assertLessThanOrEqual(40, strlen($result['address1']));
        $this->assertLessThanOrEqual(40, strlen($result['address2']));

        // Verificar que se registró el log
        $this->assertFileExists($this->logFile);
        $logContent = file_get_contents($this->logFile);
        $this->assertStringContainsString('WARNING: Address truncated', $logContent);
        $this->assertStringContainsString('TEST123', $logContent);
        $this->assertStringContainsString($address, $logContent);
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

        // Debe manejar espacios correctamente con trim
        $this->assertStringNotContainsString('  ', $result['address1']);
        $this->assertStringNotContainsString('  ', $result['address2']);
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
     * Test: Dirección exactamente de 40 caracteres
     */
    public function testAddressExactly40Characters()
    {
        $address = str_repeat("A", 40); // Exactamente 40 'A'
        $result = $this->service->splitAddress($address);

        $this->assertEquals(40, strlen($result['address1']));
        $this->assertEquals('', $result['address2']);
    }

    /**
     * Test: Dirección de 41 caracteres (debe dividir)
     */
    public function testAddress41CharactersMustSplit()
    {
        $address = str_repeat("A", 41);
        $result = $this->service->splitAddress($address);

        $this->assertEquals(40, strlen($result['address1']));
        $this->assertEquals(1, strlen($result['address2']));
    }

    /**
     * Test: Dirección exactamente de 80 caracteres (no debe truncar)
     */
    public function testAddressExactly80CharactersNoTruncation()
    {
        // 40 chars + espacio + 39 chars = 80 chars
        $address = str_repeat("A", 40) . " " . str_repeat("B", 39);
        $result = $this->service->splitAddress($address);

        // No debe haber truncamiento
        $this->assertLessThanOrEqual(40, strlen($result['address1']));
        $this->assertLessThanOrEqual(40, strlen($result['address2']));

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
        $this->assertLessThanOrEqual(40, strlen($result['address1']));
        $this->assertLessThanOrEqual(40, strlen($result['address2']));

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
        $this->assertLessThanOrEqual(40, strlen($result['address1']));
        $this->assertLessThanOrEqual(40, strlen($result['address2']));

        // Log debe existir pero sin order ID
        $this->assertFileExists($this->logFile);
        $logContent = file_get_contents($this->logFile);
        $this->assertStringContainsString('WARNING: Address truncated', $logContent);
        $this->assertStringNotContainsString('for order', $logContent);
    }
}
