<?php
/**
 * Контакты фирмы
 */

$contacts = $provider->getContacts();
$mainContent .= getContent('views/contacts.phtml', ['myData'=>$myData, 'contacts'=>$contacts]);
