<?php
/**
 * Created by PhpStorm.
 * User: win10
 * Date: 2017/7/26
 * Time: 9:55
 */
class ezGLOBALS{
	static public $data 				= array();
	static public $server				= null;
	static public $os					= null;
	static public $curConnect			= null;
	static public $multiProcess 		= true;
	static public $processCount 		= 4;
	static public $maxAsyncLinks 		= 5;
	static public $event				= null;
	static public $thirdEventsTime		= 1;
	static public $thirdEvents			= array();
	static public $dbEvent				= null;
	static public $debug				= false;
	static public $dbConf				= array(
												'host' => '127.0.0.1',
												'user' => 'root',
												'password' => 'root',
												'dataBase' => 'test',
												'port' => 3306
											);

}