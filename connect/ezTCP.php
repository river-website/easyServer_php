<?php

class ezTCP{
	private $socket = null;
	private $onMessage = null;
	private $event = null;
	public function __construct($socket){
		$this->socket = $socket;
	}
	public function setOnMessage($func){
		$this->onMessage = $func;
	}
	public function setEvent($event){
		$this->event = $event;
	}
	public function onRead($socket){
		$buffer = fread($socket, 65535);
		// Check connection closed.
		if ($buffer === '' || $buffer === false || !is_resource($socket)) {
			$this->destroy();
			return;	
		}
		if($this->onMessage)
			call_user_func_array($this->onMessage,array($this,$buffer));
	}
	public function onWrite($socket){
		if(!empty($this->sendBuffer)){
			$data = $this->sendBuffer;
			$len = @fwrite($socket,$dat);
			if($len <= 0){
				if (!is_resource($this->socket) || feof($this->socket)) 
					$this->destroy();
				else $this->sendBuffer = $data;
				return;
			}else if($len != strlen($data))$this->sendBuffer = substr($data, $len);
		}
		$this->event->del($this->socket,ezEvent::write);
	}
	public function send($data){
		$len = @fwrite($this->socket,$data);
		echo 'send data len:'.$len."\n";
		if($len == strlen($data)) return true;
		else if($len>0) $this->sendBuffer = substr($data, $len);
		else{
			if (!is_resource($this->socket) || feof($this->socket)) {
				$this->destroy();
				return false;
			}
			$this->sendBuffer = $data;
		}
		$this->event->add($this->socket,ezEvent::write,array($this,'onWrite'));
		return true;
	}
	public function destroy()
	{
		echo $this->socket." destroy!\n";
		// Remove event listener.
		$this->event->del($this->socket, ezEvent::read);
		$this->event->del($this->socket, ezEvent::write);
		// Close socket.
		@fclose($this->socket);
	}
}