<?php

class ezDbPool{
	public $maxAsyncLinks 		= 0;
	public $dbPoolTime			= 1;
	public $dbConf				= array(
		'host' => '127.0.0.1',
		'user' => 'root',
		'password' => 'root',
		'dataBase' => 'yun',
		'port' => 3306
	);

	private $syncLink 			= null;
	private $asyncLinks 		= array();
	private $linkKeys 			= array();
	private $freeAsyncLink      = array();
	private $sqlList 			= array();
	private $bakLink            = array();

	public function __construct(){
	}
	static public function getInterface(){
		static $dbPool;
		if(empty($dbPool)) {
			$dbPool = new ezDbPool();
		}
		return $dbPool;
	}
	public function init(){
		$conf = $this->dbConf;
        $maxAsyncLinks = $this->maxAsyncLinks;
        $this->syncLink = $this->connectDB($conf);
        ezServer::getInterface()->debugLog("sync link is: ".$this->linkToKey($this->syncLink));
		if($this->dbPoolTime==0)return;
		for($i=0;$i<$maxAsyncLinks;$i++){
			$con = $this->connectDB($conf);
			$this->asyncLinks[] = $con;
			$this->freeAsyncLink[] = $con;
			ezServer::getInterface()->debugLog("async link is: ".$this->linkToKey($con));
		}
		ezReactor::getInterface()->add($this->dbPoolTime,ezReactor::eventTime, array($this,'loop'));
	}
	private function connectDB($conf){
		$con = mysqli_connect($conf['host'], $conf['user'], $conf['password'], $conf['dataBase'], $conf['port']);
		if (!$con)throw new Exception(mysqli_error($con));
        mysqli_query($con,"set names 'utf8'");
		return $con;
	}
	public function bakLinks(){
        $this->bakLink[] = $this->syncLink;
        $this->bakLink[] = $this->asyncLinks;
        $this->bakLink[] = $this->linkKeys;
        $this->bakLink[] = $this->freeAsyncLink;
        $this->bakLink[] = $this->sqlList;

	    $this->syncLink = null;
        $this->asyncLinks = null;
        $this->linkKeys = null;
        $this->freeAsyncLink = null;
        $this->sqlList = null;
    }
    public function createSync(){
        $conf = $this->dbConf;
        $this->syncLink = $this->connectDB($conf);
        ezServer::getInterface()->debugLog("sync link is: ".$this->linkToKey($this->syncLink));
    }
	private function linkToKey($link){
		return $link->thread_id;
	}
	public function excute($sql, $func = null,$queEvent = false){
		if(!empty($func) || $queEvent){
		    ezServer::getInterface()->pid++;
			$con = ezServer::getInterface()->curConnect;
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

	//  db loop do
	public function loop(){
		while(true){
		    if(count($this->asyncLinks) == 0)return;
			$read = $errors = $reject = $this->asyncLinks;
			$re = mysqli_poll($read, $errors, $reject, 0);
			if (false === $re) {
				die('mysqli_poll failed');
			} elseif ($re < 1)
				return;

			ezServer::getInterface()->debugLog("read ready!");
			foreach ($read as $link) {
			    ezServer::getInterface()->pid--;
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
				    ezServer::getInterface()->pid++;
					ezServer::getInterface()->debugLog("do sql que");
					mysqli_query($link, $sqlInfo[0], MYSQLI_ASYNC);
					$this->linkKeys[$linkKey] = array($sqlInfo[1], $sqlInfo[2]);
				}
				$func = $linkInfo[0];
				if(empty($func))continue;
				$socketCon = $linkInfo[1];
				ezServer::getInterface()->debugLog($socketCon->getSocket());
				ezServer::getInterface()->debugLog($linkKey);
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
				ezServer::getInterface()->debugLog("close");
			}
			return;
		}
	}
}