<?php

require 'connect/ezTCP.php';
require 'event/ezEvent.php';

class ezServer{

	private $serverSocket = null;
	private $host = null;
	private $os = null;

	protected $event = null;
	protected $thirdEvents = array();
	protected $protocol = null;
    private $queSocket = null;
	public $processCount = 4;
	private $pids = array();
	public $onMessage = null;
	public $onStart = null;
	public $onStop = null;
	public $onConnect = null;
	public $onClose = null; 

	public function __construct($host){
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
	// 初始化
	private function init(){
	    $this->os = $this->getOS();
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
	    $this->init();
        $this->createSocket();
		$this->forkMysql();
        for($i=0;$i<$this->processCount;$i++){
			$pid = pcntl_fork();
			if($pid == 0) {
				$this->event = new ezEvent($this->os);
				$this->event->thirdEvents = $this->thirdEvents;
				$this->event->add($this->serverSocket, ezEvent::read, array($this, 'onAccept'));
				$this->event->loop();
				echo "child pid exit event loop\n";
			}else{
				echo "child pid -> $pid\n";
				$this->pids[] = $pid;
			}
		}
		$this->monitorWorkers();
    }
    private function forkMysql(){
        $pid = pcntl_fork();
        if($pid == 0) {
            $this->queSocket = stream_socket_server('tcp://0.0.0.0:8888');
            if (!$this->queSocket) {
                echo "error -> create que socket fail!\n";
                exit();
            }
            stream_set_blocking($this->queSocket, 0);
            echo "que socket -> " . $this->queSocket . "\n";
            $this->onMessage = array($this->asynDB,'onMessage');
			$this->event = new ezEvent($this->os);
//			$this->event->thirdEvents = $this->thirdEvents;
			$this->event->add($this->queSocket, ezEvent::eventRead, array($this, 'onAccept'));
			$this->event->loop();
			echo "child pid exit event loop\n";
        }else{
            $this->pids[] = $pid;
        }

    }
    private function pubMem(){

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
        echo "connect socket -> ".$new_socket."\n";
		stream_set_blocking($new_socket,0);

		$tcp = new ezTCP($new_socket,$remote_address);
		$tcp->setEvent($this->event);
		$tcp->setOnMessage($this->onMessage);
		$tcp->setProtocol($this->protocol);
		$this->event->add($new_socket, ezEvent::read, array($tcp, 'onRead'));
	}
}