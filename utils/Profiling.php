<?php

namespace utils;

/**
 * Класс для профилируемых объектов и их методов с ВНЕШНИМ буфером(массивом) для профилирования!
 *
 * Структура внешнего накопительного массива данных по профилированию:
 * [
 *   label => [
 *     PROF_START => [ PROF_COUNT=>int, PROF_TIME=>float, PROF_MEMORY=>float ],
 *     PROF_END   => [ PROF_COUNT=>int, PROF_TIME=>float, PROF_MEMORY=>float ],
 *     // служебные, типа protected:
 *     cnt=>int, timestart =>float, timeend => float, memorystart => float, memoryend=>float
 *   ], ..
 * ]
 * Каждая запись снабжается меткой что профилируется и имеет 2 массива начала процесса и его завершения.
 * Каждая точка: начало и завершение содержит (в т.ч. и усредненные) данные по времени и размеру памяти + количество усреднений.
 *
 * @property bool  $isCallUnique -- каждый замер добавлять отдельно или усреднять подобные?
 * @property array $profiler     -- внешний (глобал в т.ч.) массив куда складывать результаты профилирований.
 *
 * @author fvn-20160916..
 *         fvn20190410 - доработка, документирование..
 */
class Profiling
{
    const PROF_START = 'start';
    const PROF_END   = 'end';

    const PROF_COUNT  = 'cnt';
    const PROF_TIME   = 'time';
    const PROF_MEMORY = 'mem';

    /** @var bool $isCallUnique -- усреднять все замеры метки или каждый замер - уникален? */
    public $isCallUnique = false;

    /** @var array $profiler -- (внешний) массив куда складывать все замеры и результаты */
    public $profiler = null;

    /**
     * Функция профилирования времени исполнения вызывающего кода.
     * Если замеры уникальны, то возвращает уникальную метку, иначе ту, что пришла в параметре.
     *
     *
     * @param string $method -- метка профилирования, напр. имя вызвавшей функции
     * @param string $tag    -- { PROF_START | PROF_END }
     *
     * @return string -- обрабатываемая метка (тэг) при PROF_START - для последующего вызова с PROF_END!
     */
    public function profiler($method, $tag)
    {
//die(var_dump($this));
        if( empty($this->profiler) ) return '';

        if( $tag == self::PROF_START ){
            // отдельные замеры для каждого вызова?
            if( $this->isCallUnique ) {
                $method .= '_' . microtime(true);
                $this->profiler[$method][self::PROF_COUNT] = 0;
            }
        }
        $ptrSave = &$this->profiler[$method];

        $ptrSave['time'.$tag]   = microtime(true);
        $ptrSave['memory'.$tag] = memory_get_usage(true);

        if( $tag == self::PROF_END )
        {
            $count = ( isset($ptrSave[self::PROF_COUNT])? $ptrSave[self::PROF_COUNT] : 0); // может не быть, если группа первый вход
            $time = $ptrSave['time'.self::PROF_END]   - $ptrSave['time'.self::PROF_START];
            $mem  = $ptrSave['memory'.self::PROF_END] - $ptrSave['memory'.self::PROF_START];

            switch( $count ){
                case 0:
                    // одноразовые метки попадают только сюда!
                    $ptrSave[self::PROF_TIME]   = $time;
                    $ptrSave[self::PROF_MEMORY] = $mem;
                    $ptrSave[self::PROF_COUNT]  = 1;
                    break;
                case 1:
                    // усредняем, поскольку задано (иначе метка уникальна при каждом PROF_START!)
                    $ptrSave[self::PROF_TIME]   = ($ptrSave[self::PROF_TIME]  + $time) / 2;
                    $ptrSave[self::PROF_MEMORY] = ($ptrSave[self::PROF_MEMORY]+ $mem)  / 2;
                    $ptrSave[self::PROF_COUNT]  = 2;
                    break;
                default:
                    // продолжаем усреднение бегущим средним
                    $ptrSave[self::PROF_COUNT]++;
                    $count = $count / ($count+1);
                    $ptrSave[self::PROF_TIME]   = $ptrSave[self::PROF_TIME]  *$count + $time/$ptrSave[self::PROF_COUNT];
                    $ptrSave[self::PROF_MEMORY] = $ptrSave[self::PROF_MEMORY]*$count + $mem /$ptrSave[self::PROF_COUNT];
            }
        }
//die(var_dump($this, $method, $tag));
        return $method;
    }

    /**
     * Setter для установки куда профилировать, дабы не делать конструктор класса
     *
     * @param array& $profiler
     */
    public function setProfiler( array &$profiler ){ $this->profiler = $profiler; }

    // ============================================================================================================= //
    //  Конструкторы и псевдо-конструкторы объектов класса                                                           //
    // ============================================================================================================= //

    /**
     * Конструктор копированием из массива, возможно дочернего класса
     *
     * Если массив задан - то устанавливает что в нем есть или остается статическая инициализация
     * Если массив пуст - то устанавливает глобальный отладочный контекст и уровень WARNING.
     *
     * !!! Свойства Debugging также будут установлены по усмолчанию, если массив пуст !!!
     *
     * @param array $params
     */
    public function __construct(array $params=[])
    {
        global $profiler;

        if( !empty($params) ) {
            if( isset($params['isCallUnique']) ){ $this->isCallUnique = $params['isCallUnique']; }
            if( isset($params['profiler'])     ){ $this->profiler = &$params['profiler']; }
        } else {
            $this->profiler = &$profiler;
            $this->isCallUnique = false;
        }
    }

}
