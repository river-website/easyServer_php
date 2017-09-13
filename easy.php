<?php
/**
 * Created by PhpStorm.
 * User: win10
 * Date: 2017/9/13
 * Time: 14:43
 */

class easy{
	public $processCount 		= 1;
	public $checkStatusTime		= 1000;

	static public function getInterface(){
		static $easy;
		if(empty($easy)) {
			self::back();
			$easy = new easy();
		}
		return $easy;
	}

	static private function back(){
		$pid  = pcntl_fork();
		if($pid > 0)exit();
	}
	public function __construct(){
		$this->mainPid = getmypid();
		$this->pid = getmypid();
		$this->os = $this->getOS();
	}
	// 获取操作系统
	private function getOS(){
		// windows or linux
		if(strpos(PHP_OS,'WIN') !== FALSE)
			$os = 'Windows';
		else $os = 'Linux';
		return $os;
	}
	private function monitorWorkers(){
		$this->log("start monitor workers");
		while(true){
			$pid = pcntl_wait($status, WNOHANG );
			if($pid>0) {
				$this->log("work process $pid exit");
				$this->checkProcessStatus($pid);
			}else if($pid==0){
				$this->checkProcessStatus();
			}else{
				$this->log(" pcntl_wait error");
			}
			$time =$this->checkStatusTime/1000;
			sleep($time);
		}
		exit();
	}
	// 检查状态
	public function checkProcessStatus($exitPid = 0){
		$status = $this->getServerStatus();
		if($this->pid == $this->mainPid){
			if ($status == ezServer::exitAll){
				$childPids = $this->getRunTimeData($this->pidsFile);
				if(empty($childPids) || count($childPids) == 0)exit();
				while(count($childPids)>0){
					$live = array();
					foreach ($childPids as $key=>$pid) {
						if (posix_kill($pid, 0)) {
							$ret = pcntl_waitpid($pid,$status,WNOHANG);
							if($ret<=0) {
								$this->log("will kill $pid");
								posix_kill($pid, SIGKILL);
								$live[$key] = $pid;
								continue;
							}
						}
						$this->log("$pid has exit");
					}
					$childPids = $live;
					$time = $this->checkStatusTime/1000;
					sleep($time);
				}
				$this->log("all process exit");
				exit();
			}else if($status == ezServer::reload){
				$childPids = $this->getRunTimeData($this->pidsFile);
				if(empty($childPids) || count($childPids) == 0){
					$this->forks();
					return;
				}
				while(count($childPids)>0){
					$live = array();
					foreach ($childPids as $key=>$pid) {
						if (posix_kill($pid, 0)) {
							$ret = pcntl_waitpid($pid,$status,WNOHANG);
							if($ret<=0) {
								$this->log("will kill $pid");
								posix_kill($pid, SIGKILL);
								$live[$key] = $pid;
								continue;
							}
						}
						$this->log("$pid has exit");
					}
					$childPids = $live;
					$time = $this->checkStatusTime/1000;
					sleep($time);
				}
				$this->forks();
			}else if($status == ezServer::smoothReload && $exitPid>0){
				$this->forkOne();
			}else if($status == ezServer::normal && $exitPid>0) {
				$this->forkOne();
			}
		}else {
			if ($status == ezServer::normal) return;
			if ($status == ezServer::exitAll){
				$this->delServerSocketEvent();
				$this->log('exit');
				exit();
			}
			if ($status == ezServer::reload){
				if (!empty($this->curConnect)) {
					$this->delServerSocketEvent();
					$this->log('exit');
					exit();
				}
			}
			if ($status == ezServer::smoothReload) {
				if (!empty($this->curConnect)) {
					// 开始平滑重启
					$this->delServerSocketEvent();
					if($this->eventCount>0)return;
					$this->log('work process exit');
					exit();
				}
			}
		}
	}
}