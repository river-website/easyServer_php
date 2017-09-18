<?php
/**
 * Created by PhpStorm.
 * User: win10
 * Date: 2017/9/15
 * Time: 15:28
 */
if(!defined('ROOT'))define('ROOT', __DIR__.'/..');
class easy{
	static public $pidsPath			= '/www/wwwroot/test/easyServer/system/pids';
	static public $checkServerTime	= 1;
	private $server					= 'ezServer';
	private $serverData				= array();
	public function __construct(){

	}
	public function setServer($server = 'ezServer'){
		$this->server = $server;
	}
	public function setServerData($serverData){
		$this->serverData = $serverData;
	}
	static public function getPids(){
		return json_decode(file_get_contents(self::$pidsPath),true);
	}
	static public function setPids($pids){
		file_put_contents(self::$pidsPath, json_encode($pids,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
	}
	static public function addPid($type,$pid){
		if(empty($pid))return;
		if(empty($type))return;
		$pids = self::getPids();
		$pidData['pid'] = $pid;
		$pidData['state'] = 'run';
		$pidData['time'] = date('y-M-d h:i:s',time());
		$pids[$type][] = $pidData;
		self::setPids($pids);
	}
	static public function delPid($pid){
		if(empty($pid))return;
		$pids = self::getPids();
		foreach ($pids as $type => &$pidList) {
			foreach ($pidList as $index=>$pidData) {
				if($pidData['pid'] == $pid)
					unset($pidList[$index]);
			}
		}
		self::setPids($pids);
	}
	static public function updatePid($pid,$state = 'run'){
		if(empty($pid))return;
		$pids = self::getPids();
		foreach ($pids as $type => &$pidList) {
			foreach ($pidList as $index=>$pidData) {
				if($pidData['pid'] == $pid)
					$pidList[$index]['state'] = $state;
			}
		}
		self::setPids($pids);
	}
	static public function getPidState($pid){
		if(empty($pid))return;
		$pids = self::getPids();
		foreach ($pids as $type => $pidList) {
			foreach ($pidList as $index=>$pidData) {
				if($pidData['pid'] == $pid)
					return $pidList[$index]['state'];
			}
		}
	}
	public function start(){
		$this->back();
		$this->forkServer();
		$this->monitorServer();
	}
	private function back(){
		$pid  = pcntl_fork();
		if($pid > 0)exit();
		file_put_contents(self::$pidsPath, null);
		self::addPid('main',getmypid());
	}
	private function forkServer(){
		$pid = pcntl_fork();
		if($pid == 0) {
			self::addPid('server',getmypid());
			// 加载server文件
			$serverPath = $this->server.'.php';
			require $serverPath;
			// 启动server
			$server = new $this->server();
			$server->setServerData($this->serverData);
			$server->start();
			exit();
		}
	}
	private function monitorServer(){
		while(true){
			$pid = pcntl_wait($status, WNOHANG );
			$this->checkServer();
			// if($pid>0) {
			// 	$this->forkServer();
			// }else if($pid==0){
			// 	$this->checkServer();
			// }else{
			// }
			sleep(self::$checkServerTime);
		}
		exit();
	}

	static public function killPids($killPids){
		$killList = $killPids;
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
		foreach ($killPids as $pid) 
			self::delPid($pid);
	}
	static public function getChilds($pids,$types = 'main'){
    	$childPids = array();
    	foreach ($pids as $type => $pidList) {
    		if($type == 'main')continue;
    		if($types == 'server' && $type == 'server')continue;
    		foreach ($pidList as $pidData) {
    			$childPids[$pidData['pid']] = $pidData;
    		}
    	}
    	return $childPids;
    }
	// 检查状态
	private function checkServer(){
		$pids = self::getPids();
		$mainStatus = $pids['main'][0]['state'];
		if($mainStatus === 'run'){
			// 正常运行
		}else if($mainStatus === 'stop'){
			// 关闭所有（包括self）
			self::killPids(array_keys(self::getChilds($pids,'main')));
			unlink(self::$pidsPath);
			exit();
		}else if($mainStatus === 'reload'){
			// 关闭所有（不包括self） 重启server
			self::killPids(array_keys(self::getChilds($pids,'main')));
			self::updatePid(getmypid(),'run');
			$this->forkServer();

		}
	}
}