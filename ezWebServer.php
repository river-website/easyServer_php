<?php

class ezWebServer {
	private $serverRoot = array();
	public function __construct($host){
		$server = new ezServer('tcp://'.$host);
		$server->onMessage = array($this, 'onMessage');
		$server->protocol = new ezHTTP();
		ezGLOBALS::$thirdEvents[] = new ezEventDB($this);
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
		$connection->close("test!123test!123test!123test!123");
		return;
        // REQUEST_URI.
        $workerman_url_info = parse_url($_SERVER['REQUEST_URI']);
        if (!$workerman_url_info) {
            Http::header('HTTP/1.1 400 Bad Request');
            $connection->close('<h1>400 Bad Request</h1>');
            return;
        }

        $workerman_path = isset($workerman_url_info['path']) ? $workerman_url_info['path'] : '/';

        $workerman_path_info      = pathinfo($workerman_path);
        $workerman_file_extension = isset($workerman_path_info['extension']) ? $workerman_path_info['extension'] : '';
        if ($workerman_file_extension === '') {
            $workerman_path           = ($len = strlen($workerman_path)) && $workerman_path[$len - 1] === '/' ? $workerman_path . 'index.php' : $workerman_path . '/index.php';
            $workerman_file_extension = 'php';
        }

        $workerman_root_dir = isset($this->serverRoot[$_SERVER['SERVER_NAME']]) ? $this->serverRoot[$_SERVER['SERVER_NAME']] : current($this->serverRoot);

        $workerman_file = "$workerman_root_dir$workerman_path";
        if ($workerman_file_extension === 'php' && !is_file($workerman_file)) {
            $workerman_file = "$workerman_root_dir/index.php";
            if (!is_file($workerman_file)) {
                $workerman_file           = "$workerman_root_dir/index.html";
                $workerman_file_extension = 'html';
            }
        }
//		echo "visit page -> $workerman_file\n";
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
                } catch (\Exception $e) {
                    // Jump_exit?
                    if ($e->getMessage() != 'jump_exit') {
                        echo $e;
                    }
                }
                $content = ob_get_clean();
                ini_set('display_errors', 'on');
                if (strtolower($_SERVER['HTTP_CONNECTION']) === "keep-alive") {
                    $connection->send($content);
                } else {
                    $connection->close($content);
                }
                chdir($workerman_cwd);
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

}