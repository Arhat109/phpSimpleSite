<?php
use utils\HtmlUtils;

/**
 * AJAX-JSON:: АПИ получения списка рекламных кампаний(фирм, изданий и т.д.) по региону или городу и типу рекламы
 *
 * @global $params
 */

$idRegion = empty($params['idRegion'])? 0 : (int)$params['idRegion'];
$idCity   = empty($params['idCity'])?   0 : (int)$params['idCity'];
$advType  = empty($params['advType'])? '' : $params['advType'];

$rows = [];
switch($advType){
  case 'smi'    : $rows = array_merge($rows, $provider->getSmi($idRegion, $idCity)); break;
  case 'radio'  : $rows = array_merge($rows, $provider->getRadio($idRegion, $idCity)); break;
  case 'tv'     : $rows = array_merge($rows, $provider->getTV($idRegion, $idCity)); break;
  case 'bild'   : $rows = array_merge($rows, $provider->getOutdoor($idRegion, $idCity)); break;
  case 'cars'   : $rows = array_merge($rows, $provider->getTransport($idRegion, $idCity)); break;
  case 'metro'  : $rows = array_merge($rows, $provider->getMetro($idRegion, $idCity)); break;
  case 'btl'    : $rows = array_merge($rows, $provider->getPromo($idRegion, $idCity)); break;
  case 'inet'   : $rows = array_merge($rows, $provider->getInternet($idRegion, $idCity)); break;
  case 'lift'   : $rows = array_merge($rows, $provider->getLifts($idRegion, $idCity)); break;
  case 'poly'   : $rows = ['id'=>0, 'strName'=>'Увы, нет такого вида рекламы пока ещё..', 'strDescription'=>'']; break;
  case 'suvenir': $rows = ['id'=>0, 'strName'=>'Увы, нет такого вида рекламы пока ещё..', 'strDescription'=>'']; break;
  default:
    throw new Exception('getAdvert.php:: ERROR! Неизвестный вид рекламы .. научите меня ..', 500);
}
// формирование блока вывода в HTML
$html = getContent('views/advertList.phtml', ['rows'=>$rows]);

$ajaxResult = ['ok'=>'успешно', 'json'=>$rows, 'html'=>$html];
$layout = 'ajax.phtml';

