<?php

class ezEventDB{
//	public $que = null;
	private $syncLink 			= null;
	private $asyncLinks 		= array();
	private $linkKeys 			= array();
	private $freeAsyncLink 	= array();
	private $sqlList 			= array();
	private $time				= 0;
	public function __construct(){
		ezGLOBALS::$dbEvent = $this;
//		$this->que = new ezQue();
	}
	public function init(){
		$conf = ezGLOBALS::$dbConf;
		$maxAsyncLinks = ezGLOBALS::$maxAsyncLinks;
		$this->syncLink = $this->connectDB($conf);
		ezDebugLog("sync link is: ".$this->linkToKey($this->syncLink));
		if(ezGLOBALS::$dbEventTime==0)return;
		for($i=0;$i<$maxAsyncLinks;$i++){
			$con = $this->connectDB($conf);
			$this->asyncLinks[] = $con;
			$this->freeAsyncLink[] = $con;
			ezDebugLog("link key is: ".$this->linkToKey($con));
//			$this->que->sendMsg(ezQue::queDBConFree, $linkKey);
		}
		ezGLOBALS::$event->add(ezGLOBALS::$dbEventTime,ezEvent::eventTime, array($this,'loop'));
	}
	private function connectDB($conf){
		$con = mysqli_connect($conf['host'], $conf['user'], $conf['password'], $conf['dataBase'], $conf['port']);
		if (!$con)throw new Exception(mysqli_error());
		return $con;
	}
	private function linkToKey($link){
		return $link->thread_id;
	}
	public function excute($sql, $func = null,$queEvent = false){
		if(!empty($func) || $queEvent){
			$con = ezGLOBALS::$curConnect;
			if(!empty($func)) {
				$con->setDelaySend();
				$con->data['HTTP_CONNECTION'] = $_SERVER['HTTP_CONNECTION'];
			}
			$link = array_shift($this->freeAsyncLink);
			if(empty($link))
				$this->sqlList[] = array($sql,$func,$con);
			else{
				$linkKey = $this->linkToKey($link);
				$ret = mysqli_query($link, $sql,MYSQLI_ASYNC);
				$this->linkKeys[$linkKey] = array($func,$con);
			}
//			$linkKey = $this->que->getMsg(ezQue::queDBConFree);
//			if(empty($linkKey)){
//				$this->que->sendMsg(ezQue::queDBSql,$sql,true,array($this,'onLinkReady'),$func);
//			}else{
//				if(empty($this->linkKeys[$linkKey])){
//					echo "get connect from que error,can not found connect key";
//					return false;
//				}
//				echo $sql."\n";
//				$ret = mysqli_query($this->linkKeys[$linkKey], $sql,MYSQLI_ASYNC);
//				$this->que->sendMsg(ezQue::queDBConBusy, $linkKey, true, array($this,'onLinkReady'),$func);
//			}
		}else{
			$row = mysqli_query($this->syncLink, $sql);
			if(is_object($row))
				return $row->fetch_all(MYSQLI_ASSOC);
			else if($row == true)return;
			else {
			    var_dump($row);
				echo $this->syncLink->error . "\n";
				echo "<br>$sql<br>";
			}
		}
	}

//	public function onLinkReady($linkKey,$func){
//		echo $linkKey."\n";
//		if(empty($this->linkKeys[$linkKey]))return;
//		$link = $this->linkKeys[$linkKey];
//		$sql_result = $link->reap_async_query();
//		var_dump($sql_result);
//		if (is_object($sql_result))
//			$linkData = $sql_result->fetch_all(MYSQLI_ASSOC);
//		else
//			echo $link->error, "\n";
//		$sql = $this->que->getMsg(ezQue::queDBSql);
//		if(!empty($sql)){
//			mysqli_query($link, $sql['data'],MYSQLI_ASYNC);
//			$data['pid'] = $sql['pid'];
//			$data['queID'] = $sql['queID'];
//			$data['data'] = $linkKey;
//			$this->que->sendMsg(ezQue::queDBConBusy, $data);
//		}else
//			$this->que->sendMsg(ezQue::queDBConFree,$linkKey);
//		var_dump($linkData);
//		if(is_object($sql_result)){
//			ob_start();
//			call_user_func_array($func,array($linkData));
//			$content = ob_get_clean();
//			echo $content;
//		}
//	}

	//  db loop do
	public function loop(){
		while(true){
		    if(count($this->asyncLinks) == 0)return;
			$read = $errors = $reject = $this->asyncLinks;
			$re = mysqli_poll($read, $errors, $reject, $this->time);
			if (false === $re) {
				die('mysqli_poll failed');
			} elseif ($re < 1)
				return;

			ezDebugLog("read ready!");
			foreach ($read as $link) {
				$sql_result = $link->reap_async_query();
				if (is_object($sql_result))
					$linkData = $sql_result->fetch_all(MYSQLI_ASSOC);
				else
					echo $link->error, "\n";
				$linkKey = $this->linkToKey($link);
				$linkInfo = $this->linkKeys[$linkKey];
				$sqlInfo = array_shift($this->sqlList);
				if(empty($sqlInfo)){
					$this->freeAsyncLink[] = $link;
					unset($this->linkKeys[$linkKey]);
				}
				else {
					ezDebugLog("do sql que");
					mysqli_query($link, $sqlInfo[0], MYSQLI_ASYNC);
					$this->linkKeys[$linkKey] = array($sqlInfo[1], $sqlInfo[2]);
				}
				$func = $linkInfo[0];
				if(empty($func))continue;
				$socketCon = $linkInfo[1];
				ezDebugLog($socketCon->getSocket());
				ezDebugLog($linkKey);
				$socketCon->setImmedSend();
				ob_start();
				try {
					call_user_func_array($func, array($linkData));
				}catch (Exception $ex){
					echo $ex;
				}
				$contents = ob_get_clean();
				if (strtolower($socketCon->data['HTTP_CONNECTION']) === "keep-alive") {
					$socketCon->send($contents);
				} else {
					$socketCon->close($contents);
				}
				ezDebugLog("close");
			}
			return;
		}
//		while(true){
//			$read = $errors = $reject = $this->asyncConnects;
//			$re = mysqli_poll($read, $errors, $reject, $time);
//			if (false === $re) {
//				die('mysqli_poll failed');
//			} elseif ($re < 1) {
//				continue;
//			}
//			echo "async mysql done\n";
//			while(true){
//				$busyLink = $this->que->getMsg(ezQue::queDBConBusy);
//				if(empty($busyLink))break;
//				$this->linkKeys[$busyLink['data']] = array('pid'=>$busyLink['pid'],'queID'=>$busyLink['queID']);
//			}
//			foreach ($read as $link) {
//				$linkKey = $this->linkToKey($link);
//				$linkInfo = $this->linkKeys[$linkKey];
//				$pid = $linkInfo['pid'];
//				$queID = $linkInfo['queID'];
//				$type = ezQue::queEventDone.$pid;
//				$this->que->sendMsg($type,array('queID'=>$queID,'data'=>$linkKey));
//				posix_kill($pid,SIGUSR1);
//			}
//
//			foreach ($errors as $link) {
//				echo $link->error, "1\n";
//			}
//
//			foreach ($reject as $link) {
//				printf("server is busy, client was rejected.\n", $link->connect_error, $link->error);
//			}
//		}
	}
	public function isFree(){
		if(count($this->linkKeys) == 0)return true;
	}
}