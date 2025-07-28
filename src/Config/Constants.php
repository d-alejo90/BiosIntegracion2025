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
    "Vegas" => 800,
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
    "Veg" => 800,
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

  public const BODEGAS_MIZOOCO = [
    "89995608360" => "Barranquilla",
    "89918046504" => "Bogotá",
    "102061080872" => "Bucaramanga",
    "91807318312" => "Cali",
    "89917882664" => "Medellín",
    "102061048104" => "Pereira",
  ];

  public const BODEGAS_CAMPO_AZUL = [
    "64213581870" => "Barranquilla",
    "60916105262" => "Suba-Bogota",
    "61816963118" => "Bucaramanga-Concordia",
    "60906635310" => "Rionegro",
    "60916072494" => "Minorista",
    "61816995886" => "Eje-Cafetero-Pinares",
    "65620377646" => "Campo-Azul-Vegas",
  ];

  public const BODEGAS = [
    "mizooco" => self::BODEGAS_MIZOOCO,
    "campo_azul" => self::BODEGAS_CAMPO_AZUL
  ];
}
