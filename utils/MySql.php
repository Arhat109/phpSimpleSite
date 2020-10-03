<?php

namespace utils;

use PDO;
use PDOException;
use PDOStatement;

/**
 * Класс библиотеки доступа к БД MySQL
 * Работаем через PDO адаптер.
 *
 * Примечание: каждый адаптер хранит, если надо последний препарированный запрос
 * , и если надо его можно повторить. Но при этом код, работающий через один адаптер не может иметь
 * асинхронное выполнение! Каждый адаптер - только для последовательного исполнения!
 *
 * Свойства
 * @property string       $host
 * @property string       $port
 * @property string       $user
 * @property string       $password
 * @property array        $pdoAttribs
 * @property PDO          $pdo
 * @property string       $dbName     -- активная СУБД
 * @property PDOStatement $st         -- последний оператор PDO
 * @property int          $fetchMode
 * @property string       $lastError  -- текстовка последней ошибки PDO
 *
 * @property Debugging $debugger -- куда свистеть отладочную информацию
 * @property Profiling $profiler -- куда свистеть профилирование запросов
 *
 * Методы
 *    __construct()  -- подключает к БД с учетом глобалов. Объект и базу может ставить в дефолт.
 *    selectAll()    -- упрощенный select к базе. Принимает запрос, массив данных и отдает массив результата.
 *    saveValues()   -- обобщенная вставка данных оптом в таблицу
 *    update()       -- обощенное обновление в таблице
 *    saveRowset()   -- надстройка над saveValues() для сохранения однотипного массива записей из таблицы
 *
 * @author fvn-20140207..
 *         fvn-20190410 - приведение, улучшение, документирование..
 */
class MySql
{
	public $host       = '';
	public $port       = '';
	public $user       = '';
	public $password   = '';
	public $pdoAttribs = [ PDO::ATTR_AUTOCOMMIT => true, PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true];
	public $debugger   = null;
    public $profiler   = null;

	/** @var PDO -- адаптер PDO объекта */
	public $pdo = null;

	/** @var string -- база, к которой подключен этот объект */
	public $dbName = '';

	/** @var PDOStatement -- Текущий оператор PDO последнего вызова методов отсюда для повтора execute($bind) */
	public $st = null;

	/** @var PDO -- FETCH_CONSTANT Текущий режим выборки данных в методах выборки тут */
	public $fetchMode = PDO::FETCH_ASSOC;

	/** @var string -- последняя ошибка PDO, если есть */
	public $lastError = '';

	/** @params array $params -- Задать параметры соденинения */
	public function setParams($params)
    {
      $this->host     = ( isset($params['host']) ? $params['host'] : MySQL_HOST);
      $this->port     = ( isset($params['port']) ? $params['port'] : MySQL_PORT);
      $this->user     = ( isset($params['user']) ? $params['user'] : MySQL_USER);
      $this->password = ( isset($params['pass']) ? $params['pass'] : MySQL_PASSWORD);
      if( isset($params['attribs']) && is_array($params['attribs']) ){ $this->pdoAttribs = $params['attribs']; }
    }

	/** (пере)Установить соединение с СУБД по текущим параметрам */
    public function reConnect($dbName)
    {
      $dbh = new PDO("mysql:host={$this->host};port={$this->port};dbname={$dbName}", $this->user, $this->password, $this->pdoAttribs);
      if( $dbh ){
        $dbh->exec('set names utf8');
        $dbh->exec('use ' . $dbName);
        $this->pdo = $dbh;
        $this->dbName=$dbName;
      }else{
        $this->debugger->raise("MySql::ERROR! Can't connect for {$dbName}");
      }
    }

	/**
	 * Соединение с БД через PDO адаптер или по заданным данным или из констант в ini.php
     * ! Если не задано, то использует глобальный контент отладчика(Debugging::DEB_FATAL)
	 *
	 * @param array $params -- настроечный массив (СУБД, хост, юзер, пароль, доп. атрибуты, отладочный и профилирующий контекст, если надо)
     * @throws
	 */
	public function __construct($params)
	{
	  global $debugContent;

      $this->debugger = !empty($params['debugger'])? $params['debugger']
        : new Debugging(['debLevel'=>Debugging::DEB_FATAL, 'debContent'=>&$debugContent]);

      if( !empty($params['profiler']) ){ $this->profiler = $params['profiler']; }

      $sqlHash = $this->profiling('MySql::construct()', Profiling::PROF_START);

      $this->setParams($params);
      $this->reConnect($params['dbname']);
      $this->debugger->debug(Debugging::DEB_INFO, 'PDO adapter are created.');

      $this->profiling($sqlHash, Profiling::PROF_END);
	}

    /**
	 * Формирует текст последней ошибки в свойстве ->lastError
	 */
	public function setError()
	{
		$res = $this->st->errorInfo();
		$this->lastError = "PDO_ERROR: {$res[0]}, {$res[1]}: {$res[2]}";
	}

    /**
     * Вывод блока отладки, если включено:
     *
     * @param string $text -- текстовка "функция сообщение"
     * @param string $sql  -- оригинал запроса ('')
     * @param array $bind  -- бинтованный массив ([])
     * @param mixed $res   -- результат запроса (null)
     * @param int   $level -- уровень отладки (DEB_INFO)
     */
    public function printDebug($text, $sql='', array $bind = [], $res = null, $level = Debugging::DEB_INFO)
    {
        if( IS_DEBUG && $this->debugger ) {
            $this->setError();
            $this->debugger->debug($level,"<p>\n" . $text . "\n</p><p>\nstmt="
                . (false===$this->st? 'false' : print_r((array)$this->st, true) ) . "\n</p>"
                . ( !empty($sql) ? "<p>\n{$sql}\n</p>" : '' )
                . ( !empty($bind)? "<pre>\n" . print_r($bind, true) . "\</pre>" : '' )
                . ( isset($res)  ? "<pre>\n" . print_r($res,  true) . "\</pre>" : '' )
                ."<p>\nlast Error: {$this->lastError}\n</p>"
            );
        }
    }

    public function profiling($method, $tag)
    {
        if( empty($this->profiler) ) return '';
        return $this->profiler->profiler($method, $tag);
    }

	/**
	 * Выборка всех данных в ассоциативный массив по запросу к БД
	 *   одиночный запрос: создать, выбрать и закрыть курсор -- есть $sql и $isClose == true
	 *   первый в пачке: создать и выбрать -- есть $sql и $isClose == false
	 *   следующий в пачке: выбрать -- пустой sql и $isClose == false
	 *   последний в пачке: выбрать и закрыть курсор -- пустой sql и $isClose == true.
	 *
	 * @param string $sql     -- строка запроса или продолжить предыдущий ('')
	 * @param array  $bind    -- массив данных, если есть в запросе ? :
	 * @param bool   $isClose -- закрывать курсор после запроса?
	 *
	 * @throws PDOException
	 * @return array
	 */
	public function &selectAll( $sql, $bind=array(), $isClose=true )
	{
        $sqlHash = $this->profiling(__METHOD__, Profiling::PROF_START);

	    $res = [];

		if( $sql != '' ) {
			$this->st = $this->pdo->prepare($sql);
            $this->printDebug(__METHOD__ . ': prepared', $sql, $bind);
		}
	    if( $this->st !== false && ($res=$this->st->execute($bind)) )
	    {
		    $res = $this->st->fetchAll($this->fetchMode);
            $this->printDebug(__METHOD__ . ': executed', '', [], $res);
	    }else{
            $this->printDebug(__METHOD__ . ': not executed', '', [], $res);
	    }
	    if( $isClose && isset($st) && $this->st !== false) { $this->st->closeCursor(); unset($this->st); }

        $this->profiling($sqlHash, Profiling::PROF_END);
	    return $res;
	}

	/**
	 * Возвращает количество строк в предыдущей выборке, если было SQL_CALC_FOUND_ROWS
	 *
	 * @return int|0
	 */
	public function getMaxRows()
	{
        $sqlHash = $this->profiling(__METHOD__, Profiling::PROF_START);

		$stmt = $this->pdo->query('SELECT found_rows() AS maxRows');
		$maxRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->profiling($sqlHash, Profiling::PROF_END);

		return (!empty($maxRows[0]['maxRows'])? $maxRows[0]['maxRows'] : 0);
	}

	/**
	 * Сохранение списка значений VALUES() прямым запросом
	 * , по умолчанию INSERT, но можно заменить и на своё начало.
	 * Если $table=='' то бинтует новые значения и повторяет предыдущий запрос
	 * Если $bind == null, то только prepare() запроса если есть table! array() -- если нет данных!
	 * Если надо, $values можно дополнить выражением [SELECT ...] ON DUPLICATE KEY UPDATE...
	 *
	 * @param  string $table    -- таблица куда вставляем данные (prepare запроса) или пусто (повтор prepared запроса)
	 * @param  string $fields   -- перечень полей списка $values
	 * @param  string $values   -- строка(!) запроса с данными: {'VALUES (f1, f2, ...),(...),..'|'SELECT...'|..}
	 * @param  array  $bind     -- optional. список значений к строке запроса (PDO params with: ? or :named)
	 * @param  bool   $isClose  -- optional. Закрывать курсор после выполнения?
	 * @param  string $addition -- optional. дополнение режима если надо {'REPLACE' | 'INSERT LOW_PRIORITY' | 'INSERT IGNORE' | etc.}
     * @param  string $ondup    -- optional. Если задан INSERT_UPDATE, то чем заменять значения для "ON DUPLICATE KEY UPDATE"
	 *
	 * @throws PDOException
	 * @return bool
	 *
	 * @author fvn-20130313..
     *   , fvn-20160624: добавлен режим INSERT_UPDATE и его параметр.
	 */
	public function saveValues($table, $fields, $values, $bind = null, $isClose = true, $addition = 'INSERT', $ondup = '')
	{
        $sqlHash = $this->profiling(__METHOD__, Profiling::PROF_START);

	    if( $table != '') {
            if( $addition === 'INSERT_UPDATE'){
                $addition = 'INSERT';
                $ondup = ' ON DUPLICATE KEY UPDATE '. $ondup;
            } else {
                $ondup = '';
            }
            $sql = $addition . ' INTO ' . $table . ' (' . $fields . ') ' . $values .' '. $ondup . ';';

            $this->st = $this->pdo->prepare($sql);
            $this->printDebug(__METHOD__ . ': prepared', $sql);
	    }
	    $res = ($this->st !== false);
	    if( isset($bind) ) {
	    	if( $res ) $res = $this->st->execute($bind);
            $this->printDebug(__METHOD__ . ($res? ':' : ': not') . ' executed', '', $bind);
	    }
		if( $isClose && isset($st) && $this->st !== false ) { $this->st->closeCursor(); unset($this->st); }

        $this->profiling($sqlHash, Profiling::PROF_END);
		return $res;
	}

    /**
     * Сохранение группы строк с полями $fields из массива $rows
     *
     * Все имена полей в БД должны совпадать с именами полей массива.
     * Массив может содержать лишние поля для каждой записи. Сохраняются только заданные.
     * Если значение поля не найдено в массиве, проставляется 'null'
     *
     * @param string $table -- название таблички куда вставляем строки
     * @param array $fields -- массив полей, как в табличке и в $rows
     * @param array $rows -- чего вставляем. Все строки должны иметь нужные поля или будет null!
     * @param bool $isClose -- закрывать курсор после вставки?
     * @param string $addition -- 'INSERT {IGNORE}', 'REPLACE {IGNORE}','INSERT_UPDATE'
     * @param string $ondup -- выражение для INSERT_UPDATE или пусто (будут просто обновлены все поля)
     *
     * @return bool|string -- номер записи (пачки)
     *
     * @fvn-20160318 -- добавлен режим INSERT_UPDATE и его параметр.
     */
    public function saveRows($table, array $fields, array $rows, $isClose = true, $addition = 'INSERT', $ondup='')
    {
        $sqlHash = $this->profiling(__METHOD__, Profiling::PROF_START);

        $res = DbUtils::makeValsBind($rows, $fields);
        if( false === $res ) { return false; }

        list($vals, $bind, $onvals) = $res;
        if( $addition == 'INSERT_UPDATE' && !empty($ondup) ) $onvals = $ondup;

        $res = $this->saveValues($table, '`'.implode('`,`', $fields). '`', ' VALUES ' . $vals, $bind, $isClose, $addition, $onvals);

        $this->profiling($sqlHash, Profiling::PROF_END);
        return ($res === false? false : $this->pdo->lastInsertId());
    }

    /**
     * Обновление записей в таблице(ах).
     * Если надо сложное обновление или джойн, то прописываем остаток запроса вместе с SET в $addition
     * или в $table, если достаточен стандартный блок SET
     * Если $bind == null, то только prepare() запроса если есть table!
     * Если есть прочие позиционные параметры - добавляем их в их порядке по номерам!
     *
     * !!! Следить за порядком в массиве и местами ? в блоках addition и where!!!
     *
     * @param string $table    -- таблица, сложный запрос или пусто (предыдущий prepare)
     * @param string $where    -- условие поиска записей. Может содержать параметры запроса!
     * @param array  $bind     -- бинтуемые данные
     * @param bool   $isClose  -- закрывать курсор?
     * @param string $addition -- сложный update - продолжение запроса вместе с SET частью.
     *
     * @return bool
     *
     * @example сложный запрос с JOIN:
     *   UPDATE table1 AS t1
     *     JOIN table2 AS t2 ON t2.key = t1.key
     *     SET t2.val2 = t1.val1
     *     WHERE t1.where IN( ?,?,?)
     *   ;
     * , вызов метода:
     *     $this->update(['t1'=>'table1'] | 'table1 as t1', 't1.where IN(?,?,?)', $bind, true
     *       , 'JOIN table2 AS t2 ON t2.key = t1.key SET t2.val2 = t1.val1'
     *     );
     * , или вызов:
     *     $this->update('table1 as t1 JOIN table2 AS t2 ON t2.key = t1.key', 't1.where IN(?,?,?)', $bind, true
     *       , 'SET t2.val2 = t1.val1'
     *     );
     * , где $bind содержит данные в порядке перечисления параметров запроса ..
     *
     * 2. вызов с массивом данных ['field'=>'val',..] И без параметров(?) возможен только для простого UPDATE:
     *     $this->update('table', 'where_string', $data);
     */
	public function update($table, $where, $bind = array(), $isClose=true, $addition = 'SET')
	{
        $sqlHash = $this->profiling(__METHOD__, Profiling::PROF_START);

		if( $table != '' ) {
			list($name, $as) = DbUtils::parseTable($table);

			$sets = '';
			if( $addition == 'SET')
				foreach($bind as $field=>$val)
					if( !is_int($field) )
						$sets .= ($sets !=''? ', ' : '') . " {$as}.`{$field}` = ?";

			if( !empty($where) ) $where = 'WHERE ' . $where;
			if( $name == $as ) $as = '';
			else $as = 'AS ' . $as;

			$this->st = $this->pdo->prepare( $sql="UPDATE {$name} {$as} {$addition} {$sets} {$where};" );
            $this->printDebug(__METHOD__ . ' prepared', $sql);
		}

		$res = ($this->st !== false);
		if( !empty($bind)) {
			if( $res ) $res = $this->st->execute( array_values($bind) );
            $this->printDebug(__METHOD__ . ($res? ':' : ': not') . ' executed', '', $bind);
		}

		if( $isClose && isset($st) && $this->st !== false ) { $this->st->closeCursor(); unset($this->st); }

        $this->profiling($sqlHash, Profiling::PROF_END);
		return $res;
	}
    /**
     * Читает заданные строкой поля из таблицы по заданному pkey!
     *
     * @param string $table     -- откуда читать
     * @param string $fields    -- какие поля отдать (часть SELECT запроса)
     * @param array $parts		-- доп. части запроса ['where'=>mixed, 'order'=>mixed, 'group'=>mixed, 'having'=>mixed, 'limit'=>mixed]
     *
     * @return array -- Rowset
     */
    public function &findAll($table, $fields, array $parts)
    {
        $sqlHash = $this->profiling(__METHOD__, Profiling::PROF_START);
        $rows = $this->selectAll(
            $sql = "SELECT {$fields} FROM {$table}"
                .(!empty($parts['where'])?  ' WHERE '    . $parts['where']  : '')
                .(!empty($parts['group'])?  ' GROUP BY ' . $parts['group']  : '')
                .(!empty($parts['having'])? ' HAVING '   . $parts['having'] : '')
                .(!empty($parts['order'])?  ' ORDER BY ' . $parts['order']  : '')
                .(!empty($parts['limit'])?  ' LIMIT '    . $parts['limit']  : '')
        );
        $this->printDebug(__METHOD__ . ': ' . (empty($rows)? 'Not ' : '') . 'founded.');
        $this->profiling($sqlHash, Profiling::PROF_END);

        return $rows;
    }
}
