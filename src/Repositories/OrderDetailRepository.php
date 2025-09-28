<?php

namespace App\Repositories;

use App\Models\OrderDetail;
use App\Config\Database;

class OrderDetailRepository
{
    private $db;

    public function __construct()
    {
        $database = Database::getInstance();
        $this->db = $database->getConnection();
    }

    public function create(OrderDetail $orderDetail)
    {
        $sql = "INSERT INTO Order_detail (
            order_id, customer_id, created_at, currency, notes, sku, quantity, variant_title, 
            price, price_taxes, discount_amount, discount_target_type, shipping_amount, 
            country_code_shipping, province_code_shipping, city_code_shipping, 
            country_code_billing, province_code_billing, city_code_billing, tags, CodigoCia, flete, motivo_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $statement = $this->db->prepare($sql);
        return $statement->execute([
            $orderDetail->order_id,
            $orderDetail->customer_id,
            $orderDetail->created_at,
            $orderDetail->currency,
            $orderDetail->notes,
            $orderDetail->sku,
            $orderDetail->quantity,
            $orderDetail->variant_title,
            $orderDetail->price,
            $orderDetail->price_taxes,
            $orderDetail->discount_amount,
            $orderDetail->discount_target_type,
            $orderDetail->shipping_amount,
            $orderDetail->country_code_shipping,
            $orderDetail->province_code_shipping,
            $orderDetail->city_code_shipping,
            $orderDetail->country_code_billing,
            $orderDetail->province_code_billing,
            $orderDetail->city_code_billing,
            $orderDetail->tags,
            $orderDetail->CodigoCia,
            $orderDetail->flete,
            $orderDetail->motivo_id
        ]);
    }
}
