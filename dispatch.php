<?php
/**
 * Диспетчер сайта arhat.su
 * @TODO: добавить правила regexp() для проверки/перекодировки урл по шаблонам!
 *
 * Проверяет переадресацию по таблице, если надо делает редирект с правильным УРЛ
 * и вызывает index.php требуемого модуля, буферизуя вывод. Если переадресации нет, то скрипт вызывается из
 * предположения структуры каталогов в урл:
 *
 * /modules/submodule/../index.php
 *
 * arhat.su/games?           -- будет вызван /modules/games/index.php
 * arhat.su/grabber/mainMenu -- будет вызван /modules/grabber/index.php с дополнением "mainMenu"
 *
 * То есть диспетчер разруливает только первую часть путей, вызывая для каждого модуля его index.php
 * , лежащий в корне модуля и, передавая ему остаток разобранного урл для дальнейшей диспетчеризации.
 * Фактически, каждый модуль может реализовать собственную диспетчеризацию всех своих ури.
 * и при этом модули могут иметь произвольно вложенную структуру.
 *
 * @global array $inUrl -- разобранный uri и дополненный параметрами для диспетчеризации:
 *             'module' -- базовый каталог откуда надо начинать поиск файла
 *             'file'   -- если задано, то типовое название файла который надо найти в модуле
 *             'ext'    -- типовое расширение искомого файла.
 * @global bool  $dispatchAutoPaths -- добавлять путь до модуля в список set_include_path()?
 * @global int   $dispatchCalls     -- уровень рекурсии диспетчеризации
 * @global array $urlMap            -- таблица быстрой диспетчеризации @see /configs/ini.php
 *
 * @var string $dispatchIndex     -- текстовка для профайлера и пр. применений в зависимости от уровня рекурсии
 * @var string $dispatchFile      -- полное имя обнаруженного файла быстрой диспетчеризации
 * @var string $dispatchOldPathes -- старый include_path до диспетчера
 * @var string $action            -- действие, найденное диспетчером
 *
 * @throwable -- выбрасывает исключения при отсутствии файла(500) или не найденной диспетчеризации (404)
 *
 * @author fvn-20140207,20141026..
 *
 * @example рекурсивный перезапуск диспетчера: require 'dispatch.php';
 */

// ************************** Диспетчер ************************* //
//
$dispatchIndex = 'dispatch '.$dispatchCalls;

if( IS_DEBUG ){
    $debugContent .= '<div id="debugDispatcher"><p>dispatch.php started:</p>';
    $profiler[$dispatchIndex] = ['start'=>[microtime(true), memory_get_usage(true)], 'end'=>[0.0, 0.0]];
}

if( isset($urlMap[$inUrl['path']]) )
{
  // быстрая диспетчеризация по карте ссылок:

  $dispatchFile = $urlMap[$inUrl['path']];
  if( IS_DEBUG ){
    $debugContent .= "\n<p>..url are founded in map, call: {$dispatchFile}</p>";
    $profiler[$dispatchIndex]['end'] = [microtime(true), memory_get_usage(true)];
  }
  // псевдо роутер: Если весь ури есть в карте прямого вызова - исполняем файл оттуда, иначе разбираем ури:
  // !нельзя выносить в функцию напрямую - теряется доступ к глобалам ..
  if( is_file($dispatchFile) && is_readable($dispatchFile) ){
    $dispatchCalls++;
    require_once $dispatchFile;
    $dispatchCalls--;
  }else{
    $message = "\n.. ERROR rendering by {$dispatchFile} - file not found or not readable..";
    if( IS_DEBUG ){$debugContent .= $message;}
    throw new Exception($message, 500);
  }

}else{ // общая диспетчеризация:

  if( IS_DEBUG ) $debugContent .= "\n<p>..search started:";

  if( empty($inUrl['paths']) ) {

    // обращение к главной странице:

    if( IS_DEBUG ){
      $debugContent .= ' empty paths - show main page</p></div>';
      $profiler[$dispatchIndex]['end'] = [microtime(true), memory_get_usage(true)];
    }
    $dispatchFile='main.php';      // а небыло путей - главная всего сайта.
    // !нельзя выносить в функцию напрямую - теряется доступ к глобалам ..
    if( is_file($dispatchFile) && is_readable($dispatchFile) ){
      $dispatchCalls++;
      require_once $dispatchFile;
      $dispatchCalls--;
    }else{
      $message = "\n.. ERROR rendering by {$dispatchFile} - file not found or not readable..";
      throw new Exception($message, 500);
    }

  }else{
    // общая диспетчеризация не пустого URI:

    while( !empty($inUrl['paths']) ){
      // если задано действие, то куски пути добавляем в модуль иначе пробуем найти действие:
      $action = $inUrl['file'];
      if( $inUrl['file'] == '' ) $action = array_shift($inUrl['paths']);
      else                       $inUrl['module'] .= array_shift($inUrl['paths']) . '/';

      $dispatchFile = $inUrl['module'] . $action . '.' . $inUrl['ext'];
      if( IS_DEBUG ){ $debugContent .= "\n<br/>.. module={$inUrl['module']}, action={$action}, file={$dispatchFile}"; }

      if( is_file($dispatchFile) && is_readable($dispatchFile) ){
        if( IS_DEBUG ){ $debugContent .= ' <span class="green">founded!</span> autoPaths = ' . ($dispatchAutoPaths ? 'true' : 'false') . '</p>'; }
        if( $dispatchAutoPaths ){
            // подключаем модуль в пути поиска по умолчанию В НАЧАЛО! Возвращаются старые пути!!!
            $dispatchOldPathes = set_include_path(
                $inUrl['module'] . PATH_SEPARATOR . get_include_path()
            );
            if( IS_DEBUG ){ $debugContent .= "\n<p>add path={$inUrl['module']}<br/>\nold={$dispatchOldPathes}</p>"; }
        }
        if( IS_DEBUG ){
          $debugContent .= '</div>';
          $profiler[$dispatchIndex]['end'] = [microtime(true), memory_get_usage(true)]; // Тут возможен возврат из диспетчера..
        }

        // !нельзя выносить в функцию напрямую - теряется доступ к глобалам ..
        if( is_file($dispatchFile) && is_readable($dispatchFile) ){
          $dispatchCalls++;
          require_once $dispatchFile;
          $dispatchCalls--;
        }else{
          $message = "\n.. ERROR rendering by {$dispatchFile} - file not found or not readable..";
          throw new Exception($message, 500);
        }

        // если не добавляли, то и нечего восстанавливать! Иначе рушится рекурсивный вызов...
        if( $dispatchAutoPaths ){ set_include_path($dispatchOldPathes); }
        return;
      }else{
        if( IS_DEBUG ){ $debugContent .= ' <span class="error"> - absent!</span>'; }
      }
      // пробуем этот кусок как модуль:
      if( $inUrl['file'] == '' ){ $inUrl['module'] .= $action . '/'; }
    }
    if( IS_DEBUG ){
      $debugContent .= '</p></div>';
      $profiler[$dispatchIndex]['end'] = [microtime(true), memory_get_usage(true)];
    }
    // Не найден ури в структуре модулей
    throw new Exception('ERROR! Nothing to dispatch!', 404);
  }
}
