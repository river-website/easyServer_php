<?php
require 'ezWebServer.php';

$server = new ezWebServer('0.0.0.0:88');
//$server->setWeb('http://localhost','D:/phpStudy/WWW/easyPHP');
$server->setWeb('http://localhost','/phpstudy/test/easyPHP');
$server->start();
