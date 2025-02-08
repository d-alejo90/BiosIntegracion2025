<?php

namespace App\Config;

final class Constants
{
    private function __construct()
    {
        throw new \Exception('No se puede instanciar la clase ' . __CLASS__);
    }

    // Constante con un array de códigos postales
    public const ZIP_CODES_CAMPO_AZUL = [
      "Bogotá" => 701,
      "Medellín" => 735,
      "Bello" => 735,
      "Envigado" => 735,
      "Itaguí" => 735,
      "Sabaneta" => 735,
      "La Estrella" => 735,
      "Rionegro" => 773,
      "Bucaramanga" => 712,
      "Pereira" => 726,
      "Bog" => 701,
      "Buc" => 712,
      "Rio" => 773,
      "Med" => 735,
      "Bel" => 735,
      "Env" => 735,
      "Ita" => 735,
      "Sab" => 735,
      "La " => 735,
      "Per" => 726,
    ];

    public const ZIP_CODES_MIZOOCO = [
      "Bogotá" => 342,
      "Medellín" => 366,
      "Bello" => 366,
      "Envigado" => 366,
      "Itaguí" => 366,
      "Sabaneta" => 366,
      "La Estrella" => 366,
      "Pereira" => 335,
      "Barranquilla" => 340,
      "Bucaramanga" => 302,
      "Cali" => 372,
      "Bog" => 342,
      "Med" => 366,
      "Bel" => 366,
      "Env" => 366,
      "Ita" => 366,
      "Sab" => 366,
      "La " => 366,
      "Per" => 335,
      "Bar" => 340,
      "Buc" => 302,
      "Cal" => 372
    ];

    public const ZIP_CODES = [
      "campo_azul" => self::ZIP_CODES_CAMPO_AZUL,
      "mizooco" => self::ZIP_CODES_MIZOOCO
    ];

    public const FECHA_DE_NACIMIENTO = "1989-07-01 09:00:00";
}
