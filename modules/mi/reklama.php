<?php

$big = empty($params['big'])? 0 : (int)$params['big'];

$leftBar = $provider->getRegions();  // пока только округа для левой части:
$mainContent .= getContent('views/leftBar.phtml', ['data'=>$leftBar, 'big'=>$big]); // левый сайдбар со списком регионов, рекламы и т.д.

$regions = [];
if( $big>0 ){ $regions = $provider->getRegions('reg', $big); }
$mainContent .= getContent('views/reklama.phtml', ['myData'=>$myData, 'regions'=>$regions, 'cols'=>4, 'big'=>$big, 'reg'=>0]);

