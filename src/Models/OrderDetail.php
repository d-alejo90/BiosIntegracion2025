<?php

namespace App\Models;

class OrderDetail
{
    public $RowId;
    public $order_id;
    public $customer_id;
    public $created_at;
    public $currency;
    public $notes;
    public $sku;
    public $quantity;
    public $variant_title;
    public $price;
    public $price_taxes;
    public $discount_amount;
    public $discount_target_type;
    public $shipping_amount;
    public $country_code_shipping;
    public $province_code_shipping;
    public $city_code_shipping;
    public $country_code_billing;
    public $province_code_billing;
    public $city_code_billing;
    public $audit_date;
    public $tags;
    public $CodigoCia;
    public $flete;
}
