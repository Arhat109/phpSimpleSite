<?php

namespace utils;

/**
 * Базовый класс объектов поддерживающих пространство отладки в виде передаваемого указателя куда выводить сообщения.
 * Вывод формируется в виде html <p> тегов, разнесенных переводами строк для возможности вывода в браузер.
 * Единообразия для.
 *
 * Классы-наследники могут встраивать свои уровни сообщений .. в разумных пределах.
 *
 * @global bool IS_DEBUG -- используется тут для общего закрытия сообщений по отладке "сквозняком".
 *
 * @property int   $debLevel   -- уровень отладки объекта
 * @property array $debContent -- внешний массив сбора отладочных сообщений.
 *
 * @author fvn20160916..
 *         fvn20190410 - документирование, приведение в порядок, улучшения.
 *
 * @TODO: можно дополнить таблицей SQL для хранения отладки в БД (роботы, автоматы)
 */
class Debugging
{
    // Базовые уровни ошибок. Сообщение выводится, если внутренний уровень сообщений объекта меньше или равен этому
    // @example: $this->debLevel = DEB_WARNING, $this->debug(DEB_WARNING, $message) -- выведет предупреждения и выше, но не DEB_INFO.
    const DEB_ALL     =     0;
    const DEB_INFO    =  1000;
    const DEB_WARNING =  2000;
    const DEB_ERROR   =  3000;
    const DEB_FATAL   = 10000;
    const DEB_NONE    = 99999; // будем считать это максимальным уровнем :)

    public $debLevel   = self::DEB_NONE;     // текущий порог вывода сообщений
    public $debContent = null;               // куда "свистеть"

    /**
     * Функция вывода сообщений с заданным уровнем
     *
     * @param $level
     * @param $message
     */
    public function debug($level, $message)
    {
        if( IS_DEBUG && ($level >= $this->debLevel) && !empty($this->debContent) ){
            $this->debContent .= "<p>\n{$message}\n</p>";
        }
    }

    /**
     * Посмертный дамп. Выводим сообщение в контекст отладки и генерим исключение
     *
     * @param string $fatal -- текст сообщения
     * @param int    $code  -- optional(500) код завершения
     *
     * @throws \Exception
     */
    public function raise($fatal, $code=500)
    {
        $this->debug(self::DEB_FATAL, $fatal );
        throw new \Exception($fatal, $code);
    }

    /**
     * Устанавливает уровень выводимых отладочных сообщений на 1 меньше заданного или 0.
     *
     * @param $level
     */
    public function setLevel($level)
    {
       $this->debLevel = ($level > 0? $level-1 : 0);
    }

    /**
     * Setter отладочного контента, дабы не городить конструктор
     *
     * @param string* $debContent
     */
    public function setDebContent( &$debContent )
    {
        $this->debContent = $debContent;
    }

    // ============================================================================================================= //
    //  Конструкторы и псевдо-конструкторы объектов класса                                                           //
    // ============================================================================================================= //

    /**
     * Конструктор копированием из массива, возможно дочернего класса
     *
     * Если массив задан - то устанавливает что в нем есть или остается статическая инициализация
     * Если массив пуст - то устанавливает глобальный отладочный контекст и уровень WARNING.
     *
     * @param array $params
     */
    public function __construct(array $params=[])
    {
        global $debugContent;

        if( !empty($params) ) {
            if( isset($params['debLevel'])   ){ $this->debLevel = $params['debLevel']; }
            if( isset($params['debContent']) ){ $this->debContent = &$params['debContent']; }
        } else {
            $this->debContent = &$debugContent;
            $this->debLevel = self::DEB_WARNING;
        }
    }

}
