<?php

namespace Tests\Unit\Helpers;

use App\Helpers\LocationHelper;
use PHPUnit\Framework\TestCase;

class LocationHelperTest extends TestCase
{
    /**
     * Test: Location ID válido retorna el mismo ID
     */
    public function testValidLocationIdReturnsId()
    {
        $result = LocationHelper::normalizeLocation('89918046504', 'mizooco');

        $this->assertEquals('89918046504', $result);
    }

    /**
     * Test: Nombre con tilde retorna ID correcto
     */
    public function testNameWithAccentReturnsId()
    {
        $result = LocationHelper::normalizeLocation('Bogotá', 'mizooco');

        $this->assertEquals('89918046504', $result);
    }

    /**
     * Test: Nombre SIN tilde retorna ID correcto
     */
    public function testNameWithoutAccentReturnsId()
    {
        $result = LocationHelper::normalizeLocation('Bogota', 'mizooco');

        $this->assertEquals('89918046504', $result);
    }

    /**
     * Test: Nombre en minúsculas sin tilde retorna ID correcto
     */
    public function testLowercaseNameWithoutAccentReturnsId()
    {
        $result = LocationHelper::normalizeLocation('bogota', 'mizooco');

        $this->assertEquals('89918046504', $result);
    }

    /**
     * Test: Nombre en mayúsculas sin tilde retorna ID correcto
     */
    public function testUppercaseNameWithoutAccentReturnsId()
    {
        $result = LocationHelper::normalizeLocation('BOGOTA', 'mizooco');

        $this->assertEquals('89918046504', $result);
    }

    /**
     * Test: Medellín con tilde retorna ID correcto
     */
    public function testMedellinWithAccentReturnsId()
    {
        $result = LocationHelper::normalizeLocation('Medellín', 'mizooco');

        $this->assertEquals('89917882664', $result);
    }

    /**
     * Test: Medellin sin tilde retorna ID correcto
     */
    public function testMedellinWithoutAccentReturnsId()
    {
        $result = LocationHelper::normalizeLocation('Medellin', 'mizooco');

        $this->assertEquals('89917882664', $result);
    }

    /**
     * Test: medellin minúsculas sin tilde retorna ID correcto
     */
    public function testMedellinLowercaseWithoutAccentReturnsId()
    {
        $result = LocationHelper::normalizeLocation('medellin', 'mizooco');

        $this->assertEquals('89917882664', $result);
    }

    /**
     * Test: Location inválida retorna null
     */
    public function testInvalidLocationReturnsNull()
    {
        $result = LocationHelper::normalizeLocation('CiudadInventada', 'mizooco');

        $this->assertNull($result);
    }

    /**
     * Test: Store inválido retorna null
     */
    public function testInvalidStoreReturnsNull()
    {
        $result = LocationHelper::normalizeLocation('Bogota', 'tienda_inventada');

        $this->assertNull($result);
    }

    /**
     * Test: Location vacía retorna null
     */
    public function testEmptyLocationReturnsNull()
    {
        $result = LocationHelper::normalizeLocation('', 'mizooco');

        $this->assertNull($result);
    }

    /**
     * Test: Location null retorna null
     */
    public function testNullLocationReturnsNull()
    {
        $result = LocationHelper::normalizeLocation(null, 'mizooco');

        $this->assertNull($result);
    }

    /**
     * Test: Campo Azul - Barranquilla funciona
     */
    public function testCampoAzulBarranquillaReturnsId()
    {
        $result = LocationHelper::normalizeLocation('Barranquilla', 'campo_azul');

        $this->assertEquals('64213581870', $result);
    }

    /**
     * Test: Campo Azul - barranquilla minúsculas funciona
     */
    public function testCampoAzulBarranquillaLowercaseReturnsId()
    {
        $result = LocationHelper::normalizeLocation('barranquilla', 'campo_azul');

        $this->assertEquals('64213581870', $result);
    }

    /**
     * Test: getLocationName retorna nombre correcto
     */
    public function testGetLocationNameReturnsCorrectName()
    {
        $result = LocationHelper::getLocationName('89918046504', 'mizooco');

        $this->assertEquals('Bogotá', $result);
    }

    /**
     * Test: getLocationName con ID inválido retorna null
     */
    public function testGetLocationNameInvalidIdReturnsNull()
    {
        $result = LocationHelper::getLocationName('123456789', 'mizooco');

        $this->assertNull($result);
    }

    /**
     * Test: getLocationName con store inválido retorna null
     */
    public function testGetLocationNameInvalidStoreReturnsNull()
    {
        $result = LocationHelper::getLocationName('89918046504', 'tienda_inventada');

        $this->assertNull($result);
    }

    /**
     * @dataProvider accentVariationsProvider
     */
    public function testAccentVariationsAllResolveToSameId(string $input, string $expectedId)
    {
        $result = LocationHelper::normalizeLocation($input, 'mizooco');

        $this->assertEquals($expectedId, $result);
    }

    public static function accentVariationsProvider(): array
    {
        return [
            'Bogotá con tilde' => ['Bogotá', '89918046504'],
            'Bogota sin tilde' => ['Bogota', '89918046504'],
            'BOGOTÁ mayúsculas con tilde' => ['BOGOTÁ', '89918046504'],
            'BOGOTA mayúsculas sin tilde' => ['BOGOTA', '89918046504'],
            'bogotá minúsculas con tilde' => ['bogotá', '89918046504'],
            'bogota minúsculas sin tilde' => ['bogota', '89918046504'],
            'Medellín con tilde' => ['Medellín', '89917882664'],
            'Medellin sin tilde' => ['Medellin', '89917882664'],
            'MEDELLÍN mayúsculas con tilde' => ['MEDELLÍN', '89917882664'],
            'MEDELLIN mayúsculas sin tilde' => ['MEDELLIN', '89917882664'],
            'medellín minúsculas con tilde' => ['medellín', '89917882664'],
            'medellin minúsculas sin tilde' => ['medellin', '89917882664'],
        ];
    }
}
