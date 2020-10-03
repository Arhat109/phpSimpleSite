<?php
// ************ Примитивы общего назначения, подключаются сразу @see /index.php ************ //

/**
 * Читает и отдает содержимое файла через ob_start() принимает параметры в array $p
 *
 * @param $fname
 * @param $p -- набор значений передаваемых в файл как параметры.
 *
 * @return string
 *
 * @author fvn-20140207
 *   , fvn-20160630: дополнен глобалами, поскольку они могут дополняться непосредственно в теле скрипта вида..
 */
function getContent($fname, $p = array())
{
  global $cssContent, $jsContent, $debugContent, $mainContent, $inUrl, $profiler;

  ob_start();
    foreach($p as $key=>$val){
      ${$key} = $val;
    }
    require_once $fname;
    $content = ob_get_contents();
  ob_end_clean();
  return $content;
}

/**
 * Редирект на другой урл. Не возвращается обратно в вызвавший контекст!
 * сбрасывает весь контент и отправляет только заголовок для передаресации в браузер.
 *
 * @param string $url  -- урл куда делаем редирект. Если надо с GET параметрами.
 * @param int    $code -- код редиректа, если надо. 303 - переадресация после обработки формы.
 *
 */
function redirect( $url, $code=303 )
{
	ob_flush();
	header('Location: '.$url, true, $code);
	die();
}

/**
 * Вывод посметрного сообщения для хаккеров и ботов разных мастей.
 *
 * @param string $message
 */
function hackerAnswer( $message )
{
	// не зачем засорять траффик!
	sleep(rand(5,10));
	header($_SERVER["SERVER_PROTOCOL"]." 403 FORBIDDEN");
	die($message);
}

// ========================== Простые общие функции ======================================= //
/**
 * Преобразует дату из стандартного "дд-мм-гггг" в обратный порядок.
 *
 * @param $inDate
 * @return string
 */
function dmy2ymd( $inDate ) {
    return preg_replace('/(\d{2}).(\d{2}).(\d{4})/i', "\${3}-\$2-\$1", $inDate);
}

/**
 * Обратное преобразование из "гггг-мм-дд" в "дд-мм-гггг"
 *
 * @param string $inDate
 * @return string
 */
function ymd2dmy( $inDate ) {
    return preg_replace('/(\d{4}).(\d{2}).(\d{2})/i', "\${3}-\$2-\$1", $inDate);
}

/**
 * Возвращает дату из формата d.m.Y в виде целого числа Ymd
 *
 * @param $inDate
 *
 * @return int
 */
function dmy2number( $inDate ) {
    return (int)preg_replace('/(\d{2}).(\d{2}).(\d{4})/i', "\${3}\$2\$1", $inDate);
}

/**
 * Возвращает форматирование в рублях и копейках в виде: 00р.00к.
 *
 * @param float $money
 * @return string
 *
 * @author fvn-20150512
 */
function formatMoney( $money )
{
    return money_format('%!#0.2n', floatval($money)).'р.';
}
