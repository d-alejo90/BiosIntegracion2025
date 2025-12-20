<?php

namespace App\Services;

use App\Helpers\Logger;

class AddressSplitterService
{
    private const MAX_ADDRESS_LENGTH = 40;
    private const MAX_TOTAL_LENGTH = 80;

    private string $logFile;

    public function __construct(string $logFile)
    {
        $this->logFile = $logFile;
    }

    /**
     * Divide una dirección en address1 y address2 respetando el límite de 40 caracteres por campo
     *
     * @param string $fullAddress Dirección completa a dividir
     * @param string|null $orderId ID de la orden para logging (opcional)
     * @return array Array con keys 'address1' y 'address2'
     */
    public function splitAddress(string $fullAddress, ?string $orderId = null): array
    {
        $fullAddress = trim($fullAddress);

        // Si la dirección cabe completa en address1
        if (strlen($fullAddress) <= self::MAX_ADDRESS_LENGTH) {
            return [
                'address1' => $fullAddress,
                'address2' => ''
            ];
        }

        // Dividir en word boundary
        $split = $this->splitAtWordBoundary($fullAddress, self::MAX_ADDRESS_LENGTH);
        $address1 = $split['address1'];
        $address2Temp = $split['address2'];

        // Si address2 también excede el límite, necesitamos truncar
        if (strlen($address2Temp) > self::MAX_ADDRESS_LENGTH) {
            // Dividir address2 también
            $secondSplit = $this->splitAtWordBoundary($address2Temp, self::MAX_ADDRESS_LENGTH);
            $address2 = $secondSplit['address1'];

            // Si hay texto sobrante, registrar warning
            if (!empty($secondSplit['address2'])) {
                $this->logTruncatedAddress($fullAddress, $orderId);
            }
        } else {
            $address2 = $address2Temp;
        }

        // Verificar longitud total
        $totalLength = strlen($address1) + strlen($address2);
        if (strlen($fullAddress) > self::MAX_TOTAL_LENGTH && $totalLength < strlen($fullAddress)) {
            $this->logTruncatedAddress($fullAddress, $orderId);
        }

        return [
            'address1' => $address1,
            'address2' => $address2
        ];
    }

    /**
     * Divide una dirección en un punto de word boundary (espacio)
     *
     * @param string $address Dirección a dividir
     * @param int $maxLength Longitud máxima del primer segmento
     * @return array Array con 'address1' y 'address2'
     */
    private function splitAtWordBoundary(string $address, int $maxLength): array
    {
        // Si la dirección cabe completa
        if (strlen($address) <= $maxLength) {
            return ['address1' => $address, 'address2' => ''];
        }

        // Encontrar el último espacio antes del límite
        $splitPoint = strrpos(substr($address, 0, $maxLength + 1), ' ');

        // Edge case: si no hay espacios (palabra muy larga), cortar forzosamente
        if ($splitPoint === false || $splitPoint === 0) {
            $splitPoint = $maxLength;
        }

        // Dividir y limpiar espacios
        $part1 = trim(substr($address, 0, $splitPoint));
        $part2 = trim(substr($address, $splitPoint));

        return ['address1' => $part1, 'address2' => $part2];
    }

    /**
     * Registra un warning cuando una dirección se trunca por exceder 80 caracteres
     *
     * @param string $fullAddress Dirección completa original
     * @param string|null $orderId ID de la orden
     * @return void
     */
    private function logTruncatedAddress(string $fullAddress, ?string $orderId): void
    {
        $orderInfo = $orderId ? " for order $orderId" : "";
        $message = "WARNING: Address truncated$orderInfo. Full address: $fullAddress (Length: " . strlen($fullAddress) . " chars)";
        Logger::log($this->logFile, $message);
    }
}
