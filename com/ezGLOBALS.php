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
	static public $multiProcess 		= true;
	static public $processCount 		= 2;
	static public $maxAsyncLinks 		= 0;
	static public $event				= null;
	static public $thirdEventsTime		= 0;
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
	public function get($key){
		if(isset(self::$data[$key])) {
			$value = self::$data[$key];
			if($value['time']>time())
				return $value['data'];
		}
	}
	public function set($key,$value,$time=315360000){
		self::$data[$key] = array('time'=>$time+time(),'data'=>$value);
	}
}