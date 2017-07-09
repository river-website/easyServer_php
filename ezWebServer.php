<?php
require 'protocol/ezHTTP.php';

class ezWebServer extends ezServer {
	private $protocol = null;
	private $web = array();

	public function __construct($host){
		parent::__construct('tcp://'.$host);
		$this->onMessage = array($this, 'onMessage');
		$this->protocol = new ezHTTP();
	}
	public function setWeb($webSite,$path){
		$this->web[$webSite] = $path;
	}
	public function onMessage($con,$data){
		echo "com in web\n";
		// $ret = $this->protocol->decode($data,$con);
		$file_path = 'C:/AppServ/www/index.html';
        $modified_time = $info ? date('D, d M Y H:i:s', $info['mtime']) . ' ' . date_default_timezone_get() : '';

		        $file_size = filesize($file_path);
		        $file_info = pathinfo($file_path);
		        $extension = isset($file_info['extension']) ? $file_info['extension'] : '';
		        $file_name = isset($file_info['filename']) ? $file_info['filename'] : '';
		        $header = "HTTP/1.1 200 OK\r\n";
		        
		           $header .= "Content-Type: application/octet-stream\r\n";
		           $header .= "Content-Disposition: attachment; filename=\"$file_name\"\r\n";
		        
		        $header .= "Connection: keep-alive\r\n";
		        $header .= $modified_time;
		        $header .= "Content-Length: $file_size\r\n\r\n";
		        $trunk_limit_size = 1024*1024;
		         return $con->send($header.file_get_contents($file_path));
		        
	}

}