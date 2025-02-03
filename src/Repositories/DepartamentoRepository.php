<?php

namespace App\Repositories;

use App\Models\Departamento;
use App\Config\Database;
use PDO;

class DepartamentoRepository
{
    private $db;

    public function __construct()
    {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    /**
     * Obtiene todos los departamentos.
     */
    public function findAll()
    {
        $query = "SELECT * FROM vw_Departamentos_Siesa";
        $stmt = $this->db->prepare($query);
        $stmt->execute();

        $departamentos = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $departamento = new Departamento();
            $departamento->f013_id_Compania = $row['f013_id_Compañia'];
            $departamento->f013_Desc_Compania = $row['f013_Desc_Compañia'];
            $departamento->f012_id_pais = $row['f012_id_pais'];
            $departamento->f012_id = $row['f012_id'];
            $departamento->f012_descripcion = $row['f012_descripcion'];
            $departamentos[] = $departamento;
        }

        return $departamentos;
    }

    /**
     * Obtiene un departamento por su ID.
     */
    public function findById($f012_id)
    {
        $query = "SELECT * FROM vw_Departamentos_Siesa WHERE f012_id = :f012_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':f012_id', $f012_id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $departamento = new Departamento();
            $departamento->f013_id_Compania = $row['f013_id_Compañia'];
            $departamento->f013_Desc_Compania = $row['f013_Desc_Compañia'];
            $departamento->f012_id_pais = $row['f012_id_pais'];
            $departamento->f012_id = $row['f012_id'];
            $departamento->f012_descripcion = $row['f012_descripcion'];
            return $departamento;
        }

        return null;
    }

    /**
     * Obtiene departamentos por el ID del país.
     */
    public function findByPaisId($f012_id_pais)
    {
        $query = "SELECT * FROM vw_Departamentos_Siesa WHERE f012_id_pais = :f012_id_pais";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':f012_id_pais', $f012_id_pais);
        $stmt->execute();

        $departamentos = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $departamento = new Departamento();
            $departamento->f013_id_Compania = $row['f013_id_Compañia'];
            $departamento->f013_Desc_Compania = $row['f013_Desc_Compañia'];
            $departamento->f012_id_pais = $row['f012_id_pais'];
            $departamento->f012_id = $row['f012_id'];
            $departamento->f012_descripcion = $row['f012_descripcion'];
            $departamentos[] = $departamento;
        }

        return $departamentos;
    }
}
