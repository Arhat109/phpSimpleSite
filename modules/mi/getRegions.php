<?php
use utils\HtmlUtils;

/**
 * AJAX-JSON:: АПИ получения списка округов, регионов и городов размещения рекламной кампании
 */

$detail = empty($params['detail'])? 'big' : $params['detail'];
$fkey   = empty($params['fkey'])? 0 : (int)$params['fkey'];
switch($detail){
  case 'big' : $what = 'округ';  break;
  case 'reg' : $what = 'регион'; break;
  case 'city': $what = 'город';  break;
  default:     $what = '..?!?..';
}
$rows = $provider->getRegions($detail, $fkey);

$options = HtmlUtils::htmlOptions($rows, 'id', 'strName', 0, [0=>'выберите '.$what]);

$ajaxResult = ['ok'=>'успешно', 'html'=>$options];
$layout = 'ajax.phtml';
