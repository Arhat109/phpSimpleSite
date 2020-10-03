<?php
//die(var_dump($GLOBALS));
/**
 * Главный исполняемый файл в стиле MVC для сайта arhat.su
 *
 * Особенности:
 *  1. полная буферизация всего вывода через ob_start(),ob_flush()...
 *  2. обработка ошибок через new Exception() в любом месте кода...
 *  3. предподготовка параметров GET,POST,COOKIE в единый массив $params[]
 *  4. автозагрузка классов, наличие карты быстрой загрузки классов
 *  5. защищенная диспетчеризация УРЛ: /модуль/index.php как сокращенный урл /модуль
 *  6. динамическое построение пути автопоиска файлов...
 *  7. возможность многоуровневой самодиспетчеризации - повторный вызов диспетчера из контроллера урл.
 *  8. встроенный вывод отладки в урл в спец. раздел <div #debug></div>
 *  9. дополняемый набор типовых обрамлений страниц: /layouts/*
 * 10. подключаемая работа с БД MySQL через прямой класс, основанный на PDO
 * 11. контейнерная сборка обрамлений html,js,css страниц.
 * 12. реализация редиректов, при необходимости из любого места.
 * 13. возможность создания общих "хелперов" контроллеров в стиле Zend Framework.
 * 14. простой и встроенный пагинатор страниц.
 * 15. простой и удобный класс для работы с сессионными переменными.
 * 16. Наличие массива в /configs/ini.php для быстрого поиска контроллера по ури страницы.
 *
 * Урезанно-расширенная модель MVC:
 *
 * /index.php -> /dispatch.php -> модуль/{index}.{php} {-> /dispatch.php} -> action.php
 * -- диспетчеризация первого уровня по первой части URI -- вызов типового файла (index.php, настраивается) из модуля без каких-либо дополнений.
 * -- Модуль самостоятельно донастраивает всё, что ему необходимо, в т.ч. и может иметь свою диспетчеризацию (ZF, Yii, etc.)
 * -- повторный (рекурсивный) вызов диспетчера из модульного index.php продолжает разбор URI и вызывает action.php модуля.
 * -- далее можно повторить рекурсивно вызов диспетчера ..
 * -- Контроллеров как таковых нет, роль контроллера может исполнять как модульный index.php , так и его action.php ..
 *
 * -- Но! Если задать $inUrl['file'] {=START_FILE} ='', то диспетчер будет искать файлы с таким названием и сначала в каталоге modules
 *    , например: можно затереть имя файла в модульном index.php перед рекурсивным вызовом диспетчера ..
 * -- аналогично можно заменять расширение запускаемого файла в $inUrl['ext'] ..
 *
 * -- первичный разбор пустого URI: диспетчер вызывает /main.php -- отдельный файл в корне!
 * !! Все файлы диспетчер вызывает в глобальном контестке !!
 *
 * Буферизация вывода и оформлений (css,js)
 *
 * -- Общий вид вывода в глобале $layout и если не пуст - отрисовывает этот файл.
 * -- Класс utils\Layouts.php содержит методы добавления списка стилей, скриптов к выводу.
 * -- ключ 'css'|'script' интегрирует содержимое непосредственно в эти теги
 * -- ключ 'link' добавляет дозагрузку указанного файла через тег link
 */
/**
 * Глобалы ядра:
 *
 * -- @see /configs/class_map.php
 * @funcioun myAutoLoad(class) -- функция автозагрузки классов, интерфейсов
 *
 * -- @see /configs/ini.php
 * @global bool   IS_DEBUG       -- режим отладки / productions
 * @global string ROOT_PATH      -- корневой каталог всего приложения
 * @global string ROOT_APP       -- корневой каталог всех модулей
 * @global string START_FILE     -- наименование стартового файла для начала диспетчеризации УРЛ (default: index)
 * @global string START_EXT      -- типовое расширение файлов-контроллеров диспетчера (default: .php)
 * @global string SITE_NAME      -- название сайта: HTTP_HOST | 'localhost' для вызовов из консоли
 * @global string SITE_LANG      -- язык клиенту
 * @global string SITE_COUNTRY   -- страна
 * @global string SITE_CHARS     -- кодировка
 * @global string SITE_TIMEZONE  -- константа временной зоны
 * @global int    SITE_ONPAGE    -- эл-тов в типовом пагинеторе
 * @global array  $siteOnpages   -- простой массив перечисления размеров страниц пагинатора "по сколько можно выводить"
 * @global array  $specURI       -- спец. запросы без диспетчеризации, обрамления, буферизации и т.д. (м.быть Zendframework, Yii, etc. далее)
 * @global array  $urlMap        -- таблица быстрой диспетчеризации и вызова контроллера по ури страницы.
 * @global array  $classFilesMap -- таблица быстрой загрузки классов
 *
 * -- @see /utils/utils.php
 * @funcioun getContent($fname, $p = []) -- Читает и отдает содержимое файла через ob_start() принимает параметры в array $p
 * @funcioun redirect( $url, $code=303 ) -- Редирект на другой урл. Не возвращается обратно в вызвавший контекст!
 * @funcioun hackerAnswer( $message )    -- Вывод посметрного сообщения для хаккеров и ботов разных мастей.
 * @funcioun dmy2ymd( $inDate )          -- Преобразует дату из стандартного "дд-мм-гггг" в обратный порядок.
 * @funcioun ymd2dmy( $inDate )          -- Обратное преобразование из "гггг-мм-дд" в "дд-мм-гггг"
 * @funcioun dmy2number( $inDate )       -- Возвращает дату из формата d.m.Y в виде целого числа Ymd
 * @funcioun formatMoney( $money )       -- Возвращает форматирование в рублях и копейках в виде: 00р.00к.
 *
 * -- @see /utils/defender.php
 * @global array  $badUrlPattern -- список строк, вхождение которых в запрос является признаком попытки взлома
 * @global array  $badIP         -- список IP, которые запрещены для доступа к сайту перманентно
 * @function defenderUri($uri)   -- проверяшка на вхождение шаблона, может вызвать die()!
 *
 * @var array  $profiler  -- данные профилирования ['mark'=>['start'=>[float time, float memory], 'end'=>[time, memory]]]
 *
 * -- глобалы пришедшего запроса request + ref(erer):
 * @var array $inUrl
 * [
 *   'path'=>string,'host'=>string,'module'=>string,'file'=>string,'ext'=>string, 'paths'=>[]
 *   ,'ref'=>array['scheme','host','path','query','paths'=>[],'params'=>[]]
 * ]
 * @var array  $params -- [name=>value] единый список параметров GET,POST для удобства обработки в контроллерах (замена для $_REQUEST).
 * @var string $sessId
 *
 * -- глобалы подготовки ответа responce:
 * @var string $layout
 * @var string $mainContent
 * @var string $cssContent
 * @var string $jsContent
 * @var string $debugContent
 * @var string $title
 * @var string $H1
 * @var string $keywords
 * @var string $description
 * @var array  $cookies       -- [int=>array['name','value','expired','path','domain','secure','httponly']]
 * @var array  $pages         -- ['page'=>int,'onpage'=>int,'maxRows'=>int,'halfPage'=>int,'onpages'=>array]
 *
 * -- Диспетчер:
 * @var bool   $dispatchAutoPaths    -- автодобавление пути к файлу ответа модуля диспетчером для автопоиска файлов(классов, видов..)
 * @var int    $dispatchCalls        -- уровень рекурсии в диспетчере (допустима до диспетчеризация внутри модуля!)
 * -- @see dispatch.php:
 * @global string $dispatchIndex     -- текстовка для профайлера и пр. применений в зависимости от уровня рекурсии
 * @global string $dispatchFile      -- полное имя обнаруженного файла быстрой диспетчеризации
 * @global string $dispatchOldPathes -- старый include_path до диспетчера
 * @global string $action            -- действие, найденное диспетчером
 * @function dispatchRender($file)   -- исполнение скрипта с проверкой его наличия и разрешений.. @throwable
 *
 * -- База данных: @see /configs/ini.php, /utils/MySql.php - optional!
 * @global string MySQL_HOST     -- host,port,user,password для MySQL PDO
 * @global string MySQL_PORT
 * @global string MySQL_USER
 * @global string MySQL_PASSWORD
 */
/**
 * Cтруктура каталогов:
 *
 * @see /layouts -- каталог с типовыми оформлениями страниц...
 * @see /modules -- каталог с отдельными модулями сайта. Возможна иерархия и внутренняя до-диспетчеризация.
 * @see /configs -- каталог с общими настройками всего сайта.
 * @see /utils   -- каталог с общими классами и утилитами сайта.
 * @see /public  -- каталог с файлами, отдаваемыми напрямую минуя .htaccess и этот файл!
 *
 * @author fvn-20140207..started,
 *         fvn-20160916 -- доработка отладочного и профилирующего контента на базе модуля grabber3, + namespaces
 *         fvn-20190409 -- доработка, документирование для создания пачки сайтов-визиток..
 */

$profiler = ['global'=>['start'=>[microtime(true), memory_get_usage(true)], 'end'=>[0.0, 0.0]]];

include 'configs/class_map.php'; // автозагрузчик классов
include 'configs/ini.php';       // глобальные настройки, роутинг
include 'utils/utils.php';       // типовые глобальные функции

// Разбираем входной запрос на запчасти и дополняем где и что надо искать диспетчеру:
$inUrl = parse_url($_SERVER['REQUEST_URI']);
if( empty($inUrl['path']) ){
  // В запросе нет даже корня! Судя по логам запрос типа CONNECT для проксей... но судя по PHP-WARNING сюда приходим!
  if( $_SERVER['REQUEST_METHOD'] == 'CONNECT' )	hackerAnswer('Method is not allowed. Server is died.');
  //
  sleep(rand(5,10));
  header($_SERVER["SERVER_PROTOCOL"]." 403 FORBIDDEN");
  die('uri not founed...died.');
}
include 'utils/defender.php'; // предпроверки запроса на предмет допустимости ответа:

// ************ Спец. файлы отдаются без подготовок и диспетчеризаций ************* //
// страницы верификаций, тестовые примеры, самостоятельные модули и решения со своим обрамлением (ZF,Yii,..)
//
foreach( $specURI['uries'] as $sp=>$uri )
	if( $_SERVER['REQUEST_URI'] == $sp ) {
		include $uri;
		return;
}
foreach( $specURI['patterns'] as $p=>$data)
	if( false !== strpos($_SERVER['REQUEST_URI'], $p) ) {
		include ROOT_PATH . $data['before'].$inUrl['path'].$data['ext'];
		return;
}

// ************ остальные запросы буферизуем, парсим и т.д. ************* //
ob_start();
try {
  session_start();
  $sessId = session_id();

  // продолжение разбора входящего запроса:
  $inUrl['host']   = $_SERVER['HTTP_HOST'];
  $inUrl['module'] = ROOT_APP;
  $inUrl['file']   = START_FILE;
  $inUrl['ext']    = START_EXT;

  // Все источники параметров запроса в одном месте: на будущее, если понадобится отстраиваться от типовых глобалов PHP
  $params = $_REQUEST;
  $params = array_merge($params, $_COOKIE);

  if( isset($_SERVER['HTTP_REFERER']) )
  {
      // и если пришли с referer, то сразу разбираем и его на запчасти:
      $inUrl['ref'] = parse_url($_SERVER['HTTP_REFERER']);
      if( !empty($inUrl['ref']['path']) )		$inUrl['ref']['paths'] = explode('/', $inUrl['ref']['path']);
      if( !empty($inUrl['ref']['query']) )	parse_str($inUrl['ref']['query'], $inUrl['ref']['params']);
  }

  // --------------------------------------------------------------------------------------------------
  // И только теперь диспетчер:
  // --------------------------------------------------------------------------------------------------
  /**
   * @var bool $dispatchAutoPaths -- добавлять пути модуля в автопоиск файлов и классов?
   * @var int  $dispatchCalls     -- уровень рекурсии при пере диспетчеризациях урл.
   */
  // удаляем все пустышки из массива путей
  $inUrl['paths'] = explode('/', $inUrl['path']);
  if( !empty($inUrl['path']) )
    for($i=count($inUrl['paths'])-1; $i>=0; $i-- )
      if( empty($inUrl['paths'][$i]) )
        unset($inUrl['paths'][$i])
  ;
  $dispatchAutoPaths = true;      // и добавить путь модуля в пути поиска файлов
  $dispatchCalls = 0;             // глобал идентификации рекурсии в диспетчере
  require 'dispatch.php';
}
catch(Exception $e)
{
	// обработка ошибок: меняем вид вывода на сообщение об ошибке (отдаем 200 OK!):
	// @TODO можно переделать на возврат 404 ... но пока незачем.
	$layout = 'debug.phtml';
	$debugContent = '<br/><code style="white-space:pre; font-size: 14px; color: red; font-weight: bold; ">'
		. $e->getMessage() . '</code><br/>' . $debugContent;
}

// --------------------------------------------------------------------------------------------------
// Если вид ещё не отключен - выводим его:
// --------------------------------------------------------------------------------------------------
if( !empty($layout) ) require_once $layout;

ob_end_flush();
