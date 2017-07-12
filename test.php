<?php
require 'ezServer.php';
require 'ezWebServer.php';

echo "start\n";

$server = new ezWebServer('39.108.148.255:80');
$server->setWeb('http://localhost','D:/phpStudy/WWW/easyPHP');
$server->start();