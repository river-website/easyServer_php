<?php
/**
 * Created by PhpStorm.
 * User: win10
 * Date: 2017/7/26
 * Time: 9:55
 */
class ezGLOBALS{
	static public $curConnect			= null;

	static public $maxAsyncLinks 		= 0;
	static public $thirdEvents			= array();
	static public $dbEvent				= null;
	static public $dbEventTime			= 1;
	static public $queEvent             = null;
	static public $queEventTime         = 10;
	static public $checkStatusTime		= 1000;
	static public $dbConf				= array(
												'host' => '127.0.0.1',
												'user' => 'root',
												'password' => 'root',
												'dataBase' => 'yun',
												'port' => 3306
											);


}