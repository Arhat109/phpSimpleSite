<?php
namespace libarary\GRB;

use utils\Debugging as DEB;
use utils\Profiling AS PROF;

/**
 * Класс объектов - "читателей" (поставщиков контента). Основной метод ->get()::string получить содержимое из источника по заданному url(fname)
 *
 * Поставщик контента(читатель) обязан быть переключаемым: "откуда брать содержимое" - из сохраненного файла(кеша) ИЛИ из источника данных.
 *
 * Настроечные параметры читателя разбиты на 3 группы:
 *   а) srcData -- статические описатели источника;
 *   б) context -- описатели соединения в стиле PHP socket context;
 *   в) params  -- прочие динамические и статические описатели получения данных, в т.ч. и нагрузочные (как часто можно дергать источник)
 *
 * Метод get() по умолчанию может отдавать только локальную копию контента. Все остальные способы - реализация дочерних классов
 *
 * @author fvn-20160919
 */
class ContentProvider
{
  /** @var PROF $profiler -- @TODO trait местный профилировщик */
  public $profiler = null;
  
  /** @var DEB $debugger -- @TODO trait местный контекст отладчика */
  public $debugger = null;

    /** @var array $isLocal -- читать содержимое локально из кеша или загружать новое? */
    public $isLocal = true;

    /** @var bool $isCacheable -- автозаписать содержимого в кеш? */
    public $isCacheable = true;

    /** @var ContentSource $source -- @see grabbers_sites table */
    public $source = null;

    /** @var array $wrappers -- @see PHP stream: source context array ['wrapper'=>['param'=>val, ..], ..] */
    public $wrappers = [];
    /** @var Resource $context -- @see PHP stream: created context resource or null */
    public $context = null;
    /** @var string $contextWrapper -- current active wrapper from $wrappers for $this->context now */
    public $contextWrapper = '';

    /** @var array $params -- нагрузочные параметры и прочие ограничители, не вошедшие в предыдущие классы настроек */
    public $params = ['contextFlags'=>null]; // 'maxlen'=>null -- и др. значение блокирует получение по УРЛ полностью!

    /**
     * Инициализация контекста потока. Базовая часть.
     *
     * @param $stream
     * @param array $params -- ЗАМЕЩАЕТ содержимое $this->wrappers[$stream] если оно было..
     */
    public function init($stream, array $params = []){
        if( !empty($this->context) ){
            // Release old context before: Нет способа освободить занятый ресурс - типа автоматически!
            unset($this->context);
        }

        if( !empty($params) )
            $this->wrappers[$stream] = $params;

        switch( $stream ){
            case 'file': // all simple wrappers without data array:
            case 'curl':
                break;
            default:
                if( !empty($this->wrappers[$stream]) ) {
                    $this->context = stream_context_create($this->wrappers[$stream], $this->params);
                    $this->contextWrapper = $stream;
                }
        }
    }

    /**
     * Базовый примитив чтения файла/URL через file_get_contents() всегда что-то возвращает..
     *
     * @param string $furl -- файл/урл
     * @return array
     */
    public function _getContent($furl)
    {
        $hash = $this->profiler(__METHOD__, PROF::PROF_START);

        $error = '';
        $content = file_get_contents(
            $furl
            , $this->params['contextFlags']
            , $this->context
        );
        if( $content === false ){
            $error = __METHOD__.':: ERROR! reading content for '.$furl;
            $this->debug(DEB::DEB_ERROR, $error);
        }

        $this->profiler($hash, PROF::PROF_END);
        return [
            'content'        => $content
            , 'error'        => $error
            , 'lang'         => ''
        ];
    }

    /**
     * Базовый метод получения контента. Абстрактный класс определяет только возможность чтения содержимого локально
     * сохраненного ранее.
     *
     * @param string $url
     * @return Content
     * @throws \Exception
     */
    public function get( $url )
    {
        $hash = $this->profiler(__METHOD__, PROF::PROF_START);

        if ($this->isLocal) {
            $fname = ($this->source->makeFname('cache', $url));

            if( !is_file($fname) ){
                $this->debug(DEB::DEB_WARNING, __METHOD__.":: WARNING! File {$fname} not founded..");
                $newContent = ['content'=>'', 'error' => 'ERROR! file not found'];
            }else {
                $newContent = $this->_getContent($fname);
                $this->debug(DEB::DEB_INFO, __METHOD__.":: Was reading from cache {$fname} ..");
            }
        } else {
            $fname = $this->source->makeUrl($url);
            $newContent = $this->_getContent($fname);
            $this->debug(DEB::DEB_INFO, __METHOD__.":: {$url} was reading from url {$fname} ..");
            if( $this->isCacheable ) {
                $this->save($url, $newContent);
            }
        }
        $this->profiler($hash, PROF::PROF_END);
        return new Content($newContent);
    }

    /**
     * Автосохранение полученного контента.
     * @TODO: дополнять по мере развития кода записью в БД если потребуется про сохраненные страницы в кеше
     *
     * @param string  $url
     * @param Content $content
     */
    public function save($url, Content $content)
    {
        $hash = $this->profiler(__METHOD__, PROF::PROF_START);

        if( !empty($content->content) && empty($content->error) ) {
            $fname = $this->source->makeFname('cache', $url);
            $dir = dirname($fname);

            if( !is_dir($dir) ){
                if( mkdir($dir, 0777, true) ){ chmod($dir, 0777); }
            }
            if( is_dir($dir) ){
                $fh = fopen($fname, 'wb');
                if ($fh) {
                    fputs($fh, $content->content);
                    fclose($fh);
                    chmod($fname, 0666);
                    $this->debug(DEB::DEB_INFO, __METHOD__ . ":: Saving {$url} into cache-file {$fname} ..");
                } else {
                    $content->error = "ERROR! Can't open cache content file {$fname}\nfor save {$url}";
                    $this->debug(DEB::DEB_ERROR, $content->error);
                }
            } else {
                $content->error = "ERROR! Can't create cache dir: {$dir}!";
                $this->debug(DEB::DEB_ERROR, $content->error);
            }
        }else {
            $content->error = "WARNING! Empty content or error. Not saved..";
            $this->debug(DEB::DEB_WARNING, $content->error);
        }
        $this->profiler($hash, PROF::PROF_END);
    }

    // ============================================================================================================= //
    //  Конструкторы и псевдо-конструкторы объектов класса                                                           //
    // ============================================================================================================= //

    /**
     * Конструктор копированием из массива, возможно от дочернего класса
     *
     * Если массив задан - то устанавливает что в нем есть или остается статическая инициализация
     * Если массив пуст - то устанавливает глобальный отладочный контекст и уровень WARNING.
     *
     * @param array $params
     */
    public function __construct(array $params=[])
    {
        foreach( $params as $prop=>$val )
            switch($prop){
                case 'isLocal':
                case 'isCacheable':
                case 'source':
                case 'wrappers':
                case 'context':
                case 'contextWrapper':
                case 'params':
                    $this->$prop = $val;
                    unset($params[$prop]);
                    break;
                default:
                    continue;
            }
        parent::__construct($params);
    }

}