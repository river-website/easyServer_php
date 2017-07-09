<?php
require 'ezServer.php';
require 'ezWebServer.php';

$server = new ezWebServer('127.0.0.1:5555');
$server->setWeb('http://localhost','/');
$server->initWorker();