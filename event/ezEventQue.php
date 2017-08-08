<?php
/**
 * Created by PhpStorm.
 * User: lhe
 * Date: 2017/7/16
 * Time: 18:06
 */

class ezEventQue{
//	const queDBConFree  = 1;
//	const queDBConBusy  = 2;
//	const queDBSql 		= 3;
//	const queEventDone  = 4;
//
//	private $que = null;
//	private $curPid = null;
//	private $queIDList = array();
//	private $curQueID = 0;
//	private $queType = null;
//
//	public function __construct($perms = 0666){
//		$key = ftok(__FILE__,'R');
//		if(msg_queue_exists($key))msg_remove_queue(msg_get_queue($key,$perms));
//		$this->que = msg_get_queue($key,$perms);
//		$this->curPid = getmygid();
//		$this->queType = ezQue::queEventDone.$this->curPid;
//		ezGLOBALS::$queEvent = $this;
//	}
//
//	public function sendMsg($type,$data,$serialize = false,$func = null,$args = null,$block = true){
//		if($serialize) {
//			$msg['pid'] = $this->curPid;
//			$msg['queID'] = $this->curQueID++;
//			$msg['data'] = $data;
//		}else $msg = $data;
//		msg_send($this->que,$type,$msg,true, $block);
//		if(!empty($func))
//			$this->queIDList[$msg['queID']] = array($func,$args);
//	}
//
//	public function getMsg($type = 0, $wait = false){
//		if($wait == true)$flags = MSG_EAGAIN;
//		else $flags = MSG_IPC_NOWAIT;
//		msg_receive($this->que, $type, $message_type, 8192, $message, true, $flags);
//		return $message;
//	}
//
//	public function onQueEventDone(){
//		while(true){
//			$msg = $this->getMsg($this->queType,false);
//			if(empty($msg))return;
//			if(empty($msg['queID'])||empty($msg['data'])||empty($this->queIDList[$msg['queID']]))continue;
//			$info = $this->queIDList[$msg['queID']];
//			var_dump($info);
//			call_user_func_array($info[0],array($msg['data'],$info[1]));
//		}
//	}
	// 内存实现方式,每个进程处理自己的队列
    private $queList    = array();
    private $queID      = 0;
    public function __construct(){
        ezGLOBALS::$queEvent = $this;
		$this->freeStatus();
    }

    public function init(){
		if(ezGLOBALS::$queEventTime==0)return;
		ezGLOBALS::$event->add(ezGLOBALS::$queEventTime,ezEvent::eventTime, array($this,'loop'));
    }
    private function getStatus(){
        return true;
        if(is_file(__DIR__.'/queLockFile')){
            return false;
        }else{
            file_put_contents(__DIR__.'/queLockFile','que is lock!');
            if(is_file(__DIR__.'/queLockFile'))
                return true;
            else
                return false;
        }
    }
    private function freeStatus(){
    	if(is_file(__DIR__.'/queLockFile'))
        	unlink(__DIR__.'/queLockFile');
    }
    public function loop(){
//        ezDebugLog("que loop running");
        if($this->count() == 0)return;
        ezDebugLog("que list >0");
        if(!$this->getStatus())
            return;
        ezDebugLog("que loop com in");
        $pid = pcntl_fork();
        if($pid == 0) {
            ezGLOBALS::$processName = "ques process ";
//            ezGLOBALS::$dbEvent = new ezEventDB();
//            ezGLOBALS::$dbEvent->init();
//            ezGLOBALS::$event->del(ezGLOBALS::$server->serverSocket,ezEvent::eventRead);
            while(true){
                $que = $this->get();
                if (empty($que)) {
                    $this->freeStatus();
                    ezDebugLog("ques process exit\n");
                    ezGLOBALS::$server->delChildPid(getmypid());
                    posix_kill(getmypid(),SIGKILL);
//                    exit();
                };
                if (empty($que[1])) continue;
                try {
                    call_user_func_array($que[1], array($que[2]));
                } catch (Exception $ex) {
                    echo $ex;
                }
            }
        }else{
            $this->freeQueList();
            ezGLOBALS::$server->addChildPid($pid);
        }
    }
	public function add($func,$args=null){
        if(empty($func))return false;
        ezDebugLog("que add event");
        $this->queList[] = array($this->queID++,$func,$args);
        return true;
    }
    private function get(){
        return array_shift($this->queList);
    }
    private function count(){
        return count($this->queList);
    }
    private function freeQueList(){
        $this->queList = array();
    }
    public function isFree(){
    	if(count($this->queList) == 0)return true;
	}
}