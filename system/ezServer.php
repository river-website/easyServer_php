<?php

require 'com/ezGLOBALS.php';
require 'connect/ezTCP.php';
require 'event/ezEvent.php';
require 'protocol/ezHTTP.php';
require 'event/ezEventDB.php';
require 'event/ezEventQue.php';
require 'com/ezFunc.php';

class ezServer{

    const running           = 1;
    const waitExit          = 2;

    const normal			= 0;
    const exitAll			= 1;
    const reload			= 2;
    const smoothReload		= 4;

    private $data				= array();
    private $errorIgnorePaths	= array();
    private $os					= null;
    private $log                = true;
    private $runTimePath        = '/phpstudy/test/easyServer/runTime';
    private $logFile            = '/log/log-$date.log';
    private $processName	    = 'main process';
    private $pid                = 0;
    private $debug				= true;
    private $mainPid            = 0;
    private $multiProcess 		= true;
    private $processCount 		= 1;
    private $status             = ezServer::running;
    private $reactor		    = null;


    private $serverSocket 	= null;
	private $host 			= null;
	private $pids 			= array();

	public $protocol 		= null;
	public $onMessage 		= null;
	public $onStart 		= null;
	public $onStop		 	= null;
	public $onConnect 		= null;
	public $onClose 		= null;

	static public function getInterface(){
	    static $server;
	    if(empty($server))
	        $server = new ezServer();
	    return $server;
    }
    public function __construct(){
	    $this->mainPid = getmypid();
        $this->os = $this->getOS();
    }
    public function get($key){
        if(isset($this->data[$key])) {
            $value = $this->data[$key];
            if($value['time']>time())
                return $value['data'];
        }
    }
    public function set($key,$value,$time=315360000){
        $this->data[$key] = array('time'=>$time+time(),'data'=>$value);
    }
    public function addErrorIgnorePath($errno,$path){
        $this->errorIgnorePaths[$errno][$path] = true;
    }
    public function delErrorIgnorePath($errno,$path){
        if(isset($this->errorIgnorePaths[$errno][$path]))
            unset($this->errorIgnorePaths[$errno][$path]);
    }
    public function getErrorIgnorePath($errno,$paths){
        if(empty($this->errorIgnorePaths[$errno]))
            return false;
        foreach ($this->errorIgnorePaths[$errno] as $path=>$value){
            if(strstr($paths,$path) != false)
                return true;
        }
        return false;
    }
    public function log($msg){
        if($this->log){
            $time = time();
            $date = date('Y-m-d',$time);
            $time = date('h-M-s',$time);
            $file = $this->runTimePath.str_replace('$date',$date,$this->logFile);
            $pid = $this->pid;
            file_put_contents($file,$this->processName."[$pid] $time -> $msg\n",FILE_APPEND);
        }
    }
    public function debugLog($msg){
        if($this->debug){
            $this->log($msg);
        }
    }


    public function setHost($host = 'tcp://0.0.0.0:80'){
        $this->host = $host;
    }

	// 获取操作系统
	private function getOS(){
		// windows or linux
		if(strpos(PHP_OS,'WIN') !== FALSE)
			$os = 'Windows';
		else $os = 'Linux';
		return $os; 
	}
	private function createSocket(){
		$this->serverSocket = stream_socket_server($this->host);
		if (!$this->serverSocket) {
			echo "error -> create server socket fail!\n";
			exit();
		}
		stream_set_blocking($this->serverSocket, 0);
		ezDebugLog("server socket: " . $this->serverSocket);
	}
	private function back(){
		$pid  = pcntl_fork();
		if($pid > 0){
			ezDebugLog("init process exit");
			exit();
		}
		ezGLOBALS::$mainPid = getmypid();
	}
	public function start(){
        $this->back();
        $this->createSocket();
        if(!ezGLOBALS::$multiProcess){
            ezGLOBALS::$event = new ezEvent();
            ezGLOBALS::$event->add($this->serverSocket, ezEvent::eventRead, array($this, 'onAccept'));
            ezGLOBALS::$event->loop();
            ezDebugLog("main pid exit event loop");
            exit();
        }
		$this->forks();
		$this->monitorWorkers();
	}
	private function forks(){
	    if(is_file(ezGLOBALS::$runTimePath.'childPids'))
	        unlink(ezGLOBALS::$runTimePath.'childPids');
        for($i=0;$i<ezGLOBALS::$processCount;$i++){
            $this->forkOne();
        }
    }
    public function forkOne(){
        $pid = pcntl_fork();
        if($pid == 0) {
            ezGLOBALS::$processName = 'work process';
            $event = new ezEvent();
            $event->add($this->serverSocket, ezEvent::eventRead, array($this, 'onAccept'));
            if(ezGLOBALS::$checkStatusTime)
                $event->add(ezGLOBALS::$checkStatusTime, ezEvent::eventTime, array($this, 'checkProcessStatus'));
            $event->loop();
            ezDebugLog("child pid exit event loop");
            exit();
        }else{
            ezDebugLog("child pid: $pid");
            $this->pids[] = $pid;
            $this->addChildPid($pid);
        }
    }
    public function addChildPid($pid){
        $childPids = $this->getRunTimeData('childPids');
        if(empty($childPids) || count($childPids) == 0)
            $childPids = array((int)$pid=>$pid);
        else $childPids[(int)$pid] = $pid;
        $this->setRunTimeData($childPids,'childPids');
    }

    public function delChildPid($pid){
        $childPids = $this->getRunTimeData('childPids');
        if(empty($childPids) || count($childPids) == 0)
            return;
       if(empty($childPids[$pid]))return;
       unset($childPids[$pid]);
        $this->setRunTimeData($childPids,'childPids');
    }
	private function monitorWorkers(){
		ezDebugLog("start monitor workers");
		while(true){
            $pid    = pcntl_wait($status, WUNTRACED);
            ezServerLog("child process $pid exit");
            $this->checkProcessStatus();
        }
	}
	// 当收到连接时
	public function onAccept($socket){
		$new_socket = @stream_socket_accept($socket, 0, $remote_address);
		if (!$new_socket) 
			return;
		ezDebugLog("connect socket: ".$new_socket);
		ezDebugLog("remote address: ".$remote_address);
		stream_set_blocking($new_socket,0);

		$tcp = new ezTCP($new_socket,$remote_address);
		$tcp->setOnMessage($this->onMessage);
		ezGLOBALS::$event->add($new_socket, ezEvent::eventRead, array($tcp, 'onRead'));
	}
	public function delServerSocketEvent(){
		if(empty($this->serverSocket))return;
		if(empty(ezGLOBALS::$event))return;
		ezGLOBALS::$event->del($this->serverSocket,ezEvent::eventRead);
		ezGLOBALS::$event->del($this->serverSocket,ezEvent::eventWrite);
	}
	public function setRunTimeData($data,$file){
        file_put_contents(ezGLOBALS::$runTimePath.$file,serialize($data));
    }
    public function getRunTimeData($file){
	    if(is_file(ezGLOBALS::$runTimePath.$file))
            return unserialize(file_get_contents(ezGLOBALS::$runTimePath.$file));
    }
	// 检查状态
	public function checkProcessStatus(){
		include 'com/ezServerStatus.php';
		if(getmypid() == ezGLOBALS::$mainPid){
			if ($GLOBALS['ezServerStatus'] == ezServer::exitAll){
                $childPids = $this->getRunTimeData('childPids');
                if(empty($childPids) || count($childPids) == 0)exit();
                while(count($childPids)>0){
                    $live = array();
                    foreach ($childPids as $key=>$pid) {
                        if (posix_kill($pid, 0)) {
                            posix_kill($pid, SIGKILL);
                            $live[$key] = $pid;
                        }
                    }
                    $childPids = $live;
                }
                ezServerLog("all process exit");
                exit();
            }else if($GLOBALS['ezServerStatus'] == ezServer::reload){
                $childPids = $this->getRunTimeData('childPids');
                if(empty($childPids) || count($childPids) == 0){
                    $this->forks();
                    return;
                }
                while(count($childPids)>0) {
                    $live = array();
                    foreach ($childPids as $key => $pid) {
                        if (posix_kill($pid, 0)) {
                            posix_kill($pid, SIGKILL);
                            $live[$key] = $pid;
                        }
                    }
                    $childPids = $live;
                }
                $this->forks();
            }else if($GLOBALS['ezServerStatus'] == ezServer::smoothReload){
                $this->forkOne();
            }else if($GLOBALS['ezServerStatus'] == ezServer::normal) {
                $this->forkOne();
            }
		}else {
            if ($GLOBALS['ezServerStatus'] == ezServer::normal) return;
            if ($GLOBALS['ezServerStatus'] == ezServer::exitAll){
                ezGLOBALS::$server->delServerSocketEvent(getmypid());
                exit();
            }
			if ($GLOBALS['ezServerStatus'] == ezServer::reload){
				if (!empty(ezGLOBALS::$curConnect)) {
					ezGLOBALS::$server->delServerSocketEvent(getmypid());
					exit();
				}
            }
			if ($GLOBALS['ezServerStatus'] == ezServer::smoothReload) {
				if (!empty(ezGLOBALS::$curConnect)) {
					// 开始平滑重启
					// 释放相关资源
					ezGLOBALS::$server->delServerSocketEvent();
					if (!empty(ezGLOBALS::$thirdEvents)) {
						foreach (ezGLOBALS::$thirdEvents as $event) {
							if (!$event->isFree()) return;
						}
					}
					if (!ezGLOBALS::$event->isFree()) return;
                    ezGLOBALS::$server->delServerSocketEvent(getmypid());
					posix_kill(getmypid(), SIGKILL);
				}
			}
		}
	}
	public function onSiganl($type){

    }
}