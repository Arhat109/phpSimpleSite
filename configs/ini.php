<?php

use utils\Debugging;
use utils\Profiling;

/**
 * Стартовый конфигуратор arhat.su.
 * ... определяем все необходимые базовые константы и глобалы ...
 *
 * @var bool   IS_DEBUG       -- режим общей отладки
 * @var string ROOT_PATH      -- главный каталог всего
 * @var string ROOT_APP       -- корневой каталог всех модулей
 * @var string START_FILE     -- наименование стартового файла для начала диспетчеризации УРЛ (default: index)
 * @var string START_EXT      -- типовое расширение файлов-контроллеров диспетчера (default: .php)
 * @var string SITE_NAME      -- название сайта: HTTP_HOST | 'localhost' для вызовов из консоли
 * @var string SITE_LANG      -- язык клиенту
 * @var string SITE_COUNTRY   -- страна
 * @var string SITE_CHARS     -- кодировка
 * @var string SITE_TIMEZONE
 * @var int    SITE_ONPAGE    -- эл-тов в типовом пагинеторе
 *
 * @var array $siteOnpages    -- @see configs/ini.php простой массив перечисления размеров страниц пагинатора "по сколько можно выводить"
 *
 * @var array $specURI        -- спец. запросы без диспетчеризации, обрамления, буферизации и т.д. (м.быть Zendframework, Yii, etc. далее)
 * @var array $urlMap         -- таблица быстрой диспетчеризации и вызова контроллера по ури страницы.
 * @var array $classFilesMap  -- таблица быстрой загрузки классов
 *
 * @author fvn-20140207..
 *         fvn20190410 - документация, перенос сюда остальной настройки из index.php
 */

// инициализация web-сервера или из командной строки?
if( !isset($_SERVER['HTTP_HOST'])   ) $_SERVER['HTTP_HOST']   = 'localhost';
if( !isset($_SERVER['SERVER_ADDR']) ) $_SERVER['SERVER_ADDR'] = '127.0.0.1';

define('IS_DEBUG', true);
define('ROOT_PATH', $_SERVER['DOCUMENT_ROOT'].'/');	// получаем уже готовый из php-fpm!!!

ini_set('display_errors', 'on');
ini_set('log_error', 'on');
ini_set('error_log', ROOT_PATH.'logs/php_error.log');
ini_set('error_reporting', E_ALL);

define('ROOT_APP' , ROOT_PATH . 'modules/' );
define('START_FILE','index');
define('START_EXT','php');
define('SITE_NAME', $_SERVER['HTTP_HOST']);

define('SITE_LANG', 'ru');
define('SITE_COUNTRY', 'RU');
define('SITE_CHARS', 'UTF-8');
define('SITE_TIMEZONE', 'Asia/Krasnoyarsk');

define('SITE_ONPAGE', 50);
$siteOnpages = [12,24,60,120,240,480];

setlocale(LC_ALL,      SITE_LANG . '_' . SITE_COUNTRY . '.' . SITE_CHARS);
setlocale(LC_MONETARY, SITE_LANG . '_' . SITE_COUNTRY . '.' . SITE_CHARS);

date_default_timezone_set(SITE_TIMEZONE);

mb_internal_encoding(SITE_CHARS);
mb_regex_encoding(SITE_CHARS);

// Настройка путей автозагрузки классов и прочих файлов:
set_include_path(
		ROOT_APP
        . PATH_SEPARATOR . ROOT_PATH			            // общий путь для поиска классов с namespace.
		. PATH_SEPARATOR . ROOT_PATH . 'layouts/'           // автопоиск общего шаблона вывода.
		. PATH_SEPARATOR . ROOT_PATH . 'utils/'             // общие утилиты всего сайта.

// фигу: . PATH_SEPARATOR . get_include_path()
);

// ************************ Настройка вида вывода и его накопителей: ************************ //
//
$layout = 'layout.phtml';
$title = $H1 = $mainContent = $debugContent = $keywords = $description = '';

// типа bootstrap :)
$cssContent =[0 => ['file' => 'public/css/fvn.css', 'params'=>'']];
//или $cssContent =[0 => ['link' => '/public/css/fvn.css', 'attribs'=>'']];

//$jsContent = [0 => ['file' => '/public/scripts/fvn.js', 'params'=>[]]];
$jsContent = [0 => ['link' => '/public/js/jquery-1.9.1.js', 'attribs'=>'type="text/javascript"']];
$cookies = [];

// общий paginator страниц @see views/pages.*:
if( !isset($params['page']) )   { $params['page'] = 1; }
if( !isset($params['onpage']) ) { $params['onpage'] = SITE_ONPAGE; }
$pages = array(
  'page'      => $params['page']
, 'onpage'    => $params['onpage']
, 'maxRows'   => 0
, 'onpages'   => $siteOnpages
, 'halfPages' => 5
);

// ************************ Подключение к БД: ************************ //
// значения по умолчанию
//
define('MySQL_HOST', 'localhost');
define('MySQL_PORT', '3306');
define('MySQL_USER', 'root');
define('MySQL_PASSWORD', '');

// подключение к БД cthulhu (192.168.0.6)
$cthulhu = [
  'host'   => '',
  'port'   => '',
  'user'   => '',
  'pass'   => '',
  'dbname' => '',
  'debugger' => new Debugging(['debLevel'=>Debugging::DEB_ALL, 'debContent'=>&$debugContent]),
  'profiler' => new Profiling(['isCallUnique'=>false, 'profiler'=>&$profiler])
//  'profiler' => new Profiling()
];

// ************************ Роутинг всех мастей: ************************ //
/**
 * Таблица специальных URI и шаблонов запросов БЕЗ диспетчеризации, буферизации и т.п. (возможно всё своё: ZF, Yii, etc.)
 * @see /index.php
 * @TODO: расширить на regepx шаблонизацию ..
 */
$specURI = [
  'uries' => [                             // полные УРЛ для быстрой отдачи:
    'info' => ROOT_PATH . 'info.php'
//		'/yandex_48978a0aa5c67395.html'  => 'yandex_48978a0aa5c67395.html'
  ],
  'patterns' => [                          // шаблоны УРЛ для быстрой отдачи (ROOT_PATH.before.pattern.ext):
    '/info'   => [
      'before' => '.'
      , 'ext'  => '.php'
    ]
  ]
];

/**
 * Таблица быстрой переадресации ури для $_SERVER['REQUEST_URI']:
 *
 * @var array('uri'=>'file')
 * @see dispatch.php
 * @TODO: добавить regexp шаблонизацию ..
 */
$urlMap = array(
	'/info' => ROOT_PATH . 'info.php'
);

/**
 * Карта известных классов для быстрой загрузки без беготни по путям и каталогам:
 *
 * @var array
 */
$classFilesMap = array(
	'Test'=>'tests/testClass.php'
);
