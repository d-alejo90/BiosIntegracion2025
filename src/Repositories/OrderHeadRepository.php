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

    public function cancelOrder($orderName, $CodigoCia)
    {
        $sql = "UPDATE Order_head SET date_cancel = GETDATE(), cancel = '1' WHERE order_name = :order_name AND CodigoCia = :CodigoCia";
        $query = $this->db->prepare($sql);
        $query->execute([
            ':order_name' => $orderName,
            ':CodigoCia' => $CodigoCia,
        ]);
    }

    public function getPendingFulfillments($CodigoCia)
    {
        $sql = "SELECT
        TOP(50)
        Order_head.order_id
        FROM
        Order_head
        WHERE Order_head.audit_date >= DATEADD(DAY, -7, CAST(GETDATE() AS DATE))
        AND Order_head.FacturaSiesa = '1'
        AND Order_head.Despacho = '0' 
        AND Order_head.CodigoCia = :CodigoCia;";
        $statement = $this->db->prepare($sql);
        $statement->execute(['CodigoCia' => $CodigoCia]);
        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function updateOrderFulfillmentStatus($orderId, $CodigoCia)
    {
        $orderId = end(explode("/", $orderId));
        $sql = "UPDATE Order_head SET Despacho = '2', audit_date = GETDATE() WHERE order_id = :order_id AND CodigoCia = :CodigoCia";
        $statement = $this->db->prepare($sql);
        $statement->execute([
            ':order_id' => $orderId,
            ':CodigoCia' => $CodigoCia
        ]);
        return $statement->rowCount();
    }
}
