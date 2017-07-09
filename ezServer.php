<?php

require 'connect/ezTCP.php';
require 'event/ezEvent.php';

class ezServer{

	private $serverSocket = null;
	private $host = null;
	private $os = null;
	private $event = null;
	private $protocol = null;
	
	public $onMessage = null;
	public $onStart = null;
	public $onStop = null;
	public $onConnect = null;
	public $onClose = null; 

	public function __construct($host){
		$this->host = $host;
		$this->os = $this->getOS();
	}
	function getOS(){ 
		$os=''; 
		$Agent=$_SERVER['HTTP_USER_AGENT']; 
		if (eregi('win',$Agent)&&strpos($Agent, '95')){ 
			$os='Windows 95'; 
		}elseif(eregi('win 9x',$Agent)&&strpos($Agent, '4.90')){ 
			$os='Windows ME'; 
		}elseif(eregi('win',$Agent)&&ereg('98',$Agent)){ 
			$os='Windows 98'; 
		}elseif(eregi('win',$Agent)&&eregi('nt 5.0',$Agent)){ 
			$os='Windows 2000'; 
		}elseif(eregi('win',$Agent)&&eregi('nt 6.0',$Agent)){ 
			$os='Windows Vista'; 
		}elseif(eregi('win',$Agent)&&eregi('nt 6.1',$Agent)){ 
			$os='Windows 7'; 
		}elseif(eregi('win',$Agent)&&eregi('nt 5.1',$Agent)){ 
			$os='Windows XP'; 
		}elseif(eregi('win',$Agent)&&eregi('nt',$Agent)){ 
			$os='Windows NT'; 
		}elseif(eregi('win',$Agent)&&ereg('32',$Agent)){ 
			$os='Windows 32'; 
		}elseif(eregi('linux',$Agent)){ 
			$os='Linux'; 
		}elseif(eregi('unix',$Agent)){ 
			$os='Unix'; 
		}else if(eregi('sun',$Agent)&&eregi('os',$Agent)){ 
			$os='SunOS'; 
		}elseif(eregi('ibm',$Agent)&&eregi('os',$Agent)){ 
			$os='IBM OS/2'; 
		}elseif(eregi('Mac',$Agent)&&eregi('PC',$Agent)){ 
			$os='Macintosh'; 
		}elseif(eregi('PowerPC',$Agent)){ 
			$os='PowerPC'; 
		}elseif(eregi('AIX',$Agent)){ 
			$os='AIX'; 
		}elseif(eregi('HPUX',$Agent)){ 
			$os='HPUX'; 
		}elseif(eregi('NetBSD',$Agent)){ 
			$os='NetBSD'; 
		}elseif(eregi('BSD',$Agent)){ 
			$os='BSD'; 
		}elseif(ereg('OSF1',$Agent)){ 
			$os='OSF1'; 
		}elseif(ereg('IRIX',$Agent)){ 
			$os='IRIX'; 
		}elseif(eregi('FreeBSD',$Agent)){ 
			$os='FreeBSD'; 
		}elseif($os==''){ 
			$os='Unknown'; 
		} 
		return $os; 
	}
	public function initWorker(){
		$this->serverSocket = stream_socket_server($this->host);
		if(!$this->serverSocket){
			echo 'create socket fail!';
			exit();
		}
		stream_set_blocking($this->serverSocket,0);
		echo "server socket is->".$this->serverSocket."\n";
		$this->event = new ezEvent();
		$this->event->add($this->serverSocket, ezEvent::read, array($this, 'onAccept'));
		$this->event->loop();
	}
	public function onAccept($socket){
		$new_socket = @stream_socket_accept($socket, 0, $remote_address);
		if (!$new_socket) 
			return;
		echo $new_socket." connect in!\n";
		stream_set_blocking($new_socket,0);

		$tcp = new ezTCP($new_socket);
		$tcp->setEvent($this->event);
		$tcp->setOnMessage($this->onMessage);
		$this->event->add($new_socket, ezEvent::read, array($tcp, 'onRead'));
	}
}

