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
          'ApiSecret' => $_ENV['API_SECRET_KEY_CAMPO_AZUL'],
          'ApiVersion' => $_ENV['SHOPIFY_API_VERSION'],
          'WebhookSign' => $_ENV['WEBHOOK_SIGN_CAMPO_AZUL'],
        ],
        'storeName' => 'campo_azul',
        'codigoCia' => '232P',
      ],
      'mizooco.myshopify.com' => [
        'shopifyConfig' => [
          'ShopUrl' => $_ENV['STORE_URL_MIZOOCO'],
          'AccessToken' => $_ENV['ACCESS_TOKEN_MIZOOCO'],
          'ApiKey' => $_ENV['API_KEY_MIZOOCO'],
          'ApiSecret' => $_ENV['API_SECRET_KEY_MIZOOCO'],
          'ApiVersion' => $_ENV['SHOPIFY_API_VERSION'],
          'WebhookSign' => $_ENV['WEBHOOK_SIGN_MIZOOCO'],
        ],
        'storeName' => 'mizooco',
        'codigoCia' => '232',
      ],
      // 'campo-azul-institucional.myshopify.com' => [
      //   'shopifyConfig' => [
      //     'ShopUrl' => $_ENV['STORE_URL_CAMPO_AZUL_INSTITUCIONAL'],
      //     'AccessToken' => $_ENV['ACCESS_TOKEN_CAMPO_AZUL_INSTITUCIONAL'],
      //     'ApiKey' => $_ENV['API_KEY_CAMPO_AZUL_INSTITUCIONAL'],
      //     'ApiSecret' => $_ENV['API_SECRET_KEY_CAMPO_AZUL_INSTITUCIONAL'],
      //     'ApiVersion' => $_ENV['SHOPIFY_API_VERSION'],
      //     'WebhookSign' => $_ENV['WEBHOOK_SIGN_CAMPO_AZUL_INSTITUCIONAL'],
      //   ],
      //   'storeName' => 'campo_azul_institucional',
      //   'codigoCia' => '232I',
      // ],
      // 'pruebasmizooco.myshopify.com' => [
      //   'shopifyConfig' => [
      //     'ShopUrl' => $_ENV['STORE_URL_MIZOOCO_PRUEBAS'],
      //     'AccessToken' => $_ENV['ACCESS_TOKEN_MIZOOCO_PRUEBAS'],
      //     'ApiKey' => $_ENV['API_KEY_MIZOOCO_PRUEBAS'],
      //     'ApiSecret' => $_ENV['API_SECRET_KEY_MIZOOCO_PRUEBAS'],
      //     'ApiVersion' => $_ENV['SHOPIFY_API_VERSION'],
      //     'WebhookSign' => $_ENV['WEBHOOK_SIGN_MIZOOCO_PRUEBAS'],
      //   ],
      //   'storeName' => 'pruebas_mizooco',
      //   'codigoCia' => '232P',
      // ],
      // 'prueba-campoazul.myshopify.com' => [
      //   'shopifyConfig' => [
      //     'ShopUrl' => $_ENV['STORE_URL_CAMPO_AZUL_PRUEBAS'],
      //     'AccessToken' => $_ENV['ACCESS_TOKEN_CAMPO_AZUL_PRUEBAS'],
      //     'ApiKey' => $_ENV['API_KEY_CAMPO_AZUL_PRUEBAS'],
      //     'ApiSecret' => $_ENV['API_SECRET_KEY_CAMPO_AZUL_PRUEBAS'],
      //     'ApiVersion' => $_ENV['SHOPIFY_API_VERSION'],
      //     'WebhookSign' => $_ENV['WEBHOOK_SIGN_CAMPO_AZUL_PRUEBAS'],
      //   ],
      //   'storeName' => 'pruebas_campoazul',
      //   'codigoCia' => '20P',
      // ],
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
