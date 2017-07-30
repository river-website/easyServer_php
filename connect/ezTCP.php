<?php

class ezTCP{
	private $socket 			= null;
	private $remote_address 	= null;
	private $onMessage 		= null;
	private $protocol 			= null;
	private $sendBuffer 		= null;
	private $sendStatus 		= true;
	public $data				= null;
	public function __construct($socket,$remote_address){
		$this->socket = $socket;
		$this->remote_address = $remote_address;
	}
	// 设置tcp读数据完成回调函数
	public function setOnMessage($func){
		$this->onMessage = $func;
	}
	// 设置事件分发对象
	public function setProtocol($protocol){
		$this->protocol = $protocol;
	}
	public function getSocket(){
		return $this->socket;
	}
	public function getRemoteIp()
	{
		$pos = strrpos($this->remote_address, ':');
		if ($pos) {
			return trim(substr($this->remote_address, 0, $pos), '[]');
		}
		return '';
	}
	public function getRemotePort()
	{
		if ($this->remote_address) {
			return (int)substr(strrchr($this->remote_address, ':'), 1);
		}
		return 0;
	}
	// 回调处理读准备好事件
	public function onRead($socket){
		$buffer = fread($socket, 65535);
		// Check connection closed.
		if ($buffer === '' || $buffer === false || !is_resource($socket)) {
			$this->destroy();
			return;	
		}
		if(ezGLOBALS::$server->protocol){
			$buffer = ezGLOBALS::$server->protocol->decode($buffer,$this);
		}
		if($this->onMessage)
			call_user_func_array($this->onMessage,array($this,$buffer));
	}
	public function setDelaySend(){
		$this->sendStatus = false;
	}
	public function setImmedSend(){
		$this->sendStatus = true;
	}
	// 回调处理写准备好事件
	public function onWrite($socket){
		if(!empty($this->sendBuffer)){
			$data = $this->sendBuffer;
			$this->sendBuffer = '';
			$len = @fwrite($socket,$data);
			if($len <= 0){
				if (!is_resource($this->socket) || feof($this->socket)) 
					$this->destroy();
				else $this->sendBuffer = $data;
				return;
			}else if($len != strlen($data)) {
				$this->sendBuffer = substr($data, $len);
				return;
			}
		}
		ezGLOBALS::$event->del($this->socket,ezEvent::eventWrite);
	}
	// 发送数据
	public function send($data){
		if(!$this->sendStatus){
			$this->sendBuffer .= $data;
			return;
		}
		$data = $this->sendBuffer.$data;
		if(ezGLOBALS::$server->protocol)$data = ezGLOBALS::$server->protocol->encode($data,$this);
		$len = @fwrite($this->socket,$data);
		if($len == strlen($data)) {
			$this->sendBuffer = '';
			return true;
		}
		else if($len>0) $this->sendBuffer = substr($data, $len);
		else{
			if (!is_resource($this->socket) || feof($this->socket)) {
				$this->destroy();
				return false;
			}
			$this->sendBuffer = $data;
		}
		ezGLOBALS::$event->add($this->socket,ezEvent::eventWrite,array($this,'onWrite'));
		return true;
	}
	//关闭当前连接
	public function close($data = null, $raw = false)
	{
		if ($data !== null) {
			$this->send($data);
		}

		if ($this->sendBuffer === '')
			$this->destroy();

	}
	// 析构当前连接
	public function destroy()
	{
		// Remove event listener.
		ezGLOBALS::$event->del($this->socket, ezEvent::eventRead);
		ezGLOBALS::$event->del($this->socket, ezEvent::eventWrite);
		// Close socket.
		fclose($this->socket);
		echoDebug("destroy socket: ".$this->socket);
	}
}