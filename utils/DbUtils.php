<?php

namespace utils;

/**
 * Статические утилиты для работы с запросами и элементами для их построения.
 * дополнения к Zend_Db_Select.
 *
 * @author fvn-2011..2012
 */
class DbUtils
{
	/**
	 * Формирует строку 'field IN (...)' или 'field = ...' или 'field is null' для упрощения выборок
	 * по предварительно выбранным/созданным индексам. Устраняет выбор формы условия.
	 *
	 * @param string $field_name
	 * @param mixed $data	-- null,число,строка значений для IN или простой уже слешованный (если не числа) массив значений - строк.
	 * @param bool	$isNot	-- добавить отрицание: "кроме указанных"
	 * @param bool	$orNull	-- добавить в условие "OR $field_name IS NULL" == "и пустые тоже"
	 *
	 * @return string -- where
	 */
	static function getWhere( $field_name, $data, $isNot = false, $orNull = false )
	{
		$orNull = ($orNull? 'OR ('.$field_name.' IS NULL)': '');

		if( $data === null ) {
			return '( ' . $field_name . ' IS '. ($isNot? 'NOT ' : '') . 'NULL )';
		} elseif( is_array($data) && !empty($data) ) {
			return '( ' . $field_name . ($isNot? ' NOT' : '') . ' IN ("' . implode('","', $data) . '") ' . $orNull . ')';
		} elseif( is_int($data) ) {
			return '(' . $field_name . ($isNot? ' !' : '') . '= "' . (int)$data . '"' . $orNull . ')';
		} elseif( is_string($data) && !empty($data) ) {
			return '(' . $field_name . ($isNot? ' NOT' : '') . ' IN ("' . $data . '") ' . $orNull . ')';
		} else {
			// @todo Здесь можно добавить обработку чего ещё: Rowset, например...
			return '(1=1)';
		}
	}

    /**
     * Возвращает строку запроса со словом LIMIT
     *
     * @param int $page
     * @param int $onpage
     *
     * @return string
     */
    static function getLimit($page, $onpage)
    {
        return ' LIMIT ' . ($page-1)*$onpage . ',' . $onpage;
    }

    /**
     * Возвращает строку запроса для блока WHERE|ON с формированием IN() по ключам заданного массива!
     * Значение у элемента массива - должно быть непустым... иначе - пропуск.
     *
     * @param string $name
     * @param array  $p     -- входной массив данных
     * @param array  $bind  -- дополняет эти бинтованные значения..
     *
     * @return array('where'=>string, 'bind'=>array)
     */
    static public function &sqlWhereIntKeys($name, array $p, array $bind)
    {
        $cntVals = 0;
        foreach( $p as $i=>$data )
            if(!empty($data) ) {
                $bind[] = (int)$i;
                $cntVals++;
            }

        $res = array(
            ($cntVals>0? $name . ' IN(' . str_repeat('?,', $cntVals-1) . '?)' : '(1=1)')
            ,$bind
        );
        return $res;
    }

    /**
     * Формирует условие для WHERE|ON как == или IN() оператор с подготовкой бинтования данных как целых
     *
     * @param string      $field
     * @param {int|array} $p
     * @param array       $bind
     *
     * @return array('where'=>string, 'bind'=>array)
     */
    static public function &sqlWhereIntArray( $field, $p, array $bind=array() )
    {
        if( is_array($p) ) {
            $where = "{$field} IN(".str_repeat('?,', count($p)-1).'?)';
            foreach( $p as $id) { $bind[] = (int)$id; }
        } else {
            $where = "{$field} = ?";
            $bind[] = (int)$p;
        }
        $res = array($where, &$bind );
        return $res;
    }

    /**
     * Возвращает строку для группировки заданного текста в массив значений в json-формате: [val1,val2,..]
     * ВНИМАТЕЛЬНО! То, что передается - и будет элементами массива!
     *
     * @param  string $value -- что превратить в json массив: 'field1' или self::jsonVar() => "name":"value"
     * @param  bool $isObj -- пропускать кавычки у значения (не атомарное значение)?
     * @return string
     */
	static public function jsonArray($value, $isObj = false, $order = '')
	{
		if( !$isObj ) $value = 'CONCAT("\"",REPLACE('.$value.',"\"","\\\\\""),"\"")';
		return 'CONCAT("[", GROUP_CONCAT(DISTINCT '.$value.' '.$order.'),"]")';
	}

    /**
     * Возвращает строку для группировки заданного текста в объект в json-формате: {name1:val1, name2:val2, ...}
     * ВНИМАТЕЛЬНО! То, что передается - и будет элементами объекта!
     *
     * @param  string $pair -- что превратить в json массив: 'field1' или self::jsonVar() => "name":"value"
     * @param  bool $isObj -- пропускать кавычки у значения (не атомарное значение)?
     * @return string
     */
	static public function jsonObject($pair, $isObj = false)
	{
		if( !$isObj ) $pair = 'CONCAT("\"",REPLACE('.$pair.',"\"","\\\\\""),"\"")';
		return 'CONCAT("{", GROUP_CONCAT(DISTINCT '.$pair.'),"}")';
	}
	/**
	 * Возвращает строку для подготовки json-объекта (пару!): '"name":"data"'
	 *
	 * @param  string $name  -- как назвать
	 * @param  string $data  -- что назвать
	 * @param  bool   $isObj -- пропускать кавычки у значения (не атомарное значение)?
	 * @return string
	 */
	static public function jsonPair($name, $data, $isObj = false)
	{
		if( !$isObj ) $data = '\"",REPLACE('.$data.',"\"","\\\\\""),"\""';
		else          $data = '",'.$data;
		return 'CONCAT("\"",' . $name . ',"\":' . $data . ')';
	}
	/**
	 * Возвращает строку для подготовки json-объекта: '{"name":"data"}'
	 *
	 * @param  string $name
	 * @param  string $data
	 * @return string
	 */
	static public function jsonVar($name, $data, $isObj = false)
	{
		if( !$isObj ) $data = '\"",REPLACE('.$data.',"\"","\\\\\""),"\""';
		else          $data = '",'.$data;
		return 'CONCAT("{\"",' . $name.',"\":' . $data . ',"}")';
	}

    /**
     * Получить текстовку $values к запросу и комплект данных для $bind из массива типа Rowset - нумерованный набор записей.
     *
     * Все имена параметров $fields должны быть полями таблицы сохранения!
     * Сохраняются только указанные поля из массива! для сохранения всех полей: $fields -> reset($values[0])
     *
     * @param array  $rows   -- [0 => ['field1'=>data, ...],..]
     * @param array  $fields   -- простой перечень сохраняемых ключей из каждой записи в $values
     *
     * @return array|false -- list($values, $bind, $onUpdate) + текстовка на случай ON DUPLICATE KEY UPDATE ..
     *
     * @author fvn-20160624
     * @see this->saveRowset()
     */
    static public function makeValsBind(array $rows, array $fields)
    {
        if (empty($rows)) { return false; }

        $bind = []; $vals='';
        $rec1 = '('. str_repeat('?,', count($fields)-1) . '?)';
        $onUpdate = '';

        $isFirstRow = true;
        foreach($rows as $row) {
            foreach($fields AS $name) {
                $bind[] = isset($row[$name]) ? $row[$name] : 'null';
                if ($isFirstRow) {
                    $onUpdate .= ($onUpdate === '' ? '' : ',') . "`{$name}` = VALUES(`{$name}`)";
                }
            }
            $vals .= ($vals? ',' : '') . $rec1;

            $isFirstRow = false;
        }
        return [$vals, $bind, $onUpdate];
    }

    /**
     * Разбираем mixed параметр функций table на таблицу и её алиас
     *
     * @param {string|array} $table
     *
     * @return array -- for list($name, $as)
     */
    static public function parseTable( $table )
    {
        if( is_array($table) ) {
            $name = reset($table); // return value from first element and reset internal pointer too.
            $as   = key($table);
        } else {
            // $table is string:
            $ltbl = strtolower($table);
            if( strpos($ltbl, ' as ') !== false ){ list($name, $as) = explode(' as ', $ltbl, 2); }
            else                                         { $name = $as = $table; }
        }
        return [$name, $as];
    }

    /**
     * Возвращает '' или табличку слов в виде запроса "select w1 union all select w2", а также массив слов.
     *
     *   Слова вставляются только значимые в нижнем регистре.
     *   Если список плохих слов не задан совсем - использует собственный.
     *   Если список не нужен - задайте пустой массив.
     *   Если список уже транспонирован - последний параметр поставьте false
     *
     * @param string   $query       -- строка запроса.
     * @param array    $words       -- возвращаемый список значимых слов в виде массива (слово=>длина)
     * @param array[2] $like        -- обрамляющие строки для слов "до и после". Не задано = '%слово%'
     * @param array    $badWords    -- если надо массив простой или транспонированный, если нет === false
     * @param bool     $isTranspose -- если массив уже транспонирован === false
     *
     * @return string -- 'SELECT `word1` AS `word` UNION ALL ..'
     *
     * @author fvn-20140130..
     */
    static public function getWordsSelect(
        $query, &$words = array(), $like = array('%','%'), $badWords = null, $isTranspose = true
    ){
        if( !isset($badWords) )
            $badWords = array(
                'и','или','не','но','на','для','а','у','за','над','под','от','в','с','к','о','об','чем','через'
                ,'из','www','http','com','ru','ua'
            )
            ;
        if( $isTranspose )
            $badWords = array_flip($badWords);

        // убираем всё кроме цифробукв и дури сначала и конца в поисковой строке - создаем массив слов.
        $q = preg_replace('@[^a-zа-я0-9ё ]@su','', trim(mb_strtolower( $query )) );
        $w1 = explode(' ', $q);

        // подзапрос: формируем табличку слов "на лету" в виде: "select .. union all select ...":
        $sql1 = '';
        foreach( $w1 as $k=>$w) {
            if( empty($w) || isset($badWords[$w]) ) { continue; }
            if( !empty($sql1) ) $sql1 .= ' UNION ALL SELECT ';
            $sql1 .= '"'. $like[0] . $w . $like[1] . '" as word ';
            $words[$w] = mb_strlen($w);
        }
        return 'SELECT ' . $sql1;
    }
    /**
     * Возвращает список значений из массивов With и Not параметра от fvnbox блока выборок
     *
     * @param {null|array} $param -- json парсинг строки выборанного через fvnbox @see /fvnbox/readme.php
     *
     * @return array -- список значений в обоих массивах
     *
     * @author fvn-20130628
     * @see /goodsrequest/IndexController->getfirmsAction()
     */
    static public function fvnboxGetKeys( array $param )
    {
      if( empty($param['Not'])  ) return (array)$param['With'];
      if( empty($param['With']) ) return (array)$param['Not'];
      
      return array_merge($param['With'], $param['Not']);
    }
    /**
     * Возвращает условия в блок having и where для формирования запроса с группирующим подзапросом
     * по сложному условию: "{найди где есть такие и/или такие} && {кроме таких или/и таких}"
     * !!! блок для WHERE 'in' - только текстовка IN() без имени поля !!!
     *
     * @param  array  $param -- @see fvnbox.readme
     * @param  string $field -- поле или выражение, значения которого проверяются на условие
     *
     * @return array('in'=>string, 'with'=>string, 'not'=>string)
     *
     * @author fvn-20130702
     *
     * @see dbs/goods/PricesTable->getSql() -- подбор фирм по наличию товаров в прайсах.
     */
    static public function fvnboxHaving( array $param, $field)
    {
        $in = $havingWith = $havingNot = '';
        if( !empty($param['With']) && (int)$param['With'][0] > 0 )
        {
            $in = ' IN('.implode(',', static::fvnboxGetKeys($param)).')';
            // есть список "должны быть"
            if( !empty($param['With_And']) ) {
                $havingWith = 'SUM('.$field.' IN('.implode(',', $param['With']).')) = ' . count($param['With']);
            }
            if( !empty($param['Not']) ) {
                $havingNot = 'BIT_OR('.$field.' IN('.implode(',', $param['Not']).')) = 0';
            }
        } elseif( !empty($param['Not']) && (int)$param['Not'][0] > 0 ) {	// в базу могло попасть Not:[""] -- нулевой элемент - не пуст!
            // есть только список товаров "все, кроме таких":
            $havingNot = 'BIT_OR('.$field.' IN('.implode(',', $param['Not']).')) = 0';
        }
        return array('in'=>$in, 'with'=>$havingWith, 'not'=>$havingNot);
    }
}