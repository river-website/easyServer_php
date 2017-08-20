<?php
require 'ezWebServer.php';

$server = new ezWebServer('0.0.0.0:80');
//$server->setWeb('http://localhost','D:/phpStudy/WWW/easyPHP');
$server->setWeb('http://localhost','/phpstudy/www/easyPHP');
$server->start();