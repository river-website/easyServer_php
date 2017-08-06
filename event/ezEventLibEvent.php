<?php
/**
 * Created by PhpStorm.
 * User: win10
 * Date: 2017/7/20
 * Time: 10:04
 */
class ezEventLibEvent{

	private $base 			= null;
	private $allEvent 		= array();

	public function __construct(){
		$this->base = event_base_new();
	}
	// 增加一个监视资源，状态及事件处理
	public function add($fd, $status, $func, $arg = null){
		switch ($status){
            case ezEvent::eventTimeOnce:
            case ezEvent::eventTime:
            case ezEvent::eventClock:{
                if(ezEvent::eventClock == $status) {
                    // $fd 如 03:15:30,即每天3:15:30执行
                    $time = strtotime($fd);
                    $now = time();
                    if ($now >= $time)
                        $time = strtotime('+1 day', $time);
                    $time = ($time - $now) * 1000;
                }else{
                    $time = $fd * 1000;
                }
                echoDebug("add time event,time out is: $fd");
				$event = event_new();
				if (!event_set($event, 0, EV_TIMEOUT,array($this,'onTime'), array($event ,$fd,$status,$func,$arg)))
					return false;
				if (!event_base_set($event, $this->base))
					return false;
				if (!event_add($event, $time))
					return false;
				$this->allEvent[(int)$event][$status] = $event;
				return (int)$event;
			}
				break;
			case ezEvent::eventSignal: {
				$event = event_new();
				if (!event_set($event, $fd, $status | EV_PERSIST, $func, null)) {
					return false;
				}
				if (!event_base_set($event, $this->base)) {
					return false;
				}
				if (!event_add($event)) {
					return false;
				}
				$this->allEvent[(int)$fd][$status] = $event;
			}
				break;
			case ezEvent::eventRead:
			case ezEvent::eventWrite: {
				$event = event_new();
				if (!event_set($event, $fd, $status | EV_PERSIST, $func, array($fd, $arg)))
					return false;
				if (!event_base_set($event, $this->base))
					return false;
				if (!event_add($event))
					return false;
				$this->allEvent[(int)$fd][$status] = $event;
			}
				break;
			default:
				break;
		}
		return true;
	}
	public function isFree(){
	    if(count($this->allEvent) == 0)return true;
    }
	// 删除一个监视资源，状态及事件处理
	public function del($fd, $status){
		if(!empty($this->allEvent[(int)$fd][$status])) {
			$ev = $this->allEvent[(int)$fd][$status];
			event_del($ev);
			unset($this->allEvent[(int)$fd][$status]);
		}
	}
	// 开始监视资源
	public function loop(){
		event_base_loop($this->base);
	}
	public function onTime($null1,$null2,$data){
        if(count($data) != 5)return;
        $event  = $data[0];
        $fd     = $data[1];
        $status = $data[2];
        $func   = $data[3];
        $arg    = $data[4];
        if($status != ezEvent::eventTimeOnce) {
            if ($status == ezEvent::eventClock) {
                // $fd 如 03:15:30,即每天3:15:30执行
                $time = strtotime($fd);
                $now = time();
                $time = strtotime('+1 day', $time);
                $time = ($time - $now) * 1000;
            } else {
                $time = $fd * 1000;
            }
            event_add($event,$time);
        }
        try{
           call_user_func($func,$arg);
        }catch (Exception $ex){
            echo $ex->getMessage()."\n";
        }
    }
}