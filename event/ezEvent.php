<?php
require 'ezEventLibEvent.php';
require 'ezEventSelect.php';

// 事件分发类
class ezEvent{

	const eventTime 		= 1;
	const eventRead 		= 2;
	const eventWrite 		= 4;
    const eventSignal	    = 8;
    const eventTimeOnce		= 16;
    const eventClock		= 32;
    const eventExcept 		= 64;

	private $reactor = null;
	private $thirdEvents = null;

	public function __construct(){
		$this->init();
	}
	public function isFree(){
	    return $this->reactor->isFree();
    }
	private function init(){
		if(extension_loaded('libevent')){
			$this->reactor = new ezEventLibEvent();
		}else{
			$this->reactor = new ezEventSelect();
		}
		foreach (ezGLOBALS::$thirdEvents as $thirdEvent)
			$thirdEvent->init();
		if(ezGLOBALS::$thirdEventsTime)
			$this->add(ezGLOBALS::$thirdEventsTime,ezEvent::eventTime, array($this,'onThirds'));
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
	public function onThirds(){
		foreach (ezGLOBALS::$thirdEvents as $thirdEvent)
			$thirdEvent->loop();
	}
}