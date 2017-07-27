<?php

require 'com/ezGLOBALS.php';
require 'connect/ezTCP.php';
require 'event/ezEvent.php';
require 'protocol/ezHTTP.php';
require 'event/ezEventDB.php';
require 'com/ezQue.php';
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
		echo "server socket -> " . $this->serverSocket . "\n";
	}
	public function start(){
        $this->createSocket();
        if(!ezGLOBALS::$multiProcess){
			ezGLOBALS::$event = new ezEvent();
			ezGLOBALS::$event->add($this->serverSocket, ezEvent::eventRead, array($this, 'onAccept'));
			ezGLOBALS::$event->loop();
			echo "main pid exit event loop\n";
			exit();
		}
        for($i=0;$i<ezGLOBALS::$processCount;$i++){
			$pid = pcntl_fork();
			if($pid == 0) {
				ezGLOBALS::$event = new ezEvent();
				ezGLOBALS::$event->add($this->serverSocket, ezEvent::eventRead, array($this, 'onAccept'));
				ezGLOBALS::$event->loop();
				echo "child pid exit event loop\n";
			}else{
				echo "child pid -> $pid\n";
				$this->pids[] = $pid;
			}
		}
		$this->monitorWorkers();
    }
    private function monitorWorkers(){
		echo "start monitor workers\n";
		while(1){
			sleep(10);
		}
	}
	// 当收到连接时
	public function onAccept($socket){
		$new_socket = @stream_socket_accept($socket, 0, $remote_address);
		if (!$new_socket) 
			return;
		echoDebug("connect socket -> ".$new_socket);
		echoDebug("remote address -> ".$remote_address);
		stream_set_blocking($new_socket,0);

		$tcp = new ezTCP($new_socket,$remote_address);
		$tcp->setOnMessage($this->onMessage);
		ezGLOBALS::$event->add($new_socket, ezEvent::eventRead, array($tcp, 'onRead'));
	}
}