<?php
/**
 * Предпроверки попыток взлома, кривых юзверей и прочей ерунды.
 *
 * Сделать:
 * 1. Список кривых шаблонов строки запроса /cgi-bin и т.д.
 * 2. Проверка строки на наличие шаблонов и, если найдено - авто-сохранение IP автора
 *    в таблице блокировок IP.
 * 3. Проверка IP по таблице блокировок. И если IP блокирован - отдавать кучу разной ерунды.
 *
 * @author fvn-20141202 started.
 */

$badUrlPattern = array('myadmin', 'muieblackcat', 'php', 'cgi','%63%67%69', '/pma', 'content-type'
	, 'http://', 'connect', 'horde', '.bs', '.cgi', '.asmx', 'components', 'hnap1','blackhats'
	, '=()'
);

$badIP = array('88.255.215.100', '116.10.189.5', '125.64.35.67', '95.70.241.166');

foreach( $badIP as $p )
	if( $p == $_SERVER['REMOTE_ADDR'] ) {
		// not returned -- die()!
		hackerAnswer('This IP are permanently blocked.');
}

/**
 * Проверка URI на список подозрительных вхождений
 *
 * @param string $uri
 * @TODO: заменить на regexp проверку!
 */
function defenderUri($uri)
{
  global $badUrlPattern;

  foreach( $badUrlPattern as $p )
    if( false !== strpos($uri, $p) ) {
      // not returned -- die()!
      hackerAnswer('Hacker are founded, server was died, this IP will be blocked. uri='.$uri.', black pattern: '.$p);
    }
}

defenderUri(strtolower($_SERVER['REQUEST_URI']));
