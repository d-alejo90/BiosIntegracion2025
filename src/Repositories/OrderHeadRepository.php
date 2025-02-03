<?php

namespace App\Repositories;

use App\Models\OrderHead;
use App\Config\Database;

class OrderHeadRepository
{
    private $db;

    public function __construct()
    {
        $database = Database::getInstance();
        $this->db = $database->getConnection();
    }

    public function create(OrderHead $orderHead)
    {
        $sql = "INSERT INTO Order_head (order_id, order_name, status, CodigoCia) 
                VALUES (?, ?, ?, ?)";
        $statement = $this->db->prepare($sql);
        return $statement->execute([
            $orderHead->order_id,
            $orderHead->order_name,
            $orderHead->status,
            $orderHead->CodigoCia
        ]);
    }

    public function exists($orderId)
    {
        $sql = "SELECT COUNT(*) FROM Order_head WHERE order_id = ?";
        $statement = $this->db->prepare($sql);
        $statement->execute([$orderId]);
        return $statement->fetchColumn() > 0;
    }

    public function cancelOrder($orderName, $dateCancel, $CodigoCia)
    {
        $sql = "UPDATE Order_head SET date_cancel = :date_cancel, cancel = 1 WHERE order_name = :order_name AND CodigoCia = :CodigoCia";
        $query = $this->db->prepare($sql);
        $query->execute([
            ':date_cancel' => $dateCancel,
            ':order_name' => $orderName,
        ]);
    }
}
