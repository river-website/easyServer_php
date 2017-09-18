<?php
require 'system/easy.php';
$easy = new easy();
$easy->setServer('ezWebServer');
$serverData['host'] = '0.0.0.0:81';
$serverData['serverRoot'] = array(
	array('webSite'=>'http://localhost','path'=>'/www/wwwroot/test/easyPHP')
);
$easy->setServerData($serverData);
$easy->start();