<?php

namespace App\Services;

use App\Repositories\OrderHeadRepository;
use App\Helpers\StoreConfigFactory;

require_once __DIR__ . '/../Config/Constants.php';

class CancelOrderService
{
    private $orderHeadRepository;
    private $codigoCia;

    public function __construct($storeUrl)
    {
        $this->orderHeadRepository = new OrderHeadRepository();
        // Obtener la configuración de la tienda basada en la URL
        $storeConfig = new StoreConfigFactory();
        $config = $storeConfig->getConfig($storeUrl);
        $this->codigoCia = $config['codigoCia'];
    }

    public function cancelOrder($data)
    {
        $orderId = $data['id'];
        $this->orderHeadRepository->cancelOrder($orderId, $this->codigoCia);
    }
}
