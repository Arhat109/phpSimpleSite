<?php
namespace utils;

/**
 * Class HtmlUtils -- общие типовые утилиты для формирования HTML
 */
class HtmlUtils
{
    /**
     * Превращает заданный uri и массив параметров в полноценный УРЛ
     * , исключая типовые параметры Зенда: модуль, контроллер, действие
     *
     * @param  string $uri
     * @param  array  $params
     * @return string
     *
     * @author fvn-20121114
     * @see /default/controller/AuthController
     *   ->logoutAction()
     *   ->registerAction()
     */
    static public function makeZendUrl( $uri, $params )
    {
        if( isset($params['module'])     ) unset($params['module']);
        if( isset($params['controller']) ) unset($params['controller']);
        if( isset($params['action'])     ) unset($params['action']);

        if( empty($params) ) return $uri;

        return $uri . (strpos($uri, '?')===false? '?' : '&') . http_build_query($params, '', '&');
    }
    /**
     * Превращает ассоциативный массив опций тега в строку для вставки в html-код
     *
     * @param array $attribs('name'=>'val',...)
     * @return string
     *
     * @author fvn-20130521
     * @see /default/views/scripts/partials/fvnbox.phtml,...
     */
    static public function makeAttribs( array $attribs = null )
    {
        $attrs = '';
        if( !empty($attribs) )
            foreach( $attribs as $word=>$val)
                $attrs .= " {$word}=\"{$val}\"";
        return $attrs;
    }
    /**
     * Превращает ассоциативный массив опций стиля в строку для вставки в html-код
     *
     * @param array $attribs('name'=>'val',...)
     * @return string
     *
     * @author fvn-20130521
     * @see /default/views/scripts/partials/fvnbox.phtml,...
     */
    static public function makeCSS( array $attribs = null )
    {
        $attrs = '';
        if( !empty($attribs) )
            foreach( $attribs as $word=>$val)
                $attrs .= " {$word} : {$val};";
        return $attrs;
    }

    /**
     * Возвращает строку опций селекта из Rowset array
     *
     * @param array  $opts	 -- массив наборов или полей (Rowset)
     * @param string $key    -- индекс ключа в наборе
     * @param string $val    -- индекс значения в наборе
     * @param int    $defval -- номер в общем списке значения по умолчанию
     * @param array  $prevs  -- доп значения, если заданы. Идут первыми!
     *
     * @return string <option></option>...
     */
    static public function htmlOptions(array $opts, $key, $val, $defval=0, array $prevs = array() )
    {
        $res = '';
        foreach( $prevs as $id=>$def) {
            $res .= "\n<option value=\"{$id}\" "
                 .($id == $defval? 'selected="selected"': '')
                 .">{$def}</option>"
            ;
        }
        foreach( $opts as $opt ) {
          if( empty($opt[$val]) ){ continue; } // пропуск пустых текстовок в Rowset

          $res .= "\n<option value=\"{$opt[$key]}\" "
               .($opt[$key] == $defval? 'selected="selected"': '')
               .">{$opt[$val]}</option>"
          ;
        }
        return $res;
    }
    /**
     * Формирует массив опций для html-select с группировкой
     * , возвращая html список опций. Предварительная сортировка по группам и далее - обязательна.
     * схема подстановки значений (групп, меток): array('field'=>array('pre','post'))
     *
     * 2. Значения по умолчанию выбранных и запрещенных должны совпадать по написанию со всех сброкой ключа, если он составной!
     * 3. HTML не предусматривает вложенность групп друн в друга! Только один уровень!!!
     *
     * @param  array  $rowSet   -- результат выборки из БД. список записей
     * @param  array  $cfg(     -- параметры генератора опций:
     * @param      'group'    => {null|string|array} -- название группировочного поля (новое значение - новая группа)
     * @param      ,'key'     => {string|array}      -- название поля значений или схема
     * @param      ,'label'   => {string|array}      -- название поля меток или схема подстановки поля меток...
     * @param      ,'attribs' => array(              -- атрибуты тегов <option>
     * @param           'group'     => string        -- атрибуты для тега optgroup (disabled - других нет!)
     * @param           ,'label'    => string        -- атрибуты для тега option
     * @param           ,'selected' => string        -- атрибуты для предвыбранных option
     * @param           ,'disabled' => string        -- атрибуты для предзапрещенных option
     * @param ))
     * @param string|array $selected -- значение/список предварительно выбранных опций
     * @param string|array $disabled -- значение/список запрещенных к выбору ОПЦИЙ (группы можно установить через атрибуты групп!)
     *
     * @return array('options'=>html-string, 'selected'=>array, 'disabled'=>array) -- html-текст опций для селектора <option>...
     *
     * @author fvn-20130427.., fvn-20130524: добавлен возврат массивов "предвыбранных" и "предзапрещенных" опций с текстами.
     * @see /goodsrequest/views/scripts/index/add.phtml
     * @see , /goodsrequest/views/scripts/ajax/getfirms.phtml
     */
    static public function htmlOptgroup( $rowSet, array $cfg, $selected = null, $disabled=null )
    {
        if( empty($rowSet) )	return array();

        $res = $old = '';
        $resSelected = $resDisabled = array();

        $atrLabel = (isset($cfg['attribs']['label'])? $cfg['attribs']['label'] : '');
        $atrGroup = (isset($cfg['attribs']['group'])? $cfg['attribs']['group'] : '');
        $atrSelected = (isset($cfg['attribs']['selected'])? $cfg['attribs']['selected'] : '');
        $atrDisabled = (isset($cfg['attribs']['disabled'])? $cfg['attribs']['disabled'] : '');
        if( isset($selected) ) $eqinSelected = is_array($selected);
        if( isset($disabled) ) $eqinDisabled = is_array($disabled);

        foreach($rowSet as $row) {
            if( isset($cfg['group']) ) {
                $group = '';
                if( is_array($cfg['group']) )
                    foreach($cfg['group'] as $name=>$adding)
                        $group .= $adding[0] . (empty($row[$name])? $adding[2] : $row[$name]) . $adding[1];
                else $group = $row[$cfg['group']];

                if( $old != $group ) {
                    // если есть группы и новое значение группирующего поля!
                    $res .= ($old? "</optgroup>\n" : '').'<optgroup label="'.htmlspecialchars($group)."\" {$atrGroup} >\n";
                    $old = $group;
                }}
            $key = '';
            if( is_array($cfg['key']) )
                foreach( $cfg['key'] as $name=>$adding)
                    $key .= $adding[0] . $row[$name] . $adding[1];
            else $key = $row[$cfg['key']];
            $key = htmlspecialchars($key);

            $label = '';
            if( is_array($cfg['label']) )
                foreach( $cfg['label'] as $name=>$adding)
                    $label .= $adding[0] . (empty($row[$name])? $adding[2] : $row[$name]) . $adding[1];
            else $label = $row[$cfg['label']];
            $label = htmlspecialchars($label);

            $res .= '<option value="' . $key . '" ';
            if(
                isset($eqinSelected) && (
                    ($eqinSelected && in_array($key, $selected))
                    || (!$eqinSelected && ($key == $selected))
                )
            ) {
                $res .= " selected=\"selected\" {$atrSelected}";
                $resSelected[$key] = $label;
            } elseif(
                isset($eqinDisabled) && (
                    ($eqinDisabled && in_array($key, $disabled))
                    || (!$eqinDisabled && ($key == $disabled))
                )
            ) {
                $res .= ' disabled="disabled" ' . $atrDisabled;
                $resDisabled[$key] = $label;
            } else {
                $res .= $atrLabel;
            }

            $res .= ' >'.$label.'</option>'.PHP_EOL;
        }
        return array('options'=>$res, 'selected'=>$resSelected, 'disabled'=>$resDisabled);
    }
    /**
     * Формирует из простого массива исходных данных ключ-значение строку опций для тега select
     * , дополнительно добавляет в массивы предвыбранных и предзапрещенных
     * тексты к их идентам из исходного массива.
     *
     * Полезно, когда есть простой список, но надо сформировать не только опции к селектору формы
     * , но и вывести отдельно тексты, выбранные или запрещенные ранее в форме
     *
     * 1. Атрибуты можно строкой "как есть", или массивом 'name'=>'params', в тегах опций в html допускаются пока только class="name"!
     * 2. Поскольку исходный массив простой, то группы в нём идут "сплошняком" от первой попавшейся и до последней записи!
     *
     * @param array $srcArray -- простой исходный набор опций селектора
     * @param array $params(
     * @param     'groupIds' => {null|array} -- список идентов, которые надо показать как группы опций
     * @param     'selected' => {null|array} -- список "предвыбранных" идентов
     * @param     'disabled' => {null|array} -- список "предзапрещенных" идентов
     * @param     'attribs'  => {null|array(
     * @param         'optgroup' => {null|string|array} -- атрибуты в теге optgroup (разрешен только disabled="disabled"!)
     * @param         'option'   => {null|string|array} -- атрибуты тега option
     * @param         'selected' => {null|string|array} -- атрибуты предвыбранных тегов
     * @param         'disabled' => {null|string|array} -- атрибуты предзапрещенных тегов
     * @param     )}
     * @param )
     *
     * @return array('options','selected','disabled')
     *
     * @author fvn-20130524
     * @see self::htmlOptgroup() -- формирование набора опций для rowset массивов
     * @see /goodsrequest/views/scripts/index/add.phtml
     */
    static public function array2HtmlOptions(array $srcArray, array $params )
    {
        $selector = ''; $selected = $disabled = array();
        $attrArray = (isset($params['attribs'])? $params['attribs'] : null );
        $isGroup = false;

        if( !empty($srcArray) ) {
            foreach($srcArray as $id => $name)
            {
                $tagName = 'option';
                if( isset($params['groupIds']) && in_array($id, $params['groupIds']) ) {
                    $tagName = 'optgroup';
                    if( $isGroup ) $selector .= '</optgroup>'.PHP_EOL;
                    else           $isGroup = true;
                }
                $optClass = (isset($attrArray['option'])? $attrArray['option'] : '');

                if( is_array($params['selected']) && in_array($id, $params['selected']) ) {
                    $selected[$id] = $name;
                    $optClass = (isset($attrArray['selected'])? $attrArray['selected'] : '');
                }
                if( is_array($params['disabled']) && in_array($id, $params['disabled']) ) {
                    $disabled[$id] = $name;
                    $optClass = (isset($attrArray['disabled'])? $attrArray['disabled'] : '');
                }

                $attribs = '';
                if( is_array($optClass) ) {
                    foreach($optClass as $attr=>$val)
                        $attribs .= " {$attr}=\"$val\"";
                    ;
                } else $attribs = $optClass;

                $selector .= "<{$tagName} {$attribs} value=\"{$id}\">{$name}</{$tagName}>\n";
            }
            if( $isGroup ) $selector .= '</optgroup>'.PHP_EOL;
        }
        return array('options'=>$selector, 'selected'=>$selected, 'disabled'=>$disabled);
    }
}