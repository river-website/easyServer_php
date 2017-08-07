<?php
/**
 * Created by PhpStorm.
 * User: win10
 * Date: 2017/7/26
 * Time: 9:55
 */
class ezGLOBALS{
	static private $data				= array();
	static private $errorIgnorePaths	= array();
	static public $server				= null;
	static public $os					= null;
	static public $curConnect			= null;
	static public $multiProcess 		= true;									// 单核单进程，几核几进程
	static public $processCount 		= 1;									// 单核单进程，几核几进程
	static public $maxAsyncLinks 		= 0;
	static public $event				= null;
	static public $thirdEvents			= array();
	static public $dbEvent				= null;
	static public $dbEventTime			= 1;
	static public $queEvent           = null;
	static public $queEventTime       = 10;
	static public $checkStatusTime		= 100;
	static public $status             = ezServer::running;
	static public $processName			= 'main process';
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
	static public function addErrorIgnorePath($errno,$path){
		self::$errorIgnorePaths[$errno][$path] = true;
	}
	static public function delErrorIgnorePath($errno,$path){
		if(isset(self::$errorIgnorePaths[$errno][$path]))
			unset(self::$errorIgnorePaths[$errno][$path]);
	}
	static public function getErrorIgnorePath($errno,$paths){
		if(empty(self::$errorIgnorePaths[$errno]))
			return false;
		foreach (self::$errorIgnorePaths[$errno] as $path=>$value){
			if(strstr($paths,$path) != false)
				return true;
		}
		return false;
	}
}