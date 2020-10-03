<?php
/**
 * "наши партнеры"
 */

// @TODO: добавить пагинатор на страницу..
$partners = $provider->getPartners();
$mainContent .= getContent('views/partners.phtml', ['list'=>$partners]);