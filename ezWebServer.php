<?php

class ezWebServer {
	private $serverRoot = array();
	public function __construct($host){
		set_error_handler(array($this,'errorHandle'));
		$server = new ezServer('tcp://'.$host);
		$server->onMessage = array($this, 'onMessage');
		$server->protocol = new ezHTTP();
        ezGLOBALS::$thirdEvents[] = new ezEventDB();
        ezGLOBALS::$thirdEvents[] = new ezEventQue();
	}

	// 设置域名和网站目录
	public function setWeb($webSite,$path){
		$this->serverRoot[$webSite] = $path;
	}
	public function start(){
		ezGLOBALS::$server->start();
	}
	// 处理从tcp来的数据
	public function onMessage($connection,$data){
		// REQUEST_URI.
		$workerman_url_info = parse_url($_SERVER['REQUEST_URI']);
		if (!$workerman_url_info) {
			ezHttp::header('HTTP/1.1 400 Bad Request');
			$connection->close('<h1>400 Bad Request</h1>');
			return;
		}

		$workerman_path = isset($workerman_url_info['path']) ? $workerman_url_info['path'] : '/';

		$workerman_path_info	  = pathinfo($workerman_path);
		$workerman_file_extension = isset($workerman_path_info['extension']) ? $workerman_path_info['extension'] : '';
		if ($workerman_file_extension === '') {
			$workerman_path		   = ($len = strlen($workerman_path)) && $workerman_path[$len - 1] === '/' ? $workerman_path . 'index.php' : $workerman_path . '/index.php';
			$workerman_file_extension = 'php';
		}

		$workerman_root_dir = isset($this->serverRoot[$_SERVER['SERVER_NAME']]) ? $this->serverRoot[$_SERVER['SERVER_NAME']] : current($this->serverRoot);

		$workerman_file = "$workerman_root_dir$workerman_path";
		if ($workerman_file_extension === 'php' && !is_file($workerman_file)) {

			$workerman_file = "$workerman_root_dir/index.php";
			if (!is_file($workerman_file)) {
				$workerman_file		   = "$workerman_root_dir/index.html";
				$workerman_file_extension = 'html';
			}

		}

		// File exsits.
		if (is_file($workerman_file)) {
			// Security check.
			if ((!($workerman_request_realpath = realpath($workerman_file)) || !($workerman_root_dir_realpath = realpath($workerman_root_dir))) || 0 !== strpos($workerman_request_realpath,
					$workerman_root_dir_realpath)
			) {
				ezHttp::header('HTTP/1.1 400 Bad Request');
				$connection->close('<h1>400 Bad Request</h1>');
				return;
			}

			$workerman_file = realpath($workerman_file);
			// Request php file.
			if ($workerman_file_extension === 'php') {
                ezGLOBALS::$curConnect = $connection;
                $workerman_cwd = getcwd();
                chdir($workerman_root_dir);
                ini_set('display_errors', 'off');
                ob_start();
                // Try to include php file.
                try {
                    // $_SERVER.
                    $_SERVER['REMOTE_ADDR'] = $connection->getRemoteIp();
                    $_SERVER['REMOTE_PORT'] = $connection->getRemotePort();
                    include $workerman_file;
                } catch (Exception $e) {
                    echo $e->getMessage();
                }
                $content = ob_get_clean();
                ini_set('display_errors', 'on');
                if (strtolower($_SERVER['HTTP_CONNECTION']) === "keep-alive") {
                    $connection->send($content);
                } else {
                    $connection->close($content);
                }
                chdir($workerman_cwd);
//                include 'com/ezServerStatus.php';
//                ezGLOBALS::$status = ($GLOBALS['ezServerStatus'] == ezServer::normal)?ezServer::running:ezServer::waitExit;
                return;
            }
			// Send file to client.
			return self::sendFile($connection, $workerman_file);
		} else {
			// 404
			ezHttp::header("HTTP/1.1 404 Not Found");
			$connection->close('<html><head><title>404 File not found</title></head><body><center><h3>404 Not Found</h3></center></body></html>');
			return;
		}
	}
	public function errorHandle($errno, $errstr, $errfile, $errline){
		if(ezGLOBALS::getErrorIgnorePath(E_NOTICE,$errfile))return;
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
//		if(strstr($errfile,__DIR__))
			ezServerLog($msg);
//		else echo $msg.'<br>';
	}
}