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
			case ezEvent::eventTime: {
				$event = event_new();
				if (!event_set($event, 0, $status, $func, array($event ,$fd, $arg)))
					return false;
				if (!event_base_set($event, $this->base))
					return false;
				if (!event_add($event, $fd*1000))
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
	// 删除一个监视资源，状态及事件处理
	public function del($fd, $status){
		if(!empty($this->allEvent[(int)$fd][$status])) {
			$ev = $this->allEvent[(int)$fd][$status];
			event_del($ev);
			unset($this->allEvent[(int)$fd][$status]);
		}
	}
	// 开始监视资源
	public function loop($thirdEvents = null, $time = 0.2){
		while(true){
			if(count($thirdEvents)>0) {
				// 单进程中，第三方循环事件，如db连接，查询
				foreach ($thirdEvents as $thirdEvent)
					$thirdEvent->loop();
				event_base_loop($this->base,EVLOOP_NONBLOCK);
			}else
			event_base_loop($this->base);
		}
	}
}