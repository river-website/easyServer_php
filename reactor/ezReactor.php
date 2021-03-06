<?php
require 'ezReactorLibEvent.php';
require 'ezReactorSelect.php';

// 事件分发类
class ezReactor{

	const eventTime 		= 1;
	const eventRead 		= 2;
	const eventWrite 		= 4;
    const eventSignal	    = 8;
    const eventTimeOnce		= 16;
    const eventClock		= 32;
    const eventExcept 		= 64;

	private $reactor 		= null;

	public function __construct(){
		$this->init();
	}
	static public function getInterface(){
		static $reactor;
		if(empty($reactor)) {
			$reactor = new ezReactor();
		}
		return $reactor;
	}
	private function init(){
		if(extension_loaded('libevent')){
			$this->reactor = new ezReactorLibEvent();
		}else{
			$this->reactor = new ezReactorSelect();
		}
	}
	// 对外接口 增加一个监视资源，状态及事件处理
	public function add($fd, $status, $func,$arg = null){
		$this->reactor->add($fd, $status, $func,$arg);
	}
	// 对外接口 删除一个监视资源，状态及事件处理
	public function del($fd,$status){
		$this->reactor->del($fd,$status);
	}
	// 对外接口 开始监视资源
	public function loop(){
		$this->reactor->loop();
	}
}