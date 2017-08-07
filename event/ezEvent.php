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

	private $reactor 		= null;
	private $thirdEvents 	= null;
	private $userEvent		= -1;

	public function __construct(){
		ezGLOBALS::$event = $this;
		$this->init();
	}
	public function isFree(){
	    return ($this->userEvent>0)?false:true;
    }
	private function init(){
		if(extension_loaded('libevent')){
			$this->reactor = new ezEventLibEvent();
		}else{
			$this->reactor = new ezEventSelect();
		}
		foreach (ezGLOBALS::$thirdEvents as $thirdEvent)
			$thirdEvent->init();
	}
	// 对外接口 增加一个监视资源，状态及事件处理
	public function add($fd, $status, $func,$arg = null){
		$this->reactor->add($fd, $status, $func,$arg);
		if($this->userEvent>=0)$this->userEvent++;
	}
	// 对外接口 删除一个监视资源，状态及事件处理
	public function del($fd,$status){
		$this->reactor->del($fd,$status);
		if($this->userEvent>0)$this->userEvent--;
	}
	// 对外接口 开始监视资源
	public function loop(){
		$this->userEvent = 0;
		$this->reactor->loop();
	}
}