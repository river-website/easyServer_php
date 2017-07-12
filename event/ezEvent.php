<?php

// 事件分发类
class ezEvent{

	const read = 0;
	const write = 1;
	const except = 2;
	const error = 3;

	private $allEvent = array();
	private $readEvent = array();
	private $writeEvent = array();
	private $exceptEvent = array();
	private $errorEvent = array();

	private $os = null;

	public $add = null;
	public $del = null;
	public $loop = null;
	public $thirdEvents = array();

	public function __construct($os){
        		$this->os = $os;
        		$this->init();
	}
	private function init(){
		if(extension_loaded('libevent')){
			$this->base = event_base_new();
			$this->add = $this->libeventAdd;
	    		$this->del = $this->libeventDel;
	    		$this->loop = $this->libeventLoop;
		}else{
			$this->add = array($this,'selectAdd');
	    		$this->del = $this->selectDel;
	    		$this->loop = $this->selectLoop;

	    	}
	}

	public function libeventAdd($fd, $status, $func,$arg = null){
		$event = event_new();
		event_set($event, $fd , $status, $func);
		event_base_set($event, $this->base);
		event_add($event);

	}
	public function libevenDel($fd,$status){

	}
	public function libeventLoop(){
		event_base_loop($this->base);
	}

    // 增加一个监视资源，状态及事件处理
	public function selectAdd($fd, $status, $func,$arg = null){
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
	public function selectDel($fd,$status){
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
	public function selectLoop(){
	    echo "start event loop\n";
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