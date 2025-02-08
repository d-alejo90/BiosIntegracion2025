<?php

namespace App\Repositories;

use App\Models\Ciudad;
use App\Config\Database;
use PDO;

class CiudadRepository
{
    private $db;

    public function __construct()
    {
        $database = Database::getInstance();
        $this->db = $database->getConnection();
    }

    /**
     * Obtiene todas las ciudades.
     *
     * @return Ciudad[] Lista de ciudades.
     */
    public function findAll(): array
    {
        // Consulta SQL para obtener todas las ciudades desde la vista vw_Ciudades_Siesa
        $query = "SELECT * FROM vw_Ciudades_Siesa";

        // Prepara la consulta SQL
        $stmt = $this->db->prepare($query);

        // Ejecuta la consulta SQL
        $stmt->execute();

        // Arreglo para almacenar las ciudades mapeadas
        $ciudades = [];

        // Itera sobre el resultado y mapea cada fila a un objeto Ciudad
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $ciudades[] = $this->mapCiudad($row);
        }

        // Retorna la lista de ciudades
        return $ciudades;
    }

    /**
     * Obtiene una ciudad por su ID.
     *
     * @param int $idCiudad ID de la ciudad a obtener.
     *
     * @return Ciudad|null La ciudad obtenida o null si no existe.
     */
    public function findById(int $idCiudad): ?Ciudad
    {
        $query = 'SELECT * FROM vw_Ciudades_Siesa WHERE f013_id = :idCiudad';
        $stmt = $this->db->prepare($query);
        $stmt->execute(['idCiudad' => $idCiudad]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            return $this->mapCiudad($row);
        }

        return null;
    }


    /**
     * Obtiene una ciudad por su nombre y el nombre del departamento.
     *
     * @param string $cityName    Nombre de la ciudad.
     * @param string $departmentName  Nombre del departamento.
     *
     * @return Ciudad|null La ciudad encontrada o null si no existe.
     */
    public function findByCityNameAndDepartmentName(string $cityName, string $departmentName): ?Ciudad
    {
        $query = 'SELECT TOP(1) c.* FROM vw_Ciudades_Siesa AS c JOIN vw_Departamentos_Siesa AS d ON c.f013_id_depto = d.f012_id AND c.f013_id_pais = d.f012_id_pais WHERE d.f012_descripcion LIKE :departmentName AND c.f013_descripcion LIKE :cityName AND c.f013_id_Compañía = 20';
        $stmt = $this->db->prepare($query);
        $stmt->execute(['departmentName' => "%$departmentName%", 'cityName' => "%$cityName%"]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            return $this->mapCiudad($row);
        }

        return null;
    }

    /**
     * Mapea la ciudad desde el resultado de la db.
     * @param row de la db $row
     * @return Ciudad | null
     */
    private function mapCiudad($row): Ciudad | null
    {
        $ciudad = new Ciudad();
        $ciudad->f013_id_Compania = $row['f013_id_Compañía'];
        $ciudad->f013_Desc_Compania = $row['f013_Desc_Compañía'];
        $ciudad->f013_id_pais = $row['f013_id_pais'];
        $ciudad->f013_id_depto = $row['f013_id_depto'];
        $ciudad->f013_id = $row['f013_id'];
        $ciudad->f013_descripcion = $row['f013_descripcion'];
        $ciudad->f013_indicativo = $row['f013_indicativo'];
        return $ciudad;
    }
}
