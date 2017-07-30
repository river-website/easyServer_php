<?php
/**
 * Created by PhpStorm.
 * User: win10
 * Date: 2017/7/26
 * Time: 9:55
 */
class ezGLOBALS{
	static private $data 				= array();
	static public $server				= null;
	static public $os					= null;
	static public $curConnect			= null;
	static public $multiProcess 		= true;								// 单核单进程，几核几进程
	static public $processCount 		= 1;									// 单核单进程，几核几进程
	static public $maxAsyncLinks 		= 0;
	static public $event				= null;
	static public $thirdEventsTime		= 1000;									// 0为没有异步sql池,
	static public $thirdEvents			= array();
	static public $dbEvent				= null;
	static public $queEvent             = null;
	static public $processName          = 'main process';
	static public $debug				= true;
	static public $dbConf				= array(
												'host' => '127.0.0.1',
												'user' => 'root',
												'password' => 'root',
												'dataBase' => 'yun',
												'port' => 3306
											);
	static public function get($key){
		if(isset(self::$data[$key])) {
			$value = self::$data[$key];
			if($value['time']>time())
				return $value['data'];
		}
	}
	static public function set($key,$value,$time=315360000){
		self::$data[$key] = array('time'=>$time+time(),'data'=>$value);
	}
}