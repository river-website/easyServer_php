<?php

require 'com/ezGLOBALS.php';
require 'connect/ezTCP.php';
require 'event/ezEvent.php';
require 'protocol/ezHTTP.php';
require 'event/ezEventDB.php';
require 'event/ezEventQue.php';
require 'com/ezFunc.php';

class ezServer{

	private $serverSocket 	= null;
	private $host 			= null;
	private $pids 			= array();

	public $protocol 		= null;
	public $onMessage 		= null;
	public $onStart 		= null;
	public $onStop		 	= null;
	public $onConnect 		= null;
	public $onClose 		= null;

	public function __construct($host){
		$this->host = $host;
		ezGLOBALS::$os = $this->getOS();
		ezGLOBALS::$server = $this;
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
		echoDebug("server socket: " . $this->serverSocket);
	}
	public function start(){
        $this->createSocket();
        if(!ezGLOBALS::$multiProcess){
            ezGLOBALS::$event = new ezEvent();
            ezGLOBALS::$event->add($this->serverSocket, ezEvent::eventRead, array($this, 'onAccept'));
            ezGLOBALS::$event->loop();
            echoDebug("main pid exit event loop");
            exit();
        }
		$this->forks();
		$this->monitorWorkers();
	}
	private function forks(){
        for($i=0;$i<ezGLOBALS::$processCount;$i++){
            $pid = pcntl_fork();
            if($pid == 0) {
                ezGLOBALS::$processName = 'work process';
                ezGLOBALS::$event = new ezEvent();
                ezGLOBALS::$event->add($this->serverSocket, ezEvent::eventRead, array($this, 'onAccept'));
                ezGLOBALS::$event->loop();
                echoDebug("child pid exit event loop");
            }else{
                echoDebug("child pid: $pid");
                $this->pids[] = $pid;
            }
        }
    }

	private function monitorWorkers(){
		echoDebug("start monitor workers");
		$oldPids = array();

		while(true){
            $pid    = pcntl_wait($status, WUNTRACED);
            if(!empty($oldPids[(int)$pid])) {
                unset($oldPids[(int)$pid]);
                continue;
            }
            $oldPids = array();
            foreach ($this->pids as $pid) {
                posix_kill($pid, SIGKILL);
                $oldPids[(int)$pid] = $pid;
            }
            $this->pids = array();
            $this->forks();
        }
	}
	// 当收到连接时
	public function onAccept($socket){
		$new_socket = @stream_socket_accept($socket, 0, $remote_address);
		if (!$new_socket) 
			return;
		echoDebug("connect socket: ".$new_socket);
		echoDebug("remote address: ".$remote_address);
		stream_set_blocking($new_socket,0);

		$tcp = new ezTCP($new_socket,$remote_address);
		$tcp->setOnMessage($this->onMessage);
		ezGLOBALS::$event->add($new_socket, ezEvent::eventRead, array($tcp, 'onRead'));
	}
}