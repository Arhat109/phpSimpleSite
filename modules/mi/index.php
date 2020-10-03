<?php

use utils\MySql;

/**
 * Главная сайта тут на самом деле..
 *
 * @global array $inUrl
 */
// 0. Читаем локальный файл настроек модуля
require_once 'ini_local.php';

// 1. Настройка PDO
$provider = new DataProvider( new MySql($cthulhu) );

$myData = $provider->getRekvisits(); // 2. Реквизиты фирмы @see int ini.php::$idClient

// Базовый контент для всех страниц:
$title = $myData['strclient'];
$H1 = getContent($mainMenu, ['idClient'=>$idClient, 'myData'=>$myData, 'inUrl'=>$inUrl]);

if( !empty($inUrl['paths']) ){
  // 3.1. Это контроллер: диспетчеризируем ури дальше
  $inUrl['file'] = '';
  $dispatchAutoPaths = false;
  $debugContent .= print_r($inUrl['paths'], true);
  include "dispatch.php";
}else{
  // 3.2. Главная:
  $mainContent .= '
<div id="main" class="page-content" style="font-size: 14px;">
  Главная страница сайта.

  Мы в общем, такие классные .. приходите, Вам понравится - обязательно!
</div>
  ';
}
