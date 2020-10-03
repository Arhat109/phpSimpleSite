<?php
/**
 * Правила автозагрузки классов: если класс прописан в карте классов сайта - грузим быстро
 * , иначе ищем по путям файл с классом от каталога приложения!
 *
 * @param string $class
 * @return bool
 * @throws \Exception
 */
function myAutoload($class)
{
	global $classFilesMap;

	if (class_exists($class, false) || interface_exists($class, false)) {
		return true;
	}
	if( isset($classFilesMap[$class]) ) {
		// подгружаем класс из карты расположения классов:
		include_once ROOT_PATH . $classFilesMap[$class];
		return true;
	}
	$path = get_include_path();
	$paths = explode(PATH_SEPARATOR, $path);
    $class = str_replace('\\', '/', $class);     // fvn-20160921: namespaces use \ !
	foreach($paths as $p)
	{
		$fname = $p . $class . '.php';
//echo "\n<br/>fname={$fname}";
		if( is_readable($fname) ) { include_once $fname; return true; }
	}
//	return false;
	throw new Exception("\nERROR load class {$class} in paths:".print_r($paths, true), 500);
}

spl_autoload_register('myAutoload');
