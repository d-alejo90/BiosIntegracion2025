<?php

namespace App\Services;

use App\Helpers\Logger;

class BillingAddressValidatorService
{
    private const DEFAULT_CEDULA = '222222222222';

    private string $logFile;

    public function __construct(string $logFile)
    {
        $this->logFile = $logFile;
    }

    /**
     * Valida y retorna la dirección de facturación, aplicando fallback a shipping si es necesario
     *
     * @param array $billingAddress Dirección de facturación del usuario
     * @param array $shippingAddress Dirección de envío (fallback)
     * @return array Dirección validada
     */
    public function validateAndGetBillingAddress(array $billingAddress, array $shippingAddress): array
    {
        // Si billing address está completa, usarla
        if ($this->isAddressComplete($billingAddress)) {
            return $billingAddress;
        }

        // Si billing está incompleta/vacía, usar shipping address como fallback
        $this->logAddressReplacement(
            'Billing address incomplete or empty, using shipping address',
            $billingAddress,
            $shippingAddress
        );

        return $shippingAddress;
    }

    /**
     * Valida y retorna la cédula, usando el valor por defecto si no se proporciona
     *
     * @param string|null $cedula Cédula del usuario
     * @param string|null $orderId ID de la orden para logging (opcional)
     * @return string Cédula validada
     */
    public function validateAndGetCedula(?string $cedula, ?string $orderId = null): string
    {
        // Si la cédula está presente y no está vacía, usarla
        if (!empty($cedula) && trim($cedula) !== '') {
            return trim($cedula);
        }

        // Si la cédula es null o vacía, usar cédula por defecto
        $this->logDefaultCedulaUsage($orderId);

        return self::DEFAULT_CEDULA;
    }

    /**
     * Verifica si una dirección tiene los campos mínimos requeridos
     *
     * @param array $address Dirección a validar
     * @return bool True si la dirección está completa, false si está incompleta o vacía
     */
    private function isAddressComplete(array $address): bool
    {
        // Verificar que address1 exista y no esté vacío
        if (empty($address['address1']) || trim($address['address1']) === '') {
            return false;
        }

        // Verificar que tenga al menos ciudad
        if (empty($address['city']) || trim($address['city']) === '') {
            return false;
        }

        return true;
    }

    /**
     * Registra en log cuando se reemplaza una dirección
     *
     * @param string $reason Razón del reemplazo
     * @param array $original Dirección original
     * @param array $replacement Dirección de reemplazo
     * @return void
     */
    private function logAddressReplacement(string $reason, array $original, array $replacement): void
    {
        $originalStr = $this->addressToString($original);
        $replacementStr = $this->addressToString($replacement);

        $message = "Address replacement: $reason | Original: [$originalStr] | Replacement: [$replacementStr]";
        Logger::log($this->logFile, $message);
    }

    /**
     * Registra en log cuando se usa la cédula por defecto
     *
     * @param string|null $orderId ID de la orden
     * @return void
     */
    private function logDefaultCedulaUsage(?string $orderId): void
    {
        $orderInfo = $orderId ? " for order $orderId" : "";
        $message = "Default cedula used$orderInfo: " . self::DEFAULT_CEDULA;
        Logger::log($this->logFile, $message);
    }

    /**
     * Convierte un array de dirección a string para logging
     *
     * @param array $address Dirección
     * @return string Representación en string de la dirección
     */
    private function addressToString(array $address): string
    {
        if (empty($address)) {
            return 'empty';
        }

        $parts = [];

        if (!empty($address['address1'])) {
            $parts[] = $address['address1'];
        }

        if (!empty($address['address2'])) {
            $parts[] = $address['address2'];
        }

        if (!empty($address['city'])) {
            $parts[] = $address['city'];
        }

        if (!empty($address['province'])) {
            $parts[] = $address['province'];
        }

        if (!empty($address['country'])) {
            $parts[] = $address['country'];
        }

        return !empty($parts) ? implode(', ', $parts) : 'incomplete';
    }
}
