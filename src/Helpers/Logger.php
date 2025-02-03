<?php

namespace App\Helpers;

class Logger
{
    public static function log($fileName, $data)
    {
        // Obtener la fecha actual
        $year = date('Y');
        $month = date('m');
        $day = date('d');

        $directory = __DIR__ . "/../../logs/$year/$month/$day";

        if (!file_exists($directory)) {
            mkdir($directory, 0777, true);
        }

        $filePath = $directory . '/' . $fileName;

        $log = fopen($filePath, "a");
        if (!$log) {
            throw new \Exception("No se puede abrir o crear el archivo $filePath.");
        }

        fwrite($log, print_r($data . "\n", true));

        fclose($log);

        return $filePath;
    }
}
