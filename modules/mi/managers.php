<?php
/**
 * список сотрудников и все что с ними связано
 */

$managers = $provider->getManagers();
$mainContent .= getContent('views/managers.phtml', ['myData'=>$myData, 'managers'=>$managers]);