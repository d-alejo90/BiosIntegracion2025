<?php

namespace App\Helpers;

use Dotenv\Dotenv;

class StoreConfigFactory
{
  private $storeConfigs;

  public function __construct()
  {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
    $dotenv->load();

    $this->storeConfigs = [
      'friko-ecommerce.myshopify.com' => [
        'shopifyConfig' => [
          'ShopUrl' => $_ENV['STORE_URL_CAMPO_AZUL'],
          'AccessToken' => $_ENV['ACCESS_TOKEN_CAMPO_AZUL'],
          'ApiKey' => $_ENV['API_KEY_CAMPO_AZUL'],
          'ApiVersion' => $_ENV['SHOPIFY_API_VERSION'],
        ],
        'storeName' => 'campo_azul',
        'codigoCia' => '232P',
      ],
      'mizooco.myshopify.com' => [
        'shopifyConfig' => [
          'ShopUrl' => $_ENV['STORE_URL_MIZOOCO'],
          'AccessToken' => $_ENV['ACCESS_TOKEN_MIZOOCO'],
          'ApiKey' => $_ENV['API_KEY_MIZOOCO'],
          'ApiVersion' => $_ENV['SHOPIFY_API_VERSION'],
        ],
        'storeName' => 'mizooco',
        'codigoCia' => '232',
      ],
      'campo-azul-institucional.myshopify.com' => [
        'shopifyConfig' => [
          'ShopUrl' => $_ENV['STORE_URL_CAMPO_AZUL_INSTITUCIONAL'],
          'AccessToken' => $_ENV['ACCESS_TOKEN_CAMPO_AZUL_INSTITUCIONAL'],
          'ApiKey' => $_ENV['API_KEY_CAMPO_AZUL_INSTITUCIONAL'],
          'ApiVersion' => $_ENV['SHOPIFY_API_VERSION'],
        ],
        'storeName' => 'campo_azul_institucional',
        'codigoCia' => '232I',
      ],
      // Agrega más tiendas según sea necesario
    ];
  }


  public function getConfig($storeUrl)
  {
    if (isset($this->storeConfigs[$storeUrl])) {
      return $this->storeConfigs[$storeUrl];
    }
    $message = "Configuración no encontrada para la tienda: $storeUrl";
    Logger::log('wh_run.txt', $message);
    throw new \Exception($message, 1);
  }
}
