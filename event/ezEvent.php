<?php
require 'ezEventLibEvent.php';
require 'ezEventSelect.php';

// 事件分发类
class ezEvent{

	const eventTime 		= 1;
	const eventRead 		= 2;
	const eventWrite 		= 4;
	const eventExcept 		= 2;
	const eventError 		= 3;
	const eventTimeOnce		= 4;
	const eventSignal       = 8;

	private $reactor = null;
	private $thirdEvents = null;

	public function __construct(){
		$this->init();
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
    public function onThirds($null1,$null2,$data){
        foreach (ezGLOBALS::$thirdEvents as $thirdEvent)
			$thirdEvent->loop();
        event_add($data[0],$data[1]*1000);
    }
}