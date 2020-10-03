<?php
namespace utils;

/**
 * Общий набор утилит (только статические методы!) реализации
 * преобразований результатов выборок в массивы для выдачи html-данных
 * для различного рода хелперов (Zend_View_Select например)
 *
 * @author fvn-20120306 (сборка из разных мест)
 */
class Arrayutils {
  
  /**
   * Разворачивает массив значений в строку для вставки в запрос в поле VALUES
   * закрывает в значениях спецсимволы слешами.
   *
   * @param array $vals == простой массив значений
   * @param string $delimiter == разделитель значений
   *
   * @return string
   *
   * @author fvn-20120322
   * @see dbs/statistics/Daily.php->insert()
   */
  static public function implodeSlashes($vals, $delimiter = ',')
  {
    $result = ''; $count=0;
    foreach($vals as $value) {
      if( $count++ > 0 ) $result .= $delimiter;
      $result .= '"'.addslashes($value).'"';
    }
    return $result;
  }
  
  static $specChars = array('&#002;', '&#003;', '&#006', '&#014;', '&#016;', '&#017;', '&#018;', '&#019;', '&#020;', '&#021;', '&#022');
  /**
   * Изменение изображения сайта - защита от копирования текстов.
   * Вставляет в текст ссылки специальные символы из массива допустимых случайным образом:
   *
   * @param  string $site
   * @return string
   *
   * @author fvn-20130611
   * @see /firms/views/scripts/block/sites_list.phtml
   */
  static public function randUrlImage($site)
  {
    $pos = rand(1, strlen($site)-1);
    $max = rand(3, count(self::$specChars)-1);	// обеспечивает первые три символа - постоянно и чем дальше к концу - тем реже!
    $sym = self::$specChars[rand(0, $max)];
    return substr($site, 0, $pos) . $sym . substr($site, $pos);
  }
  /**
   * Разделяет url на две случайные части, возвращает массив из них
   *
   * @param string $url
   * @param int    $start -- начальная позиция раздела (= 3)
   *
   * @return array(string, string)
   *
   * @author fvn-20130611
   */
  static public function randSplitUrl( $url, $start = 3 )
  {
    $part1 = mb_substr($url, 0, ($pos=rand($start, ($len=mb_strlen($url, 'UTF-8'))-1)), 'UTF-8');
    $part2 = mb_substr($url, $pos, $len, 'UTF-8');
    return array($part1, $part2);
  }
  
  /**
   * Из массива типа Rowset делает простой массив по заданному индексу.
   *
   * Может добавлять в массив значение с индексом 'empty'
   * Параметр field может описывать какие поля собирать в строку для показа:
   * $field = ['f1'=>['pre1','post1'], 'f2'=>['pre2','post2'],..] даст опцию:
   *		$RowsertArray['keyfield'] => pre1 . $RowsetArray['f1'] . post1 . pre2 . $RowsetArray['f2'] . post2
   *
   * @param array        $rowsetArray -- [number => ['field' => 'value']] выборка из БД
   * @param string|array $field		-- какое поле Rowset или какие field из $rowsetArray собрать в строку значений
   * @param string       $keyField	-- если задано, то $field - поле значений, а $keyField - поле ключей; иначе ключ - порядковый номер элемента.
   * @param array        $emptyName	-- array('emptyID' => 'emptyName') Для вставки "пустого"(пустых!) значения(й) первым элементом.
   *
   * @return array -- ('key' => 'value')
   *
   * @author fvn-20110217..fvn-20120306
   * , fvn-20121031: добавлен разбор элемента $rowsetArray ($row!) как объекта.
   * , fvn-20160624: переименование, чистка описания функции.
   * , fvn-20170713: добавлено $field===null -- перенос всей строки по заданному ключу (переиндексация по полю или составному строковому ключу)
   */
  static function &toKeyVals( $rowsetArray, $field = 'id', $keyField = null, $emptyName = array() )
  {
    $ret = array();
    if ( !empty($emptyName) ) {
      $ret = $emptyName;
    }
    if ( !$field || !is_array($rowsetArray) || empty($rowsetArray) ) return $ret;
    if ( $keyField === null ) {
      // not key field! use Rowset index...
      if( is_array($field) ) {
        // value will be a complex from more then one fields:
        foreach ($rowsetArray as $key => $row) {
          $ret[$key] = '';
          foreach( $field as $name=>$adding )
            $ret[$key] .= $adding[0] . (!is_object($row)? $row[$name] : $row->$name) . $adding[1];
        }
      } else {
        // value is a simple field:
        foreach ($rowsetArray as $key => $row)
          $ret[$key] = (!is_object($row)? $row[$field] : $row->$field);
      }
    } else {
      // keyfield is present:
      if( is_array($field) ) {
        foreach ($rowsetArray as $row) {
          // 20170512: keyfield makes from rowsetfields (such as fields too)
          if( is_array($keyField) ){
            $key = '';
            foreach($keyField as $name=>$adding){
              $key .= $adding[0] . (!is_object($row)? $row[$name] : $row->$name) . $adding[1];
            }
          }else{ $key = (!is_object($row)? $row[$keyField] : $row->$keyField); }
          
          $ret[$key] = '';
          foreach( $field as $name=>$adding )
            $ret[$key] .= $adding[0] . (!is_object($row)? $row[$name] : $row->$name) . $adding[1];
        }
      } else if( null !== $field ){
        foreach ($rowsetArray as $row) {
          // 20170512: keyfield makes from rowsetfields (such as fields too)
          if (is_array($keyField)) {
            $key = '';
            foreach ($keyField as $name => $adding) {
              $key .= $adding[0] . (!is_object($row) ? $row[$name] : $row->$name) . $adding[1];
            }
          }else{ $key = (!is_object($row)? $row[$keyField] : $row->$keyField); }
          
          $ret[$key] = (!is_object($row) ? $row[$field] : $row->$field);
        }
      } else {
        // 20170713: added reindexing array for $keyfield data
        if( !is_array($keyField) ){ $keyField = [$keyField]; }
        foreach ($rowsetArray as $row) {
          if (is_array($keyField)) {
            $key = '';
            foreach ($keyField as $name => $adding) {
              $key .= $adding[0] . (!is_object($row) ? $row[$name] : $row->$name) . $adding[1];
            }
          }else{ $key = (!is_object($row)? $row[$keyField] : $row->$keyField); }
          
          $ret[$key] = $row;
        }
      }
    }
    return $ret;
  }
  // старые названия этой же функции для совместимости:
  static public function &toOptionsArray( $rowsetArray, $field = 'id', $keyField = null, $emptyName = array() ){
    return self::toKeyVals($rowsetArray, $field, $keyField, $emptyName);
  }
  static public function &toArray( $rowsetArray, $field = 'id', $keyField = null, $emptyName = array() ){
    return self::toKeyVals($rowsetArray, $field, $keyField, $emptyName);
  }
  
  /**
   * Рекурсивное перестроение Rowset списка объектов в дерево параметров по полю $key с поиском поддеревьвев в поле $subTree
   *
   * @param array  $rowset
   * @param string $key     -- поле ключа индексации дерева
   * @param string $subTree -- имя поля, содержащее поддерево
   *
   * @return array
   */
  public static function makeTree($rowset, $key='id', $subTree = 'childs')
  {
    $tree = [];
    foreach($rowset as $num=>$row){
      $tree[$row[$key]] = $rowset[$num];
      if( !empty($row[$subTree]) ){
        $tree[$row[$key]][$subTree] = self::makeTree($row[$subTree], $key, $subTree);
      }
    }
    return $tree;
  }
  
  /**
   * Удаляет из массива все ключи, отсутствующие как заданное поле второго массива
   * Если результат ещё пуст, то тупо заливает в него данные из поля Rowset источника..
   *
   * @param array  $zids -- исходный массив из которого выносим лишнее
   * @param array  $rows -- Rowset массив, поле которого является ключом для проверки
   * @param string $field
   */
  static public function mergeAnd(array &$zids, array $rows, $field)
  {
    if( count($zids)==0){
      foreach ($rows as $t) { $zids[$t[$field]] = 1; }
    }else{
      foreach($rows as $t)
        if( !empty($zids[$t[$field]]) ) $zids[$t[$field]] = 0;
      
      foreach( $zids as $n => &$v )
        if( $v != 0 )
          unset($zids[$n]);
    }
  }
  
  /**
   * из массива типа Rowset делает массив для поколоночного вывода в заданное число колонок
   * 1. Исходный массив должен быть предсортирован в требуемом порядке.
   * 2. Группировка элементов по полю parent (вложенные элементы втягиваются к родительскому)
   * , поэтому первая колонка - самая длинная.
   * 3. Нумерация колонок с 0 до $columns-1
   *
   * @param array $src     -- сортированная выборка из БД
   * @param int   $columns -- на сколько подмассивов разбивать исходный набор
   *
   * @return array(cols)
   *
   * @author fvn-20120516
   * @see goods/IndexController->indexAction()
   */
  static public function makeColumns(array $src, $columns)
  {
    $allCount = count($src);
    $part = (int)($allCount/$columns);
    $res = array(); $offset = 0;
    
    for($i=0; $i<$columns; $i++) {
      for($j = $offset; $j<$allCount; $j++) {
        if( $j < $part * ($i+1) || $src[$j]['parent'] !== null ) {
          $res[$i][] = $src[$j];
        } else {
          $offset = $j;
          break;
        }
      }
    }
    return $res;
  }
  /**
   * Возвращает заданное количество случайных элементов из простого массива
   * основное назначение выборка товаров для title, description, keywords
   *
   * @param array $list
   * @param int   $maxCount
   * @return array
   *
   * @author fvn-20120815
   * @see goods/IndexController->listAction()
   */
  static public function getRandItems($list, $maxCount)
  {
    $keys = array_keys($list);
    $res = array();
    if( ($cntList=count($list)) <= $maxCount ) return $list; // возвращаем всё что есть!
    $i=0; $cntList--;
    while( $i < $maxCount ) {
      $cur=rand(0, $cntList);
      if( !empty($keys[$cur]) ) {
        $res[] = $list[$keys[$cur]];
        $keys[$cur] = null;
        $i++;
      }
    }
    return $res;
  }
  
  /**
   * Конвертер группированных строк массивов из SQL (после group_concat) в массив
   *
   * @param string $srcRow -- группированная строка выборки данных
   * @param array $cfg
   *   [
   *     'strDelimiter' -- разделитель строк массива данных
   *     'oneDelimiter' -- разделитель значений в строке массива
   *     'keyNum'       -- номер поля, которое будет ключом записи. НЕ задано - все подряд! массив - конкатенация из
   *     полей
   *     'dupIsNum'     -- ключевое поле: true=число; false=строка; нет - накладываем дубликаты ключа друг на друга
   *     'dupAdd'       -- дубликат ключа инкрементируется на эту величину или это добавляется в конец ключа
   *     'fields'       -- список имен полей в порядке группировки полей в строке (optional)
   *   ]
   * @param array  $res -- OUT куда складывать равернутые значения (+ дополнение к содержимому)
   */
  static public function makeFromString( $srcRow, array $cfg, array &$res )
  {
    if( empty($srcRow) ) return;
    
    if (isset($cfg['keyNum'])) {
      $key = (is_array($cfg['keyNum'])? true : $cfg['keyNum'] );
    } else {
      $key = (false);
    }
    
    $docs = explode($cfg['strDelimiter'], $srcRow);
    foreach( $docs as $doc )
    {
      $ddoc = explode($cfg['oneDelimiter'], $doc);
      if( isset($cfg['fields']) )
      {
        $to = array();
        foreach ($cfg['fields'] AS $num => $name)
          $to[$name] = $ddoc[$num];
        
        if ($key)
        {
          if( $key === true )
          {
            $endKey = '';
            // составной ключ (список номеров полей):
            foreach( $cfg['keyNum'] as $fnum ) { $endKey .= ($endKey==''? '' : $cfg['dupAdd']).$ddoc[$fnum]; }
          } else {
            $endKey = $ddoc[$key];
          }
          if( isset($cfg['dupIsNum']) )
          {
            // Надо разводить дубликаты ключа: пока есть повтор изменяем ключ как сказано:
            while (isset($res[$endKey]))
              $endKey = ($cfg['dupIsNum']? (int)$endKey + $cfg['dupAdd'] : $endKey . $cfg['dupAdd']);
          }
          $res[$endKey] = $to;
        }
        else        $res[] = $to;

      } else $res[] = $ddoc;
    }
  }
  // старое название функции:
  static public function convertRows2Array( $srcRow, array $cfg, array &$res ){ self::makeFromString( $srcRow, $cfg, $res ); }
}
