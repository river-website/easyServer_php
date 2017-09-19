<?php

if (!function_exists('ezQueue')) {
	function ezQueue(){
		return ezQueueEvent::getInterface();
	}
}
if (!function_exists('ezBack')) {
	function ezBack($func,$args= null){
		ezQueueEvent::getInterface()->back($func,$args);
	}
}
if (!function_exists('ezQueueAdd')) {
	function ezQueueAdd($func,$args= null){
		ezQueueEvent::getInterface()->add($func,$args);
	}
}

class ezQueueEvent{

    public $queueLockDir           = '/queueLok';
    public $queueLockFile          = '/queueLockFile-$pid';
    public $queueEventTime         = 10;

    private $queList    			= array();
    private $queID      			= 0;

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
		ezReactorAdd($this->queueEventTime,ezReactor::eventTime, array($this,'loop'));
    }
    private function getStatus(){
        if(is_file($this->queueLockFile)){
            return false;
        }else{
            file_put_contents($this->queueLockFile,'queue is lock!');
            if(is_file($this->queueLockFile))
                return true;
            else
                return false;
        }
    }
    private function freeStatus(){
        $this->queueLockDir = ezServer()->runTimePath.$this->queueLockDir;
        $this->queueLockFile = str_replace('$pid',getmypid(),$this->queueLockDir.$this->queueLockFile);
        if(!is_dir($this->queueLockDir))mkdir($this->queueLockDir);
    	if(is_file($this->queueLockFile))unlink($this->queueLockFile);
    }
    public function loop(){
        if($this->count() == 0)return;
        if(!$this->getStatus())return;
        ezDebugLog("queue loop com in");
        $pid = pcntl_fork();
        if($pid == 0) {
			easy::addPid('queue',getmypid());
			ezServer()->processName = "ques process";
			ezServer()->pid = getmypid();
			ezServer()->outScreen = false;
            ezDb()->bakLinks();
            ezDb()->createSync();
            while(true){
                $que = $this->get();
                if (empty($que)) {
                    $this->freeStatus();
                    ezDebugLog("ques process exit\n");
					ezServer()->delChildPid(getmypid());
                    posix_kill(getmypid(),SIGKILL);
                };
                if (empty($que[1])) continue;
                try {
                    call_user_func_array($que[1], array($que[2]));
                } catch (Exception $ex) {
                    ezLog($ex->getMessage());
                }
            }
        }else{
            ezServer()->pid -= count($this->queList);
            $this->freeQueList();
			ezServer()->addChildPid($pid);
        }
    }
	public function add($func,$args=null){
        if(empty($func))return false;
        ezServer::getInterface()->debugLog("que add event");
        $this->queList[] = array($this->queID++,$func,$args);
        ezServer::getInterface()->eventCount++;
        return true;
    }
    public function back($func,$args= null){
	    if(empty($func))return false;
	    $pid = pcntl_fork();
	    if($pid == 0){
	    	easy::addPid('back',getmypid());
	        ezServer()->processName = 'back process';
	        ezServer()->pid = getmypid();
	        ezServer()->outScreen = false;
			ezServer()->log("start back task");
            ezDb()->bakLinks();
            ezDb()->createSync();
	        call_user_func_array($func,array($args));
			ezLog("back task exit");
            posix_kill(getmypid(),SIGKILL);
        }else{
			ezServer()->addChildPid($pid);
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