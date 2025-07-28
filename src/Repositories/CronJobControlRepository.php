<?php

namespace App\Repositories;

use App\Models\CronJobControl;
use App\Config\Database;
use PDO;

class CronJobControlRepository
{
  private $db;

  public function __construct()
  {
    $database = Database::getInstance();
    $this->db = $database->getConnection();
  }

  /**
   * Obtiene todas los cronjobs.
   *
   * @return CronJobControl[] Lista de cronjobs.
   */
  public function findAll(): array
  {
    // Consulta SQL para obtener todas los cronJobs
    $query = "SELECT * FROM cronjob_control";

    // Prepara la consulta SQL
    $stmt = $this->db->prepare($query);

    // Ejecuta la consulta SQL
    $stmt->execute();

    // Arreglo para almacenar los resultados mapeados
    $cronJobs = [];

    // Itera sobre el resultado y mapea cada fila a un objeto Ciudad
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $cronJobs[] = $this->mapCronJob($row);
    }

    // Retorna la lista de cronJobs
    return $cronJobs;
  }

  /**
   * Obtiene el status cronjob por su cron_name.
   * @param $cron_name
   * @return bool | null
   */
  public function getStatusByCronName($cron_name): bool | null
  {
    // Consulta SQL
    $query = "SELECT status FROM cronjob_control WHERE cron_name = :cron_name";

    // Prepara la consulta SQL
    $stmt = $this->db->prepare($query);

    // Establece el parámetro de la consulta
    $stmt->bindValue(':cron_name', $cron_name, PDO::PARAM_STR);

    // Ejecuta la consulta SQL
    $stmt->execute();

    // Obtiene el resultado
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Retorna
    if ($result) {
      if ($result[0]['status'] == 1) {
        return true;
      } else {
        return false;
      }
    } else {
      return null;
    }
  }

  /**
   * Actualiza el status de un cronjob.
   * @param status - nuevo status a cambiar
   * @return bool - true si el status se actualizo correctamente.
   */
  public function updateStatus($id, $status, $changed_by): bool
  {
    // Consulta SQL
    $query = "UPDATE cronjob_control SET status = :status, last_change = NOW(), changed_by = :changed_by WHERE id = :id";

    // Prepara la consulta SQL
    $stmt = $this->db->prepare($query);

    // Establece los parámetros de la consulta
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->bindValue(':status', $status, PDO::PARAM_INT);
    $stmt->bindValue(':changed_by', $changed_by, PDO::PARAM_INT);

    // Ejecuta la consulta SQL
    $stmt->execute();

    // Retorna
    return $stmt->affectedRows > 0;
  }


  /**
   * Mapea el cronJob desde el resultado de la db.
   * @param row de la db $row
   * @return CronJobControl | null
   */
  private function mapCronJob($row): CronJobControl | null
  {
    $cronJob = new CronJobControl();
    $cronJob->id = $row['ID'];
    $cronJob->cron_name = $row['cron_name'];
    $cronJob->cron_desc = $row['cron_desc'];
    $cronJob->status = $row['status'];
    $cronJob->last_change = $row['last_change'];
    $cronJob->changed_by = $row['changed_by'];

    return $cronJob;
  }
}
