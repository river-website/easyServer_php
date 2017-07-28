<?php
require 'ezServer.php';
require 'ezWebServer.php';

echo "start\n";

$server = new ezWebServer('0.0.0.0:80');
//$server->setWeb('http://localhost','D:/phpStudy/WWW/easyPHP');
$server->setWeb('http://localhost','/phpstudy/www/easyPHP');
$server->start();


//$http = new Swoole\Http\Server("0.0.0.0", 80);
//$http->set(array(
//	'worker_num' => 1	//worker process num
//));
//$http->on('request', function ($request, $response) {
////	$conf = array(
////		'host' => '127.0.0.1',
////		'user' => 'root',
////		'password' => 'root',
////		'dataBase' => 'test',
////		'port' => 3306
////	);
////	$con = mysqli_connect($conf['host'], $conf['user'], $conf['password'], $conf['dataBase'], $conf['port']);
////	if (!$con)throw new Exception(mysqli_error());
////	$row = mysqli_query($con,"select * from ez_user");
//	$response->header("Content-Type", "text/html; charset=utf-8");
//	$response->end("<h1>Hello Swoole. #</h1>");
//});
//
//$http->start();