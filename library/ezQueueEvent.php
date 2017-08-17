<?php
/**
 * Created by PhpStorm.
 * User: lhe
 * Date: 2017/7/16
 * Time: 18:06
 */

class ezQueueEvent{

    private $queList    			= array();
    private $queID      			= 0;
	private $queueEventTime        = 10;

	public function __construct(){
		$this->freeStatus();
    }
	static public function getInterface(){
		static $queueEvent;
		if(empty($queueEvent)) {
			$queueEvent = new ezQueueEvent();
		}
		return $queueEvent;
	}

    public function init(){
		if($this->queueEventTime==0)return;
		ezReactor::getInterface()->add($this->queueEventTime,ezReactor::eventTime, array($this,'loop'));
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
        if($this->count() == 0)return;
        ezServer::getInterface()->debugLog("que list >0");
        if(!$this->getStatus())
            return;
        ezServer::getInterface()->debugLog("que loop com in");
        $pid = pcntl_fork();
        if($pid == 0) {
			ezServer::getInterface()->processName = "ques process";
            ezDbPool::getInterface()->bakLinks();
            ezReactor::getInterface()->createSync();
            while(true){
                $que = $this->get();
                if (empty($que)) {
                    $this->freeStatus();
                    ezServer::getInterface()->debugLog("ques process exit\n");
					ezServer::getInterface()->delChildPid(getmypid());
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
			ezServer::getInterface()->addChildPid($pid);
        }
    }
	public function add($func,$args=null){
        if(empty($func))return false;
        ezServer::getInterface()->debugLog("que add event");
        $this->queList[] = array($this->queID++,$func,$args);
        return true;
    }
    public function back($func,$args= null){
	    if(empty($func))return false;
	    $pid = pcntl_fork();
	    if($pid == 0){
	        ezGLOBALS::$processName = 'back process';
			ezServer::getInterface()->log("start back task");
            ezGLOBALS::$dbEvent->bakLinks();
            ezGLOBALS::$dbEvent->createSync();
	        call_user_func_array($func,array($args));
			ezServer::getInterface()->log("back task exit");
            posix_kill(getmypid(),SIGKILL);
        }else{
			ezServer::getInterface()->addChildPid($pid);
        }
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