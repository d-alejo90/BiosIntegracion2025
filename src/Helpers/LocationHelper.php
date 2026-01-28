<?php

namespace App\Helpers;

use App\Config\Constants;

class LocationHelper
{
    /**
     * Removes accents/diacritics from a string
     *
     * @param string $string String to normalize
     * @return string String without accents
     */
    private static function removeAccents(string $string): string
    {
        $accents = ['á', 'é', 'í', 'ó', 'ú', 'Á', 'É', 'Í', 'Ó', 'Ú', 'ñ', 'Ñ'];
        $noAccents = ['a', 'e', 'i', 'o', 'u', 'A', 'E', 'I', 'O', 'U', 'n', 'N'];
        return str_replace($accents, $noAccents, $string);
    }

    /**
     * Validates and normalizes location parameter to location ID
     * Accepts both ID (64213581870) and name (Barranquilla)
     * Returns location ID or null if invalid
     *
     * @param string|null $location Location ID or name to normalize
     * @param string $storeName Store name (campo_azul or mizooco)
     * @return string|null Normalized location ID or null if invalid
     */
    public static function normalizeLocation(?string $location, string $storeName): ?string
    {
        if (empty($location)) {
            return null;
        }

        // Get bodegas for the store
        if (!isset(Constants::BODEGAS[$storeName])) {
            return null;
        }

        $bodegas = Constants::BODEGAS[$storeName];

        // Check if it's already a valid location ID
        if (isset($bodegas[$location])) {
            return $location;
        }

        // Search by name (case-insensitive and accent-insensitive)
        $locationNormalized = strtolower(self::removeAccents($location));
        foreach ($bodegas as $id => $name) {
            $nameNormalized = strtolower(self::removeAccents($name));
            if ($nameNormalized === $locationNormalized) {
                return $id;
            }
        }

        return null; // Invalid location
    }

    /**
     * Get location name by ID
     *
     * @param string $locationId Location ID
     * @param string $storeName Store name (campo_azul or mizooco)
     * @return string|null Location name or null if not found
     */
    public static function getLocationName(string $locationId, string $storeName): ?string
    {
        if (!isset(Constants::BODEGAS[$storeName])) {
            return null;
        }

        $bodegas = Constants::BODEGAS[$storeName];
        return $bodegas[$locationId] ?? null;
    }
}
