<?php
date_default_timezone_set("PRC");
if(!defined('ROOT'))define('ROOT', __DIR__.'/..');
require ROOT.'/connect/ezTcp.php';
require ROOT.'/reactor/ezReactor.php';

class ezServer{
    const normal			= 0;
    const exitAll			= 1;
    const reload			= 2;
    const smoothReload		= 4;

    // 配置
	public $log               	= true;
    public $runTimePath       	= '/runTime';
    public $logDir       	    = '/log';
	public $logFile           	= '/log-$date.log';
    public $pidsDir			    = '/pids';
    public $pidsFile			= '/pidsFile';
    public $debug				= true;
	public $processCount 		= 1;
	public $checkStatusTime		= 1000;
	public $host 				= 'tcp://0.0.0.0:80';

	// 回调
	public $onMessage 			= null;
	public $onStart 			= null;
	public $onStop		 		= null;
	public $onConnect 			= null;
	public $onClose 			= null;

	public $protocol 			= null;
	public $curConnect			= null;
    public $pid               	= 0;
    private $data				= array();
    private $errorIgnorePaths	= array();
    private $os				= null;
    public $processName	    	= 'main process';
    private $mainPid           = 0;
    private $serverSocket 		= null;
	public $eventCount			= 0;
	public $outScreen			= false;
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
    public function set($key,$value,$time=0){
        $this->data[$key] = array('time'=>$time==0?strtotime('21000000'):$time+time(),'data'=>$value);
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
            $time = date('h:i:s',$time);
            $file = str_replace('$date',$date,$this->logFile);
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
	public function start(){
		set_error_handler(array($this,'errorHandle'));
		$this->initDir();
		$this->createSocket();
		$this->forks();
		$this->monitorWorkers();
	}
	private function initDir(){
	    $this->runTimePath = ROOT.$this->runTimePath;
		if(!is_dir($this->runTimePath))
			mkdir($this->runTimePath);
		$this->logDir = $this->runTimePath.$this->logDir;
		if(!is_dir($this->logDir))
            mkdir($this->logDir);
        $this->pidsDir = $this->runTimePath.$this->pidsDir;
        if(!is_dir($this->pidsDir))
            mkdir($this->pidsDir);
        $this->logFile = $this->logDir.$this->logFile;
        $this->pidsFile = $this->pidsDir.$this->pidsFile;
	}

	private function forks(){
	    if(is_file($this->pidsFile))
	        unlink($this->pidsFile);
	    if($this->processCount == 0)$this->reactor();
        for($i=0;$i<$this->processCount;$i++) {
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
        if(!empty($this->onStart))
            call_user_func($this->onStart);
		ezReactor::getInterface()->add($this->serverSocket, ezReactor::eventRead, array($this, 'onAccept'));
		if($this->checkStatusTime)
			ezReactor::getInterface()->add($this->checkStatusTime, ezReactor::eventTime, array($this, 'checkProcessStatus'));
        ezReactor::getInterface()->loop();
        $this->log("work process exit reactor loop");
        exit();
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
		$this->log("start monitor workers");
		while(true){
            $pid = pcntl_wait($status, WNOHANG );
			if($pid>0) {
				$this->log("work process $pid exit");
				$this->checkProcessStatus($pid);
			}else if($pid==0){
                $this->checkProcessStatus();
            }else{
                $this->log(" pcntl_wait error");
            }
            $time =$this->checkStatusTime/1000;
            sleep($time);
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
		ezReactor::getInterface()->add($new_socket, ezReactor::eventRead, array($tcp, 'onRead'));
	}
	public function delServerSocketEvent(){
		if(empty($this->serverSocket))return;
		if(empty($this->reactor))return;
		$this->reactor->del($this->serverSocket,ezReactor::eventRead);
		$this->reactor->del($this->serverSocket,ezReactor::eventWrite);
	}
	public function setRunTimeData($data,$file){
        file_put_contents($file,serialize($data));
    }
    public function getRunTimeData($file){
	    if(is_file($file))
            return unserialize(file_get_contents($file));
    }
	// 检查状态
	public function checkProcessStatus($exitPid = 0){
		$status = $this->getServerStatus();
		if($this->pid == $this->mainPid){
			if ($status == ezServer::exitAll){
                $childPids = $this->getRunTimeData($this->pidsFile);
                if(empty($childPids) || count($childPids) == 0)exit();
                while(count($childPids)>0){
                    $live = array();
                    foreach ($childPids as $key=>$pid) {
                        if (posix_kill($pid, 0)) {
                            $ret = pcntl_waitpid($pid,$status,WNOHANG);
                            if($ret<=0) {
                                $this->log("will kill $pid");
                                posix_kill($pid, SIGKILL);
                                $live[$key] = $pid;
                                continue;
                            }
                        }
                        $this->log("$pid has exit");
                    }
                    $childPids = $live;
                    $time = $this->checkStatusTime/1000;
                    sleep($time);
                }
                $this->log("all process exit");
                exit();
            }else if($status == ezServer::reload){
                $childPids = $this->getRunTimeData($this->pidsFile);
                if(empty($childPids) || count($childPids) == 0){
                    $this->forks();
                    return;
                }
                while(count($childPids)>0){
                    $live = array();
                    foreach ($childPids as $key=>$pid) {
                        if (posix_kill($pid, 0)) {
                            $ret = pcntl_waitpid($pid,$status,WNOHANG);
                            if($ret<=0) {
                                $this->log("will kill $pid");
                                posix_kill($pid, SIGKILL);
                                $live[$key] = $pid;
                                continue;
                            }
                        }
                        $this->log("$pid has exit");
                    }
                    $childPids = $live;
                    $time = $this->checkStatusTime/1000;
                    sleep($time);
                }
                $this->forks();
            }else if($status == ezServer::smoothReload && $exitPid>0){
                $this->forkOne();
            }else if($status == ezServer::normal && $exitPid>0) {
                $this->forkOne();
            }
		}else {
            if ($status == ezServer::normal) return;
            if ($status == ezServer::exitAll){
               	$this->delServerSocketEvent();
               	$this->log('exit');
                exit();
            }
			if ($status == ezServer::reload){
				if (!empty($this->curConnect)) {
					$this->delServerSocketEvent();
                    $this->log('exit');
                    exit();
				}
            }
			if ($status == ezServer::smoothReload) {
				if (!empty($this->curConnect)) {
					// 开始平滑重启
					$this->delServerSocketEvent();
					if($this->eventCount>0)return;
					$this->log('work process exit');
					exit();
				}
			}
		}
	}
	public function errorHandle($errno, $errstr, $errfile, $errline){
		if($this->checkErrorIgnorePath($errno,$errfile))return;
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
		if($this->outScreen)
			echo "$msg<br>";
		else
			$this->log($msg);
	}
}