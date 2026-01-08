<?php

namespace App\Services;

use App\Helpers\Logger;

class AddressSplitterService
{
    public const MAX_ADDRESS_LENGTH = 40;
    public const MAX_TOTAL_LENGTH = 80;

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

    /**
     * Divide una dirección considerando si address2 ya existía en el payload original
     *
     * Maneja dos escenarios:
     * A) address2 NO existía: divide fullAddress en address1 (40 chars) y address2 (resto)
     * B) address2 SÍ existía: trunca ambos a 40 caracteres independientemente
     *
     * @param string $address1Original Dirección 1 del payload de Shopify
     * @param string|null $address2Original Dirección 2 del payload (puede ser null/vacío)
     * @param string|null $orderId ID de la orden para logging (opcional)
     * @return array Array con keys: address1, address2, full_address, was_split, had_original_address2
     */
    public function splitAddressWithOriginal(
        string $address1Original,
        ?string $address2Original,
        ?string $orderId = null
    ): array
    {
        $address1Original = trim($address1Original);
        $address2Original = trim($address2Original ?? '');

        // Construir dirección completa (siempre sin pérdida de información)
        $fullAddress = $address1Original;
        if (!empty($address2Original)) {
            $fullAddress .= ' ' . $address2Original;
        }

        $hadOriginalAddress2 = !empty($address2Original);

        // ESCENARIO A: address2 NO existía en payload original
        if (!$hadOriginalAddress2) {
            return $this->handleScenarioA($fullAddress, $orderId);
        }

        // ESCENARIO B: address2 SÍ existía en payload original
        return $this->handleScenarioB($address1Original, $address2Original, $fullAddress, $orderId);
    }

    /**
     * Escenario A: address2 NO existía - divide fullAddress en dos partes
     *
     * @param string $fullAddress Dirección completa a dividir
     * @param string|null $orderId ID de orden para logging
     * @return array
     */
    private function handleScenarioA(string $fullAddress, ?string $orderId): array
    {
        // Si cabe completa en address1, no dividir
        if (strlen($fullAddress) <= self::MAX_ADDRESS_LENGTH) {
            return [
                'address1' => $fullAddress,
                'address2' => '',
                'full_address' => $fullAddress,
                'was_split' => false,
                'had_original_address2' => false
            ];
        }

        // Dividir en word boundary
        $split = $this->splitAtWordBoundary($fullAddress, self::MAX_ADDRESS_LENGTH);
        $address1 = $split['address1'];
        $address2Remaining = $split['address2'];

        // Si address2 también excede límite, truncar
        $address2 = $this->truncateToLimit($address2Remaining, self::MAX_ADDRESS_LENGTH);

        // Log si hay texto truncado
        if (strlen($address2Remaining) > self::MAX_ADDRESS_LENGTH) {
            $this->logAddressSplit($fullAddress, $address1, $address2, $orderId, 'split');
        }

        return [
            'address1' => $address1,
            'address2' => $address2,
            'full_address' => $fullAddress,
            'was_split' => true,
            'had_original_address2' => false
        ];
    }

    /**
     * Escenario B: address2 SÍ existía - truncar ambos independientemente
     *
     * @param string $address1Original Address1 original
     * @param string $address2Original Address2 original
     * @param string $fullAddress Dirección completa combinada
     * @param string|null $orderId ID de orden para logging
     * @return array
     */
    private function handleScenarioB(
        string $address1Original,
        string $address2Original,
        string $fullAddress,
        ?string $orderId
    ): array
    {
        $address1 = $this->truncateToLimit($address1Original, self::MAX_ADDRESS_LENGTH);
        $address2 = $this->truncateToLimit($address2Original, self::MAX_ADDRESS_LENGTH);

        // Log si se truncó alguno
        $wasTruncated =
            strlen($address1Original) > self::MAX_ADDRESS_LENGTH ||
            strlen($address2Original) > self::MAX_ADDRESS_LENGTH;

        if ($wasTruncated) {
            $this->logAddressSplit($fullAddress, $address1, $address2, $orderId, 'truncate');
        }

        return [
            'address1' => $address1,
            'address2' => $address2,
            'full_address' => $fullAddress,
            'was_split' => false,
            'had_original_address2' => true
        ];
    }

    /**
     * Trunca un texto al límite especificado respetando word boundary
     *
     * @param string $text Texto a truncar
     * @param int $maxLength Longitud máxima
     * @return string Texto truncado
     */
    private function truncateToLimit(string $text, int $maxLength): string
    {
        if (strlen($text) <= $maxLength) {
            return $text;
        }

        // Intentar cortar en word boundary
        $split = $this->splitAtWordBoundary($text, $maxLength);
        return $split['address1'];
    }

    /**
     * Registra información sobre división o truncamiento de dirección
     *
     * @param string $fullAddress Dirección completa
     * @param string $address1 Address1 resultante
     * @param string $address2 Address2 resultante
     * @param string|null $orderId ID de orden
     * @param string $type Tipo: 'split' o 'truncate'
     * @return void
     */
    private function logAddressSplit(
        string $fullAddress,
        string $address1,
        string $address2,
        ?string $orderId,
        string $type
    ): void
    {
        $orderInfo = $orderId ? " for order $orderId" : "";

        if ($type === 'split') {
            $message = "INFO: Address split from single field{$orderInfo}. " .
                       "Full: '$fullAddress' (" . strlen($fullAddress) . " chars) → " .
                       "Address1: '$address1' (" . strlen($address1) . " chars), " .
                       "Address2: '$address2' (" . strlen($address2) . " chars)";
        } else {
            $message = "WARNING: Address truncated{$orderInfo} (address2 already existed). " .
                       "Full: '$fullAddress' (" . strlen($fullAddress) . " chars) → " .
                       "Address1: '$address1' (" . strlen($address1) . " chars), " .
                       "Address2: '$address2' (" . strlen($address2) . " chars)";
        }

        Logger::log($this->logFile, $message);
    }
}
