<?php

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

	public function __construct(){

	}

	public function add($fd, $status, $func,$arg = null){
		switch ($status) {
			case self::read:
				$this->allEvent[$fd][$status] = $func;
				$this->readEvent[$fd] = $fd;
				break;
			case self::write:
				$this->allEvent[$fd][$status] = $func;
				$this->writeEvent[$fd] = $fd;
				break;
			case self::except:
				$this->allEvent[$fd][$status] = $func;
				$this->exceptEvent[$fd] = $fd;
				break;
			case self::error:
				$this->allEvent[$fd][$status] = $func;
				$this->errorEvent[$fd] = $fd;
				break;
			default:
				break;
		}
	}

	public function del($fd,$status){
		if(!empty($this->allEvent[$fd][$status]))
			unset($this->allEvent[$fd][$status]);
		switch ($status) {
			case self::read:
				if(!empty($this->readEvent[$fd]))
					unset($this->readEvent[$fd]);
				break;
			case self::write:
				if(!empty($this->writeEvent[$fd]))
					unset($this->writeEvent[$fd]);
				break;
			case self::except:
				if(!empty($this->exceptEvent[$fd]))
					unset($this->exceptEvent[$fd]);
				break;
			case self::error:
				if(!empty($this->errorEvent[$fd]))
					unset($this->errorEvent[$fd]);
				break;
			default:
				break;
		}
	}

	public function loop(){
		while(true){
			$read = $this->readEvent;
			$write = $this->writeEvent;
			$error = $this->errorEvent;
			$ret = @stream_select($read,$write, $err, 0, 10);
			if(!$ret) continue;
			foreach ($read as $fd) {
				$ev = $this->allEvent[$fd][self::read];
				call_user_func($ev,$fd);
			}
			foreach ($write as $fd) {
				$ev = $this->allEvent[$fd][self::write];
				call_user_func_array($ev, $fd);
			}
		}
	}
}