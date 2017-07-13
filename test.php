<?php
require 'ezServer.php';
require 'ezWebServer.php';

echo "start\n";

$server = new ezWebServer('0.0.0.0:80');
//$server->setWeb('http://localhost','D:/phpStudy/WWW/easyPHP');
$server->setWeb('http://localhost','/www/wwwroot/easyPHP');
$server->start();

