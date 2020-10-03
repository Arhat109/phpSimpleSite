<?php

namespace utils;

/**
 * Класс общих утилит для обработки глобалов накопленного ответа сайта
 *
 * @author fvn-20160628
 */
class Layouts
{
  /**
   * Вывод данных о профилировании ответа, если они есть среди глобальных переменных сайта
   *
   * @return string
   */
  static public function outProfiler()
  {
    global $profiler;
    $content = '';

    if( !empty($profiler) ){
      $content = "<!-- Профилирование урл: -->\n<div id=\"profiler\" style=\"display:none;\">";

      foreach( $profiler as $key=>$times ){
        if( isset($times['start']) && isset($times['end']) ){
          $content .= "<p>\n{$key}:: start={$times['start'][0]}, end={$times['end'][0]}, time="
                    . (round(1000 * ($times['end'][0] - $times['start'][0]), 3)) . "msec.";
          $mem = $times['end'][1] - $times['start'][1];
          $content .= ", memory add={$mem} bytes. ({$times['start'][1]})";
          $content .= "\n</p>";
        }else{
          $content .= "<p>\n{$key}:: ";
          foreach( $times as $prop=>$val ){
            if( $prop == Profiling::PROF_TIME ){  $content .= $prop.'='.(round(1000 * $val, 3)).'msec.'; }
            if(
                   $prop == Profiling::PROF_MEMORY
                || $prop == Profiling::PROF_COUNT
            ){
                $content .= ", {$prop}={$val}";
            }
          }
          $content .= "\n</p>";
        }
      }
      $content .= "\n</div>";
    }
    return $content;
  }
  /**
   * Вывод всех CSS ответа, если они есть среди глобальных переменных сайта
   *
   * Структура контейнера для css:
   * 'style' => string -- прямой перечень правил тут
   * 'file'  => fpath  -- развернуть прямым перечнем файл с таким именем от ROOT_PATH + 'params' передать в него параметры..
   * 'link'  => uri    -- добавить css тегом link + 'attribs' атрибутами..
   *
   * @return string
   */
  static public function outCSS()
  {
    global $cssContent;

    $content = '';
    if( !empty($cssContent) && is_array($cssContent) ){
      foreach( $cssContent as $item ){
        $attribs = !empty($item['attribs'])? ' ' . $item['attribs'] : '';
  
        if( !empty($item['link']) ){
          $content .= PHP_EOL . '<link rel="stylesheet" href="' . $item['link'] . '"' . $attribs . '>';
        }else{
          $data = '';
          if( !empty($item['style']) ){
            $data = $item['style'];
          }
          if( !empty($item['file']) ){
            $data = getContent(realpath(ROOT_PATH . $item['file']), $item['params']);
          }
          $content .= "\n<style{$attribs}>\n{$data}\n</style>";
        }
      }
    }
    return $content;
  }

  /**
   * Вывод всех JS ответа, если они есть среди глобальных переменных сайта
   *
   * Структура контейнера для js:
   * 'script' => string -- прямой перечень js строкой
   * 'file'   => fpath  -- развернуть прямым перечнем файл с таким именем от ROOT_PATH + 'params' передать в него параметры..
   * 'link'   => uri    -- добавить загрузкой + 'attribs' атрибутами..
   *
   * @return string
   */
  static public function outJS()
  {
    global $jsContent;

    $content = '';
    if( !empty($jsContent) && is_array($jsContent) ){
      foreach( $jsContent as $item ){
        $attribs = empty($item['attribs'])? '' : ' ' . $item['attribs'];

        if( !empty($item['link']) ){
          $content .= "\n<script src=\"{$item['link']}\"{$attribs}></script>";
        }else{
          $data = '';
          if( !empty($item['script']) ){
            $data = $item['script'];
          }
          if( !empty($item['file']) ){
            $data = getContent(realpath(ROOT_PATH . $item['file']), empty($item['params'])? [] : $item['params']);
          }
          $content .= "\n<script type='text/javascript'>{$data}</script>";
        }
      }
    }
    return $content;
  }
}
