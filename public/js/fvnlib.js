/**
 * Объект (класс) универсального и единого места валидации разных значений и прочего общего JS-функционала системы лидов
 *
 * @author fvn-20170529
 * @TODO: Добавить опцию молчаливой проверки без надписи "вроде ок, можно сохранять" перед отправкой..
 */
// иденты в таблице тегов для типа Tags::CONTACTS .. фиг знает как лучше .. колонку бы надо ..
var CONTACT_ID_PHONE    = '251';
var CONTACT_ID_EMAIL    = '252';
var CONTACT_ID_ICQ      = '253';
var CONTACT_ID_VKCOM    = '254';
var CONTACT_ID_WHATSAPP = '255';

function getClass(obj) {
    return {}.toString.call(obj).slice(8, -1);
}

var fvn = {
    // показ сообщения во всплывающем блоке на небольшое время возле заданного элемента
    messageShow: function(obj, message){
        var div = '<div id="fvnMessageBox"><div class="modal-fly btn-delete">'+message+'</div></div>';
        var pos = obj.position();
        var zind = obj.css('z-index');

        obj.append(div);
        obj.find('#fvnMessageBox .modal-fly').show(500).css('z-index', zind+100).position({left: pos.left+32, top: pos.top+32});
        setTimeout(function(){
            obj.children('#fvnMessageBox .modal-fly').hide(500);
            obj.children('#fvnMessageBox').remove();
        }, 1000);
    },
    errorHide: function(obj, type){
        obj.parent().children('#errors_' + type).hide();
        obj.removeClass('required');
    },
    errorShow: function(obj, type){
        obj.parent().children('#errors_' + type).show();
        obj.addClass('required');
    },
    validateTrue : function(model){ $(model.errorSelector).html('<span style="color:green">вроде всё верно, можно сохранить..</span>').show(); },
    validateFalse: function(model){ $(model.errorSelector).html('<span style="color:red">не все поля запонены верно..</span>').show();         },
    /**
     *     Валидация заданного поля(obj) или списка полей входящих в блок obj по правилу(type) с контрольными данными(pattern)
     *
     * Поле/Блок obj должен иметь родителя (напр. обрамляющий <DIV>) в котором вместе с ним есть блок текстовки ошибки для
     * этого правила с классом .errors_{type}
     * А также поле/блок должен иметь класс CSS .required
     *
     *  @return boolean;
     */
    validate: function (obj, type, pattern)
    {
console.log('fvn.validate():: started for');
console.log(obj);

        var data = obj.val();
        var res = false;
console.log('fvn.validate():: name='+obj.prop('name')+', d='+data+', t='+type+', p='+pattern);
        switch (type) {
            case 'minlength' : res = data.length >= Number(pattern);                                        break;
            case 'mincheck'  : res = obj.parent().find('INPUT:checkbox:checked').length >= Number(pattern); break;
            case 'minradio'  : res = obj.parent().find('INPUT:radio:checked').length >= Number(pattern);    break;
            default:
                switch (type) {
                    case 'digit'  :
                        pattern = new RegExp('^\\d{'+pattern+'}$'); break;
                    case 'phone'  :
                    case CONTACT_ID_PHONE:
                        pattern = new RegExp('^\\d{11}$'); break;
                    case 'email'  :
                    case CONTACT_ID_EMAIL:
                        pattern = new RegExp('^([a-zA-Z0-9_\\.-])+@([a-zA-Z0-9_\\.-])+\\.\\w{2,}$'); break;
                    case 'date'   :
                        pattern = new RegExp('^\\d{2}\\.\\d{2}\\.\\d{4}$'); break;
                    case 'vkcom'  :
                        pattern = new RegExp('^vk\\.com/.*$'); break;
                    case 'regexp' : break;
                    default:
                        alert('ERROR! fvn.validate(): '+obj.prop('name')+' Неизвестный способ валидации '+type+', доп:'+pattern+' .. Пусть меня научат..');
                        return false;
                }
                res = pattern.test(data);
        }
console.log('res='+res);
        if (res) { this.errorHide(obj, type); }
        else     { this.errorShow(obj, type); }
        return res;
    },

    toValidate: function(model){
        $(model.formSelector+' INPUT').blur().change();
        $(model.formSelector+' SELECT').blur().change();
        $(model.formSelector+' TEXTAREA').blur().change();
    },
    /**
     * Проверка валидации всех полей объекта data.isValid и выполнение действий по разрешению data.validateTrue()
     * или запрету data.validateFalse()
     *
     * @param data { isValid:{}, validateTrue:(), validateFalse:() }
     * @return boolean
     */
    validateSubmit: function (data) {
        var res = true;
        for (prop in data.isValid) {
            if( data.isValid.hasOwnProperty(prop) && !data.isValid[prop]) { res = false; }
        }
        if (res) {
            if( 'validateTrue' in data ){ data.validateTrue();     }
            else                        { this.validateTrue(data); }
        }
        else{
            if( 'validateFalse' in data ){ data.validateFalse();     }
            else                         { this.validateFalse(data); }
        }
console.log('validateSubmit():: res='+res+', '+JSON.stringify(data.isValid));
        return res;
    },
    /** Обощенная закрывашка ближайшего охватывающего нажатую кнопку селектора или заданного объекта(селектора), если без события */
    close: function(e, selector){
        if( getClass(e) == 'MouseEvent' ){ // некузяво, в случае если тип события окажется иным ..
            $(e.target).closest(selector).hide();
            e.stopPropagation();
        }else if( typeof selector == 'string' ){
            $(selector).hide();
        }else{
            selector.hide();
        }
    },
    /** обобщенная открывашка заданного объекта со смещением модального окна "куда задано". fvn20180808: добавлен ОJQ-object как selector
     *  selector -- string|JqueryObject что открываем
     *  obj      -- JqueryObject относительно какого объекта
     *  offset   -- {top,left} куда сместить от объекта открытия
     */
    open: function(selector, obj, offset){
        var pos = obj.offset();
        var target;
        var isString;

        if( typeof selector == 'string' ){ target = $(selector); isString = true;  }
        else                             { target = selector;    isString = false; }
//console.log(selector);
        if( target.length == 1 ){
            target.show(250).offset({left: pos.left+offset.left, top: pos.top+offset.top});
            if( target.find('FORM [name][type!="hidden"]').length > 0 ) {
                // если внутри форма - делаем её фокусом ввода первый эл-т формы
                target.find('FORM [name][type!="hidden"]')[0].focus();
            }
        }else{
            console.log('ERROR! target not founded. ' + (isString? selector : JSON.stringify(selector[0])) );
        }
    },
    /** callback() функция погрузки чего-либо в ДОМ params.objTo согласно списку параметров.
     *  может искать в т.ч. включенные кнопки, выбранные значения по ДОМ params.from ..
     *  params: { uri, objTo, options, from, find, findTo } -- откуда, куда, параметры, доп. ДОМ поиска, что ищем (м.б. массив шаблонов)..
     *  ! find[] -- м.б. список шаблонов поиска (каждый >1 объекта) findTo можно строкой как имя подмассива куда складывать
     *  ! Если есть from, то м.б. find и findTo
     *  ! findTo[] -- массив от prop::name найденного объекта или согласован по номерам со списком поиска find[]
     *
     *  @see svt.local branch admins :: /views/firm/detail.php -- оперативная подгрузка блоков меню в виде "табов"..
     *  @author fvn20190204 - добавлен параметр params.findTo "куда складывать" найденное в опциях Ajax (если есть from)
     */
    loadAjax: function(params){
        var ajaxParams = params.options; // базовый набор параметров к ajax выборке
console.log('fvn.loadAjax() started.');
console.log(params);
        if( params.from ){ // задан объект ДОМ, где искать доп. параметры к ajax из поля find
console.log('from is present');
//console.log( params.find.prototype.toString.call([]) ); // params.find.prototype is undefined
//console.log( typeof(params.find) );                     // object
//console.log( params.find.isArray );                     // params.find.isArray undefined; params.find.isArray() is not a function
//console.log( params.find instanceof Array);             // true только для обектов из одного фрейма! Каждый фрейм - свои классы !!!

            finds = params.find instanceof Array? params.find : [params.find]; // образцы поиска тут
console.log(finds);
            for(var i=finds.length-1; i>=0; i--){ // JS не умеет делать преддекремент!!!
console.log(i + ', :' + finds[i] +': '+ params.from.find(finds[i]).length );
                params.from.find(finds[i]).each(function(idx){
console.log('fvn.ajaxLoad():: нашел это');
console.log($(this));
                    var founded;
                    if( $(this).prop('type') == 'checkbox' ){
                        founded = $(this).prop('checked')? 1 : 0;
                    }else{
                        founded = $(this).val();
                    }
                    if( params.findTo ){
                        if( params.findTo instanceof Array ){
                            ajaxParams[params.findTo[i]] = founded; // findTo[] -- д.б. согласованы номера в find[] и findTo[]!
                        }else{
                            ajaxParams[params.findTo][$(this).prop('name')] = founded; // строка -- имя массива параметров по имени объектов
                        }
                    }else{
                        ajaxParams[$(this).prop('name')] = founded; // нет findTo складываем тупо по имени
                    }
                });
            }
        }
        $.post(params.uri, ajaxParams, function(res){
            if( 'error' in res ){ params.objTo.html(res.error); return; }

            params.objTo.html(res.html);
        }, 'json');
    },
    /** переключает заданный объект */
    toggle: function(selector, obj, offset){
        var target;

        if( typeof selector == 'string' ){ target = $(selector); isString = true;  }
        else                             { target = selector;    isString = false; }

        if(target.css('display') == 'none'){ this.open(selector, obj, offset);         }
        else                               { this.close(new Event('click'), selector); }
    },
    /** fvn20181116: перенесено в общие: переключалка элементов меню (теперь может подгружать AJAX данные, с учетом параметров если надо)
     *  оперирует с CSS классами menu-... @see fvn.css
     */
    menuToggle: function(name, obj, params){
        var menuBtns = obj.parent();
        var menuObj  = menuBtns.parent();
        var newInner = menuObj.find(name);

        if( params && params.method ){
            params.method(params); // вызываем callback для подгрузки ajax данных в блок @see ./fvn.loadAjax()
        }
        menuObj.find('.inner').each(function(){ $(this).hide(); });
        newInner.show();
        menuBtns.find('.menu-active').removeClass('menu-active').addClass('menu-item');
        obj.removeClass('menu-item').addClass('menu-active');
    },
    /** Меняет кнопки на форме: mode={'create' -- кнопка "Создать", 'update' -- "Изменить"} */
    setButtons: function( model, mode, addon ){
        var btnCreate = ( 'btnCreate' in model? model.btnCreate : 'создать');
        var btnUpdate = ( 'btnUpdate' in model? model.btnUpdate : 'заменить');
        var title = 'новый режим формы';

        if (mode == 'create') {
            title = model.titleCreate + (addon? ' '+addon : '');
            $(model.formSelector+' .form-submit').html('<button type="submit" class="btn btn-primary">'+btnCreate+'</button>');
        } else if (mode == 'update') {
            title = model.titleUpdate + (addon? ' '+addon : '');
            // fvn20181109: автопоиск метода сохранения
            var funcSave = ('save' in model
                ? model.selfName+'.save($(this).closest(\'FORM\'))'                        // в модели есть метод save()
                : 'fvn.save('+model.selfName+', $(\''+model.formSelector+' FORM\'), true)' // а нет метода! местный.
            );

            $(model.formSelector+' .form-submit').html(
                '<button type="button" class="btn" onclick="'+funcSave+'">'+btnUpdate+'</button>'
            );
        } else {
            alert('Упс .. fvnlib.js::setButtons() .. научите меня!');
        }
        $(model.formSelector+' H3').html(title);
        $(model.formSelector+' .form-submit').append(
            '&nbsp;<button type="button" class="btn btn-close" onclick="fvn.close(event, \''+model.formSelector+'\');">закрыть</button>'
        );
    },
    /** Заполнение данных формы из объекта. mode={'create'|'update'}, defVals={key:value,..} */
    setData: function( model, mode, defVals, pkey ){
console.log('fvn.setData():: model, mode, defVals, pkey below');
console.log(model);
console.log(mode);
console.log(defVals);
console.log(pkey);
        var obj = $(model.objPrefix + pkey);
        var autoValidate;

        for( var num in model.fields ){
console.log('fvn.setData():: FOR num='+num);
            var val = '';
            switch( mode ){
                case 'create': break;
                case 'update':
                    var objField = obj.find(model.fieldPrefix+model.fields[num]);
                    if( !objField || objField.length == 0 ){
                        console.log('fvn.setData() ERROR:: Поле '+model.fields[num]+' есть в списке формы, но отсутствует у объекта:');
                        console.log(obj);
                        return;
                    }
                    val = objField.text();

                    break;
                default:
                    console.log('ERROR fvn.setData():: mode - новый режим .. научите меня!');
                    return;
            }
            autoValidate = false;
            if( defVals && defVals[model.fields[num]]  ){
                val = defVals[model.fields[num]];
                autoValidate = true;
            }
            // fvn20181109: ошибка в автозаполнении чек-боксов и радио-кнопок:
            var dest = $(model.formSelector+' [name="'+model.fields[num]+'"]');

            if( dest ){
                if( dest.prop('type') == 'checkbox' || dest.prop('type') == 'radio' ){
                    dest.prop('checked', 'checked');
                }else{
                    dest.val(val);
                }
                // fvn20181204: + автовалидация поля как true (автозаполнение!):
                if( autoValidate && (model.fields[num] in model.isValid) ){
                    model.isValid[model.fields[num]] = true;
console.log('fvn.setData():: autovalidate for '+model.fields[num]);
                }
            }else{
                console.log('ERROR! fvn.setData():: Поле '+model.fields[num]+' задано как заполняемое, но не найдено в форме!');
            }
        }
    },
    /** AJAX-submit to server form data: obj */
    save: function(model, obj, autoClose){
        if( autoClose == undefined ){ autoClose = true; }

        $(model.errorSelector).html('').show();
        if( fvn.validateSubmit(model) ) {
            $.post(model.urlSave, {form: obj.serialize(), options: model.options || {}},
                function (data) {
                    if (data.error) { $(model.errorSelector).html(data.error); return; }
                    else            { $(model.errorSelector).html(data.ok);            }

                    model.afterSave(data);
                    if( autoClose ){
                        setTimeout(function(){ $(model.formSelector).hide(500); }, 750);
                    }
                },
                'json'
            );
        }
    },
    /** Добавление нового документа(скана) к персоне vals={key:value,..} */
    create: function(model, obj, vals, options)
    {
        if( options.length==0 || !('offset' in options) || options.offset.length == 0 ){
            options.offset = {left:0, top:32};
        }
        model.options = options;
        $(model.errorSelector).html('').show();
        this.setData(model, 'create', vals);
        this.setButtons(model, 'create', options.headForm? options.headForm : '');
        this.open(model.formSelector, obj, options.offset);
    },
    /** Заполняем форму добавления/правки и показываем для правки запроса */
    update: function(event, model, pkey, options, from)
    {
console.log('fvn.update():: event, model, pkey, options, from below');
console.log(event);
console.log(model);
console.log(pkey);
console.log(options);
console.log(from);
        var obj = $(model.objPrefix + pkey);
        var oldAfterSave = model.afterSave || function(data){};

        if( options.length==0 || !('offset' in options) || options.offset.length == 0 ){
            options.offset = {left:0, top:32};
        }
        model.options = options;
        $(model.errorSelector).html('').show();
        this.setData(model, 'update', {}, pkey);

        model.afterSave = function(data){
            if( !data.error ){
                obj.replaceWith(data.data);
                oldAfterSave(data);
            }
        };
        this.toValidate(model);
        this.setButtons(model, 'update', options.headForm? options.headForm : pkey);

        if( from ){ this.close(new Event('click'), from); }

        this.open(model.formSelector, obj, options.offset);
console.log('fvn.update() ended..');
        event.stopPropagation();
    },
    /** Удаляет запрос через перерисовку страницы со стороны сервера! */
    del: function(model, id)
    {
        var obj = $(model.objPrefix + id);

        if( !model.isConfirm || confirm('Удалить безвозвратно?')){
            $.get(model.urlDelete + id, {}, function(result){
                if( result.ok ){
                    obj.remove();
                }
            }, 'json');
        }
    },
    /** сложить заданное значение в value куда сказано если не ''. Изменяет класс объекта errObj для индикации результата/ошибки */
    setValue: function(key, to, errObj){
        to.val(key);
        if( key != '' ){
            if( errObj.length>0 ){ errObj.removeClass('error'); }
            return true;
        }else{
            if( errObj.length>0 ){ errObj.addClass('error'); }
            return false;
        }
    },
    /** fvn20180922: печать на принтер заданного содержимого */
    print: function(html){
        var wnd = window.open('', 'printing', 'height=700,width=700');

        wnd.document.write(html);
        wnd.document.close(); // necessary for IE >= 10
        wnd.focus(); // necessary for IE >= 10
        wnd.print();
        wnd.close();
    },
    /** fvn20181026: перезагрузка страницы на заданную с добавлением значений GET-параметров name=value.
     *  params - массив(!) строк для JQuery как искать в коде элементы ввода. Автопоиск значений из полей ввода
     *
     *  @see /views/site/manager.php -- перезагрузка р.м. менеджера по выбору.
     */
    restartPage: function(uri, params){
        var p='';
        var n='';

        for(var i=params.length-1; i>=0; i--){
            if( $(params[i])[0] != undefined ){
                n = $(params[i]).prop('name');
                p += (p==''? '?' : '&') + n + '=' + $(params[i]).val();
            }
        }
        window.location = uri + p;
    },
    /** fvn20181204: инициализация всех календариков на странице заново (AJAX-подрузка) */
    initDatepickers: function(){
        $('.date-picker').each(
            function(i){
                $(this).datepicker({changeMonth:true, changeYear:true, numberOfMonths:1, showButtonPanel:true});
            });
    }
};
/** fvn-20170918 динамический селектор списков:
 * перемещает единый список выбора по странице к требуемому элементу и сохраняет текстовку (в вызвавшем) и идент (в скрытом) полях ввода
 * !поля должны быть обрамлены единым блоком!
 */
var dynSelect = {
    targetObj : {},
    /** где открыть и какой список стран и куда потом складывать результат */
    init: function(obj, dynSelect, offset){
        var pos = offset || {left:0, top:parseInt(obj.css('height'))};

        fvn.open(dynSelect, obj, pos);
        this.targetObj = obj;
    },
    /** куда сложить выбранную страну */
    selected: function(dynSelect){
        this.targetObj.val( dynSelect.children(':selected').text() );
        this.targetObj.parent().children('.hidden').val( dynSelect.val() );

        fvn.close({}, dynSelect);
    },
    /** проверка ввода в поисковое поле */
    validate: function(input, selector){
        var val = input.val();
        var key = '';

        $(selector+' OPTION:contains("'+val+'")').each(function(index){
            // блокируем частичное вхождение. Только полное совпадение!
            if( $(this).text() == val ){ key = $(this).prop('value'); return false; }
        });
        return key == ''? false : true;
    },
    /** проверка ввода и получение ключа (OPTION::value) если он найден | false */
    searchKey: function(input, selector){
        var val = input.val();
        var key = false;

//console.log(selector+', cnt='+$(selector+' OPTION:contains("'+val+'")').length);
        $(selector+' OPTION:contains("'+val+'")').each(function(index){
//console.log( $(this).text() );
            // блокируем частичное вхождение. Только полное совпадение!
            if( $(this).text() == val ){
                key = $(this).prop('value');
                return false;
            }
        });
        return key;
    }
};

/**
 * Попытка построения своего Ajax-поиска (автокомплита) с запросом поисковых данных на сервере согласно модели
 *
 * @see /views/person/person_search.php
 */
var ajaxSearch = {
    minCount: 3,         // стартовое кол-во символов в поле ввода, с которого начинается Ajax-поиск
    count : 0,           // текущее кол-во символов в поле ввода
    src: {},             // объект-источник данных, собственно поле ввода для JQUERY
    dstSelector: '',     // селектор JQUERY DOM-блока куда выводить данные (модальный блок)
    options: {
        show:     {},
        search:   {},
        phpModel: '',
        phpBy:    []
    },
    onclick: 'ajaxSearch.defClick($(this))', // имя обработчика выборки элемента в найденном (отправляется в запросе)

    /** onfocus() на поле ввода: инициализация поиска
     * @param src            JqueryObject -- Jquery-DOM INPUT ввода данных,
     * @param searchSelector string       -- селектор блока "найдено",
     * @param options object {
     *   object   offset{left,top} -- куда рисовать блок найденного,
     *   object   show{..}         -- опции в AJAX-запрос "как рисовать элементы найденного" @see PHP-modelClass->show..(),
     *   object   search{..}       -- опции в AJAX-запрос "как искать данные"
     *   string   phpModel         -- короткое имя класса модели на сервере "что ищем" дляAJAX-запроса
     *   array    phpBy            -- набор для AJAX-запроса критериев поиска "где" (список полей и т.д. смотреть в php-модели)
     * }
     *
     * Имя(вызов) внешней функция onclick() - "выбран этот результат из найденного" может передаваться внутри опций .show для встраивания
     * в отрисовку найденного ещё на сервере. @see BaseModel::show()
     */
    init: function(src, searchSelector, options){
        this.count = 0;
        this.src = src;
        this.dstSelector = searchSelector;
        this.options = options;
        $(searchSelector).html('');
        if( options.min ){ this.minCount = options.min; }
        src.val('');
        $('BODY').on('click', ajaxSearch.end );
    },
    /** BODY.onclick(): завершение блока поиска по потере фокуса полем ввода
     * @param event Event
     * @param ajaxSearch object -- (этот) объект, дабы не содержать статические ссылки на себя в коде..
     */
    end: function(event){
        if ($(event.target).prop('id') == ajaxSearch.src.prop('id')) {
            // цель события совпадает с полем ввода - продолжаем ввод.
            return;
        } else if( $(event.target).closest(ajaxSearch.dstSelector).length != 0 ){
            // цель события внутри блока найденного
            return;
        } else {
            // вне поискового поля и вне блока найденного
            fvn.close(event, ajaxSearch.dstSelector);
            $('BODY').off('click');
        }
    },
    /** onclick() возвращаемый сервером для каждого найденного эл-та (заглушка) */
    defClick:function(obj){ alert('Выбран'); },

    /** onkeyup() на поле поиска - проверка и отправка введенной части
     */
    pressed : function(){
        var trEl = $(this.dstSelector);
        var searchObject = this;

        if( this.src.val().length < this.minCount ){
            trEl.html('');
            return;
        }
        $.post('/api/search', {
                model:   this.options.phpModel,
                by:      this.options.phpBy,
                val:     this.src.val(),
                options: {offset: this.options.offset, show: this.options.show, search: this.options.search}
            },
            /** прием результата поиска тут */
            function( res ){
                if( trEl.css('display') == 'none' ){
                    fvn.open(searchObject.dstSelector, searchObject.src, searchObject.options.offset);
                }
                if( res.error           ){ trEl.html(res.error); }
                else if( res.html != '' ){
//                    res.html =
//                        '<button type="button" class="btn1 btn-close" style="float:right;" onclick="$(\'' +
//                        searchObject.dstSelector+'\').hide();">закрыть</button>' +
//                        res.html
//                    ;
                    trEl.html(res.html);
                }
                else{
                    searchObject.onEmpty();
                }
            },
            'json'
        );
    },
    /** перехват, если ничего не найдено */
    onEmpty: function(){ $(this.dstSelector).html('<p style="color:red;">Ничего не найдено</p>'); }
};

