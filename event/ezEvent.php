<?php

// 事件分发类
class ezEvent{

	const read = 2;
	const write = 4;
	const except = 2;
	const error = 3;
	const time = 1;

	private $allEvent = array();
	private $readEvent = array();
	private $writeEvent = array();
	private $exceptEvent = array();
	private $errorEvent = array();

	private $os = null;

	public $addFunc = null;
	public $delFunc = null;
	public $loopFunc = null;
	public $base = null;
	public $thirdEvents = array();

	public function __construct($os){
		$this->os = $os;
		$this->init();
	}
	private function init(){
		if(extension_loaded('libevent')){
            $this->base = event_base_new();
            $this->addFunc = 'libeventAdd';
            $this->delFunc = 'libeventDel';
            $this->loopFunc = 'libeventLoop';
			$this->libeventAdd(100,ezEvent::time,array($this,'libeventTime'));
		}else{
			$this->addFunc = 'selectAdd';
            $this->delFunc = 'selectDel';
            $this->loopFunc = 'selectLoop';
        }
	}
    // 对外接口 增加一个监视资源，状态及事件处理
	public function add($fd, $status, $func,$arg = null){
	    $funcName = $this->addFunc;
	    $this->$funcName($fd, $status, $func,$arg);
	}
    // 对外接口 删除一个监视资源，状态及事件处理
    public function del($fd,$status){
        $funcName = $this->delFunc;
        $this->$funcName($fd,$status);
    }
    // 对外接口 开始监视资源
    public function loop(){
        $funcName = $this->loopFunc;
        $this->$funcName();
    }

    // 增加一个监视资源，状态及事件处理
	private function libeventAdd($fd, $status, $func,$arg = null){
    	switch ($status){
			case ezEvent::time: {
				$event = event_new();
				if (!event_set($event, 0, EV_TIMEOUT, $func,array($event,$fd)))
					return false;
				if (!event_base_set($event, $this->base))
					return false;
				if (!event_add($event,$fd))
					return false;
				$this->allEvent[(int)$event][$status] = $event;
				echo "add event fd -> $fd; event -> $event\n";
				return (int)$event;
			}
				break;
			case ezEvent::read:
			case ezEvent::write: {
				$event = event_new();
				if (!event_set($event, $fd, $status | EV_PERSIST, $func, array($fd, $arg)))
					return false;
				if (!event_base_set($event, $this->base))
					return false;
				if (!event_add($event))
					return false;
				$this->allEvent[(int)$fd][$status] = $event;
				echo "add event fd -> $fd; event -> $event\n";
			}
				break;
		}
		return true;
	}
	// 删除一个监视资源，状态及事件处理
    private function libeventDel($fd,$status){
		if(!empty($this->allEvent[(int)$fd][$status])) {
			$ev = $this->allEvent[(int)$fd][$status];
			event_del($ev);
			unset($this->allEvent[(int)$fd][$status]);
		}
	}
    // 开始监视资源
	private function libeventLoop(){
		event_base_loop($this->base);
	}

	public function libeventTime($_null1, $_null2, $data){
		// 第三方循环事件，如db连接，查询
		foreach ($this->thirdEvents as $thirdEvent)
			$thirdEvent->loop();
		event_add($data[0],$data[1]);
	}

    // 增加一个监视资源，状态及事件处理
	private function selectAdd($fd, $status, $func,$arg = null){
	    $fd_key = (int)$fd;
		switch ($status) {
			case self::read:
				$this->allEvent[$fd_key][$status] = $func;
				$this->readEvent[$fd_key] = $fd;
				break;
			case self::write:
				$this->allEvent[$fd_key][$status] = $func;
				$this->writeEvent[$fd_key] = $fd;
				break;
			case self::except:
				$this->allEvent[$fd_key][$status] = $func;
				$this->exceptEvent[$fd_key] = $fd;
				break;
			case self::error:
				$this->allEvent[$fd_key][$status] = $func;
				$this->errorEvent[$fd_key] = $fd;
				break;
			default:
				break;
		}
	}
    // 删除一个监视资源，状态及事件处理
	private function selectDel($fd,$status){
	    $fd_key = (int)$fd;
		if(!empty($this->allEvent[$fd_key][$status]))
			unset($this->allEvent[$fd_key][$status]);
		switch ($status) {
			case self::read:
				if(!empty($this->readEvent[$fd_key]))
					unset($this->readEvent[$fd_key]);
				break;
			case self::write:
				if(!empty($this->writeEvent[$fd_key]))
					unset($this->writeEvent[$fd_key]);
				break;
			case self::except:
				if(!empty($this->exceptEvent[$fd_key]))
					unset($this->exceptEvent[$fd_key]);
				break;
			case self::error:
				if(!empty($this->errorEvent[$fd_key]))
					unset($this->errorEvent[$fd_key]);
				break;
			default:
				break;
		}
	}
    // 开始监视资源
	private function selectLoop(){
		while(true){
		    // 第三方循环事件，如db连接，查询
		    foreach ($this->thirdEvents as $thirdEvent)
		        $thirdEvent->loop();

			$read = $this->readEvent;
			$write = $this->writeEvent;
			$error = $this->errorEvent;
			$ret = @stream_select($read,$write, $error, 0, 0.1);
			if(!$ret) continue;
			foreach ($read as $fd) {
			    $fd_key = (int)$fd;
				$ev = $this->allEvent[$fd_key][self::read];
				call_user_func($ev,$fd);
			}
			foreach ($write as $fd) {
			    $fd_key = (int)$fd;
                $ev = $this->allEvent[$fd_key][self::write];
				call_user_func_array($ev, $fd);
			}
		}
	}
}