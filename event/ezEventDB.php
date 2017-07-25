<?php

class ezEventDB{
//	public $que = null;
	private $syncConnect = null;
	private $asyncConnects = array();
	private $maxAsyncConnects = 1;
	private $conf = null;
	private $linkKeys = array();
	private $freeAsyncLink = array();
	private $busyAsyncLink = array();
    private $sqlList = array();
    private $server = null;
    public function __construct($server){
//        $this->que = new ezQue();
		$this->conf = array(
			'host' => '127.0.0.1',
			'user' => 'root',
			'password' => 'root',
			'dataBase' => 'test',
			'port' => 3306
		);
		$this->server = $server;
    }
    public function init(){
		$con = mysqli_connect($this->conf['host'], $this->conf['user'], $this->conf['password'], $this->conf['dataBase'], $this->conf['port']);
		if (!$con)throw new Exception(mysqli_error());
		$this->syncConnect = $con;
		$this->createConnects();
	}
    private function createConnects(){
		for($id=0;$id<$this->maxAsyncConnects;$id++){
			$con = mysqli_connect($this->conf['host'], $this->conf['user'], $this->conf['password'], $this->conf['dataBase'], $this->conf['port']);
			if (!$con)throw new Exception(mysqli_error());
			$this->asyncConnects[$id] = $con;
			$this->freeAsyncLink[] = $con;
			$linkKey = $this->linkToKey($con);
			echo "link key is -> ".$linkKey."\n";
//			$this->que->sendMsg(ezQue::queDBConFree, $linkKey);
		}
	}
    private function linkToKey($link){
        return $link->thread_id;
    }

    public function excute($sql,$func = null){
    	if(!empty($func)){
            $con = $this->server->curConn;
            $con->setDelaySend();
            $link = array_shift($this->freeAsyncLink);
            if(empty($link))
                $this->sqlList[] = array($sql,$func,$this->server->curConn);
            else{
                $linkKey = $this->linkToKey($link);
                $ret = mysqli_query($link, $sql,MYSQLI_ASYNC);
                $this->linkKeys[$linkKey] = array($func,$this->server->curConn);
                $this->busyAsyncLink[$linkKey] = $link;
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
			$row = mysqli_query($this->syncConnect, $sql);
			return $row->fetch_all(MYSQLI_ASSOC);
		}
    }

//	public function onLinkReady($linkKey,$func){
//        echo $linkKey."\n";
//        if(empty($this->linkKeys[$linkKey]))return;
//        $link = $this->linkKeys[$linkKey];
//        $sql_result = $link->reap_async_query();
//        var_dump($sql_result);
//        if (is_object($sql_result))
//            $linkData = $sql_result->fetch_all(MYSQLI_ASSOC);
//        else
//            echo $link->error, "\n";
//        $sql = $this->que->getMsg(ezQue::queDBSql);
//        if(!empty($sql)){
//            mysqli_query($link, $sql['data'],MYSQLI_ASYNC);
//            $data['pid'] = $sql['pid'];
//            $data['queID'] = $sql['queID'];
//            $data['data'] = $linkKey;
//            $this->que->sendMsg(ezQue::queDBConBusy, $data);
//        }else
//            $this->que->sendMsg(ezQue::queDBConFree,$linkKey);
//        var_dump($linkData);
//        if(is_object($sql_result)){
//            ob_start();
//            call_user_func_array($func,array($linkData));
//            $content = ob_get_clean();
//            echo $content;
//        }
//    }

    //  db loop do
    public function loop($break = true, $time = 0){
        while(true){
            $read = $errors = $reject = $this->busyAsyncLink;
            $re = mysqli_poll($read, $errors, $reject, $time);
			if (false === $re) {
				die('mysqli_poll failed');
			} elseif ($re < 1) {
			    if($break)return;
                continue;
			}
            foreach ($read as $link) {
                $sql_result = $link->reap_async_query();
                if (is_object($sql_result))
                    $linkData = $sql_result->fetch_all(MYSQLI_ASSOC);
                else
                    echo $link->error, "\n";
                $linkKey = $this->linkToKey($link);
                $sqlInfo = array_shift($this->sqlList);
                if(empty($sqlInfo)){
                	$this->freeAsyuncLink[] = $link;
				}
                else {
                    mysqli_query($link, $sqlInfo[0], MYSQLI_ASYNC);
                    $this->linkKeys[$linkKey] = array($sqlInfo[1], $sqlInfo[2]);
                }
                $linkInfo = $this->linkKeys[$linkKey];
                $func = $linkInfo[0];
				$socketCon = $linkInfo[1];
				$socketCon->setImmedSend();
				ob_start();
                try {
                    call_user_func_array($func, array($linkData));
                }catch (Exception $ex){
                    echo $ex;
                }
                $contents = ob_get_clean();
				echoDebug($socketCon->getSocket());
				$socketCon->close($contents);
				echoDebug(print_r($contents,true));
				echoDebug("close");
//				$socketCon->send($contents);
            }
        }
//    	while(true){
//			$read = $errors = $reject = $this->asyncConnects;
//			$re = mysqli_poll($read, $errors, $reject, $time);
//			if (false === $re) {
//				die('mysqli_poll failed');
//			} elseif ($re < 1) {
//                continue;
//			}
//            echo "async mysql done\n";
//            while(true){
//			    $busyLink = $this->que->getMsg(ezQue::queDBConBusy);
//			    if(empty($busyLink))break;
//                $this->linkKeys[$busyLink['data']] = array('pid'=>$busyLink['pid'],'queID'=>$busyLink['queID']);
//            }
//			foreach ($read as $link) {
//				$linkKey = $this->linkToKey($link);
//				$linkInfo = $this->linkKeys[$linkKey];
//                $pid = $linkInfo['pid'];
//                $queID = $linkInfo['queID'];
//                $type = ezQue::queEventDone.$pid;
//                $this->que->sendMsg($type,array('queID'=>$queID,'data'=>$linkKey));
//                posix_kill($pid,SIGUSR1);
//			}
//
//			foreach ($errors as $link) {
//				echo $link->error, "1\n";
//			}
//
//			foreach ($reject as $link) {
//				printf("server is busy, client was rejected.\n", $link->connect_error, $link->error);
//			}
//        }
    }
}