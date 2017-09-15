<?php
/**
 * Created by PhpStorm.
 * User: win10
 * Date: 2017/9/15
 * Time: 15:28
 */
class easy{
	static $pidsPath			= 'pids';
	public $checkServerTime		= 1;
	private $server			= 'ezServer';
	private $serverData		= array();
	public function __construct(){

	}
	public function setServer($server = 'ezServer'){
		$this->server = $server;
	}
	public function setServerData($serverData){
		$this->serverData = $serverData;
	}
	public function start(){
		$this->back();
		$this->forkServer();
		$this->monitorServer();
	}
	private function back(){
		$pid  = pcntl_fork();
		if($pid > 0)exit();
	}
	private function forkServer(){
		$pid = pcntl_fork();
		if($pid == 0) {
			// 加载server文件
			$serverPath = $this->server.'.php';
			require $serverPath;
			// 启动server
			$server = new $this->server();
			$server->setData($this->serverData);
			$server->start();
			exit();
		}
	}
	private function monitorServer(){
		while(true){
			$pid = pcntl_wait($status, WNOHANG );
			if($pid>0) {
				$this->forkServer();
			}else if($pid==0){
				$this->checkServer();
			}else{
			}
			sleep($this->checkServerTime);
		}
		exit();
	}
	static public function getServer(){
		return json_decode(file_get_contents(self::$pidsPath),true);
	}
	private function closeAll($pids){
		unset($pids['main']);
		$killList = array();
		foreach ($pids as $key => $value)
			$killList[] = $value['pid'];
		while(count($killList)>0){
			$live = array();
			foreach ($killList as $pid) {
				if (posix_kill($pid, 0)) {
					$ret = pcntl_waitpid($pid,$status,WNOHANG);
					if($ret<=0) {
						posix_kill($pid, SIGKILL);
						$live[] = $pid;
					}
				}
			}
			$killList = $live;
		}
	}
	// 检查状态
	private function checkServer(){
		$pids = self::getServer();
		$mainStatus = $pids['main'];
		if($mainStatus === 'run'){
			// 正常运行
		}else if($mainStatus === 'stop'){
			// 关闭所有（包括self）
			$this->closeAll($pids);
			exit();
		}else if($mainStatus === 'reload'){
			// 关闭所有（不包括self） 重启server
			$this->closeAll($pids);
			$this->forkServer();
		}
	}
}