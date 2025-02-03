<?php

namespace App\Services;

use App\Repositories\OrderHeadRepository;
use App\Repositories\OrderDetailRepository;
use App\Repositories\CiudadRepository;
use App\Repositories\CustomerRepository;
use App\Helpers\ShopifyHelper;
use App\Helpers\Logger;
use App\Helpers\StoreConfigFactory;
use App\Models\Customer;
use App\Models\OrderHead;
use App\Models\OrderDetail;
use PgSql\Lob;

require_once __DIR__ . '/../Config/Constants.php';

class OrderService
{
  private $orderHeadRepository;
  private $orderDetailRepository;
  private $codigoCia;
  private $storeName;

  public function __construct($storeUrl)
  {
    $this->orderHeadRepository = new OrderHeadRepository();
    // Obtener la configuración de la tienda basada en la URL
    $storeConfig = new StoreConfigFactory();
    $config = $storeConfig->getConfig($storeUrl);
    $this->codigoCia = $config['codigoCia'];
    $this->storeName = $config['storeName'];
  }

  public function cancelOrder($orderId, $dateCancel)
  {
    $this->orderHeadRepository->cancelOrder($orderId, $dateCancel, $this->codigoCia);
  }
}
