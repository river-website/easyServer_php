<?php
date_default_timezone_set("PRC");
require dirname(__FILE__).'/../connect/ezTcp.php';
require dirname(__FILE__).'/../connect/ezUdp.php';
require dirname(__FILE__).'/../reactor/ezReactor.php';

class ezServer{
    const normal			= 0;
    const exitAll			= 1;
    const reload			= 2;
    const smoothReload		= 4;

    // 配置
	public $log               	= true;
	public $runTimePath       	= '/phpstudy/test/easyServer/runTime';
	public $logFile           	= '/log/log-$date.log';
	public $debug				= true;
	public $processCount 		= 1;
	public $checkStatusTime		= 1000;
	public $host 				= 'tcp://0.0.0.0:80';
	public $pidsFile			= '/pids/pidsFile';

	// 回调
	public $onMessage 			= null;
	public $onStart 			= null;
	public $onStop		 		= null;
	public $onConnect 			= null;
	public $onClose 			= null;

	public $protocol 			= null;
	public $curConnect			= null;
	private $data				= array();
    private $errorIgnorePaths	= array();
    private $os				= null;
    public $processName	    = 'main process';
    private $pid               = 0;
    private $mainPid           = 0;
    private $serverSocket 		= null;
	private $eventCount		= 0;

	static public function getInterface(){
	    static $server;
	    if(empty($server)) {
	    	self::back();
			$server = new ezServer();
		}
	    return $server;
    }
	static private function back(){
		$pid  = pcntl_fork();
		if($pid > 0)exit();
	}
    public function __construct(){
		$this->mainPid = getmypid();
	    $this->pid = getmypid();
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
    public function checkErrorIgnorePath($errno,$paths){
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
			echo "\nerror -> create server socket fail!\n";
			exit();
		}
		stream_set_blocking($this->serverSocket, 0);
		$this->log("server socket: " . $this->serverSocket);
	}
	public function init(){
		set_error_handler(array($this,'errorHandle'));
		$this->dirCreate();
		$this->createSocket();
		$this->forks();
		$this->monitorWorkers();
	}
	private function dirCreate(){
		if(is_dir($this->runTimePath))
			return;
	}
	public function loop(){
		if($this->processCount==0 || $this->pid != $this->mainPid){
			ezReactor::getInterface()->loop();
			$this->log("work process exit reactor loop");
			exit();
		}
	}
	private function forks(){
	    if(is_file($this->runTimePath.$this->pidsFile))
	        unlink($this->runTimePath.$this->pidsFile);
	    if($this->processCount == 0)$this->reactor();
        for($i=0;$i<$this->processCount;$i++) {
        	if($this->pid != $this->mainPid)return;
			$this->forkOne();
		}
    }
    private function forkOne(){
        $pid = pcntl_fork();
        if($pid == 0) {
            $this->processName = 'work process';
            $this->pid = getmypid();
			$this->reactor();
        }else{
			$this->log("work pid: $pid");
            $this->addChildPid($pid);
        }
    }
    private function reactor(){
		ezReactor::getInterface()->add($this->serverSocket, ezReactor::eventRead, array($this, 'onAccept'));
		if($this->checkStatusTime)
			ezReactor::getInterface()->add($this->checkStatusTime, ezReactor::eventTime, array($this, 'checkProcessStatus'));
	}
    public function addChildPid($pid){
        $childPids = $this->getRunTimeData($this->pidsFile);
        if(empty($childPids) || count($childPids) == 0)
            $childPids = array((int)$pid=>$pid);
        else $childPids[(int)$pid] = $pid;
        $this->setRunTimeData($childPids,$this->pidsFile);
    }
    public function delChildPid($pid){
        $childPids = $this->getRunTimeData($this->pidsFile);
       if(!isset($childPids[$pid]))return;
       unset($childPids[$pid]);
        $this->setRunTimeData($childPids,$this->pidsFile);
    }
	private function monitorWorkers(){
		if($this->processCount==0 || $this->pid != $this->mainPid)
			return;
		$this->log("start monitor workers");
		while(true){
            $pid = pcntl_wait($status, WUNTRACED);
            $this->log("work process $pid exit");
            $this->checkProcessStatus();
        }
        exit();
	}
	private function getServerStatus(){
		include 'ezServerStatus.php';
		return $GLOBALS['ezServerStatus'];
	}
	// 当收到连接时
	public function onAccept($socket){
		$new_socket = stream_socket_accept($socket, 0, $remote_address);
		if (!$new_socket) 
			return;
		$this->debugLog("connect socket: ".$new_socket);
		$this->log("remote address: ".$remote_address);
		stream_set_blocking($new_socket,0);

		$tcp = new ezTcp($new_socket,$remote_address);
		$tcp->onMessage = $this->onMessage;
		$this->reactor->add($new_socket, ezEvent::eventRead, array($tcp, 'onRead'));
	}
	public function delServerSocketEvent(){
		if(empty($this->serverSocket))return;
		if(empty($this->reactor))return;
		$this->reactor->del($this->serverSocket,ezReactor::eventRead);
		$this->reactor->del($this->serverSocket,ezReactor::eventWrite);
	}
	public function setRunTimeData($data,$file){
        file_put_contents($this->runTimePath.$file,serialize($data));
    }
    public function getRunTimeData($file){
	    if(is_file($this->runTimePath.$file))
            return unserialize(file_get_contents($this->runTimePath.$file));
    }
	// 检查状态
	public function checkProcessStatus(){
		$status = $this->getServerStatus();
		if($this->pid == $this->mainPid){
			if ($status == ezServer::exitAll){
                $childPids = $this->getRunTimeData($this->pidsFile);
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
                $this->log("all process exit");
                exit();
            }else if($status == ezServer::reload){
                $childPids = $this->getRunTimeData($this->pidsFile);
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
            }else if($status == ezServer::smoothReload){
                $this->forkOne();
            }else if($status == ezServer::normal) {
                $this->forkOne();
            }
		}else {
            if ($status == ezServer::normal) return;
            if ($status == ezServer::exitAll){
               	$this->delServerSocketEvent();
                exit();
            }
			if ($status == ezServer::reload){
				if (!empty($this->curConnect)) {
					$this->delServerSocketEvent();
					exit();
				}
            }
			if ($status == ezServer::smoothReload) {
				if (!empty($this->curConnect)) {
					// 开始平滑重启
					$this->delServerSocketEvent();
					if($this->eventCount>0)return;
					exit();
				}
			}
		}
	}
	public function errorHandle($errno, $errstr, $errfile, $errline){
		if($this->checkErrorIgnorePath(E_NOTICE,$errfile))return;
		$msg = '';
		switch ($errno){
			case E_ERROR:{
				$msg = "easy E_ERROR -> $errstr ; file -> $errfile ; errline -> $errline ; ";
			}
				break;
			case E_WARNING:{
				$msg =  "easy E_WARNING -> $errstr ; file -> $errfile ; errline -> $errline ; ";
			}
				break;
			case E_PARSE:{
				$msg =  "easy E_PARSE -> $errstr ; file -> $errfile ; errline -> $errline ; ";
			}
				break;
			case E_NOTICE:{
				$msg =  "easy E_NOTICE -> $errstr ; file -> $errfile ; errline -> $errline ; ";
			}
				break;
			case E_CORE_ERROR:{
				$msg =  "easy E_CORE_ERROR -> $errstr ; file -> $errfile ; errline -> $errline ; ";
			}
				break;
			case E_CORE_WARNING:{
				$msg =  "easy E_CORE_WARNING -> $errstr ; file -> $errfile ; errline -> $errline ; ";
			}
				break;
			case E_COMPILE_ERROR:{
				$msg =  "easy E_COMPILE_ERROR -> $errstr ; file -> $errfile ; errline -> $errline ; ";
			}
				break;
			case E_COMPILE_WARNING:{
				$msg =  "easy E_COMPILE_WARNING -> $errstr ; file -> $errfile ; errline -> $errline ; ";
			}
				break;
			case E_USER_ERROR:{
				$msg =  "easy E_USER_ERROR -> $errstr ; file -> $errfile ; errline -> $errline ; ";
			}
				break;
			case E_USER_WARNING:{
				$msg =  "easy E_USER_WARNING -> $errstr ; file -> $errfile ; errline -> $errline ; ";
			}
				break;
			case E_USER_NOTICE:{
				$msg =  "easy E_USER_NOTICE -> $errstr ; file -> $errfile ; errline -> $errline ; ";
			}
				break;
			case E_STRICT:{
				$msg =  "easy E_STRICT -> $errstr ; file -> $errfile ; errline -> $errline ; ";
			}
				break;
			case E_RECOVERABLE_ERROR:{
				$msg =  "easy E_RECOVERABLE_ERROR -> $errstr ; file -> $errfile ; errline -> $errline ; ";
			}
				break;
			case E_DEPRECATED:{
				$msg =  "easy E_DEPRECATED -> $errstr ; file -> $errfile ; errline -> $errline ; ";
			}
				break;
			case E_USER_DEPRECATED:{
				$msg =  "easy E_USER_DEPRECATED -> $errstr ; file -> $errfile ; errline -> $errline ; ";
			}
				break;
			case E_ALL:{
				$msg =  "easy E_ALL -> $errstr ; file -> $errfile ; errline -> $errline ; ";
			}
				break;
		}
		$this->log($msg);
	}
}