<?php

namespace App\Repositories;

use App\Models\Customer;
use App\Config\Database;

class CustomerRepository
{
    private $db;

    public function __construct()
    {
        $database = Database::getInstance();
        $this->db = $database->getConnection();
    }

    public function create(Customer $customer)
    {
        $sql = "INSERT INTO Customers (
            order_id, customer_id, company, first_name, last_name, name, address1, address2, 
            billing_address1, billing_address2, shipping_address1, shipping_address2, 
            currency, phone, zip, email, tags, cc_registro, nombre_registro, apellido_registro, 
            fecha_nacimiento, billing_nombre, billing_apellido, CodigoCia
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $statement = $this->db->prepare($sql);
        return $statement->execute([
            $customer->order_id,
            $customer->customer_id,
            $customer->company,
            $customer->first_name,
            $customer->last_name,
            $customer->name,
            $customer->address1,
            $customer->address2,
            $customer->billing_address1,
            $customer->billing_address2,
            $customer->shipping_address1,
            $customer->shipping_address2,
            $customer->currency,
            $customer->phone,
            $customer->zip,
            $customer->email,
            $customer->tags,
            $customer->cc_registro,
            $customer->nombre_registro,
            $customer->apellido_registro,
            $customer->fecha_nacimiento,
            $customer->billing_nombre,
            $customer->billing_apellido,
            $customer->CodigoCia
        ]);
    }
}
