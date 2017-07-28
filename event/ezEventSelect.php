<?php
/**
 * Created by PhpStorm.
 * User: win10
 * Date: 2017/7/19
 * Time: 17:03
 */

class ezEventSelect{

	private $allEvent 		= array();
	private $readEvent 	= array();
	private $writeEvent 	= array();
	private $exceptEvent 	= array();

	// 增加一个监视资源，状态及事件处理
	public function add($fd, $status, $func, $arg = null){
		$fdKey = (int)$fd;
		switch ($status) {
			case ezEvent::eventRead:
				$this->allEvent[$fdKey][$status] = array($func,$arg);
				$this->readEvent[$fdKey] = $fd;
				break;
			case ezEvent::eventWrite:
				$this->allEvent[$fdKey][$status] = array($func,$arg);
				$this->writeEvent[$fdKey] = $fd;
				break;
			case ezEvent::eventExcept:
				$this->allEvent[$fdKey][$status] = array($func,$arg);
				$this->exceptEvent[$fdKey] = $fd;
				break;
			case ezEvent::eventSignal: {
				// Windows not support signal.
				if (DIRECTORY_SEPARATOR !== '/') {
					return false;
				}
//				$this->allEvent[(int)$fd][$status] = array($func, $fd);
				pcntl_signal($fd, $func);
			}
				break;
			default:
				break;
		}
	}
	// 删除一个监视资源，状态及事件处理
	public function del($fd, $status){
		$fd_key = (int)$fd;
		if(!empty($this->allEvent[$fd_key][$status]))
			unset($this->allEvent[$fd_key][$status]);
		switch ($status) {
			case ezEvent::eventRead:
				if(!empty($this->readEvent[$fd_key]))
					unset($this->readEvent[$fd_key]);
				break;
			case ezEvent::eventWrite:
				if(!empty($this->writeEvent[$fd_key]))
					unset($this->writeEvent[$fd_key]);
				break;
			case ezEvent::eventExcept:
				if(!empty($this->exceptEvent[$fd_key]))
					unset($this->exceptEvent[$fd_key]);
				break;
			case ezEvent::eventSignal:
				if(DIRECTORY_SEPARATOR !== '/') {
					return false;
				}
//				pcntl_signal($fd, SIG_IGN);
				break;
			default:
				break;
		}
	}
	// 开始监视资源
	public function loop($thirdEvents = null, $time = 0){
		while(true){
			if(count($thirdEvents)>0) {
				// 单进程中，第三方循环事件，如db连接，查询
				foreach ($thirdEvents as $thirdEvent)
					$thirdEvent->loop();
			}
			if(DIRECTORY_SEPARATOR === '/') {
				// Calls signal handlers for pending signals
//				pcntl_signal_dispatch();
			}
			$read = $this->readEvent;
			$write = $this->writeEvent;
			$except = $this->exceptEvent;
			$ret = @stream_select($read, $write, $except, $time);
			if(!$ret) continue;
			foreach ($read as $fd) {
				$fd_key = (int)$fd;
				$ev = $this->allEvent[$fd_key][ezEvent::eventRead];
				call_user_func_array($ev[0],array($fd,$ev[1]));
			}
			foreach ($write as $fd) {
				$fd_key = (int)$fd;
				$ev = $this->allEvent[$fd_key][ezEvent::eventWrite];
				call_user_func_array($ev[0],array($fd,$ev[1]));
			}
			foreach ($except as $fd) {
				$fd_key = (int)$fd;
				$ev = $this->allEvent[$fd_key][ezEvent::eventExcept];
				call_user_func_array($ev[0],array($fd,$ev[1]));
			}
		}
	}

}