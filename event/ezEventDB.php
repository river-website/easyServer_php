<?php

class ezEventDB{

	const DBSyn = 0;
	const DBAsyn = 1;

	private $que = null;
	private $syncConnect = null;
	private $asyncConnects = array();
	private $maxAsyncConnects = 10;
	private $conf = null;
	private $pid = null;
	private $events = array();
	private $clinetConnects = array();
	private $main = false;
//	private $allCon = array();

	public $model = 0;

    private $busyCon = array();
    private $freeCon = array();
    private $sqlQue = array();

    public function __construct(){
    	$this->pid = getmygid();
        $this->que = new ezQue(__FILE__);
		$this->conf = array(
			'host' => '127.0.0.1',
			'user' => 'root',
			'password' => 'root',
			'dataBase' => 'test',
			'port' => 3306
		);
		$this->createConnects();
    }
    private function createConnects(){
		$con = mysqli_connect($this->conf['host'], $this->conf['user'], $this->conf['password'], $this->conf['dataBase'], $this->conf['port']);
		if (!$con)throw new Exception(mysqli_error());
		$this->syncConnect = $con;

    	for($id=0;$id<$this->maxAsyncConnects;$id++){
			$con = mysqli_connect($this->conf['host'], $this->conf['user'], $this->conf['password'], $this->conf['dataBase'], $this->conf['port']);
			if (!$con)throw new Exception(mysqli_error());
			$this->asyncConnects[$id] = $con;
			$this->que->sendMsg(ezQue::queDBConFree, $id);
		}
	}

    public function add($sqlCon){
//        if($this->que->getCount()<$this->connectCount){
//            $this->que->sendMsg(ezEventDB::queDBFree,$sqlCon);
//        }
        return;
        if(count($this->allCon)<$this->connectCount){
            $conKey = $this->toUuid($sqlCon);
            $this->allCon[$conKey] = null;
            $this->freeCon[$conKey] = $sqlCon;
        }
    }
    private function toUuid($sqlCon){
        return $sqlCon->client_info;
    }
    public function del($sqlCon){
        $conKey = $this->toUuid($sqlCon);
        if(!empty($this->busyCon[$conKey]))
            return false;
        if(!empty($this->allCon[$conKey]))
            unset($this->allCon[$conKey]);
        if(!empty($this->freeCon[$conKey]))
            unset($this->freeCon[$conKey]);
        $this->event->del($sqlCon,ezEvent::read);
        echo "delete mysql connect -> ".print_r($sqlCon,true)."\n";
        return true;
    }
    public function excute($sql,$func = null){
    	if(!empty($func)){
			$conId = $this->que->getMsg(ezQue::queDBConFree,false);
			if($conId == null){
				$this->que->sendMsg(ezQue::queDBSql,array($this->pid, $sqlID, $sql));
			}else{
				if(empty($this->asyncConnects[$conId])){
					echo "get connect from que error,can not found connect id";
					return false;
				}
				mysqli_query($this->asyncConnects[$conId], $sql,MYSQLI_ASYNC);
				$this->que->sendMsg(ezQue::queDBConBusy, array($this->pid,$conId));
				$this->events[$conId] = array($GLOBALS['server']->curConn,$func);
			}
		}else{
			$row = mysqli_query($this->syncConnect, $sql);
			return $row->fetch_all(MYSQLI_ASSOC);
		}


        return;
        if(count($this->freeCon) == 0)
            $this->sqlQue[] = array($sql,$func);
        else{
            $sqlCon = array_shift($this->freeCon);
            $row = mysqli_query($sqlCon, $sql,MYSQLI_ASYNC);
            $conKey = $this->toUuid($sqlCon);
            $this->busyCon[$conKey] = $sqlCon;
            $this->allCon[$conKey] = array($func,$GLOBALS['server']->curConn);
        }
    }
    private function excueQue(){
        if(count($this->sqlQue) > 0){
            while(count($this->freeCon)>0) {
                $sql = array_shift($this->sqlQue);
                $this->excute($sql[0],$sql[1]);
            }
        }
    }

    public function onMessage($connection,$data){
		if(empty($data))return;
		$data = unserialize($data);
		if(empty($data))return;
		if(empty($data['key']))return;
		if($this->main){
			$this->clinetConnects[$data['key']] = $connection;
		}else{
			$key = $data['key'];
			if(empty($this->events[$key]))return;
			if(empty($this->asyncConnects[$key]))return;
			$link = $this->asyncConnects[$key];
			$ev = $this->events[$key];
			$sql_result = $link->reap_async_query();
			ob_start();
			if (is_object($sql_result))
				call_user_func_array($ev[1],array($sql_result->fetch_all(MYSQLI_ASSOC)));
			else
				echo $link->error, "\n";
			$content = ob_get_clean();
			$ev[0]->sendStatus = true;
			$ev[0]->send($content);
		}
	}

    public function loop($multi = true, $break = false, $time = 0.02){
    	while(true){
			$read = $errors = $reject = $this->asyncConnects;
			$re = mysqli_poll($read, $errors, $reject, $time);
			if (false === $re) {
				die('mysqli_poll failed');
			} elseif ($re < 1) {
                continue;
			}

			foreach ($read as $link) {
				$conKey = $this->toUuid($link);
				$conInfo = $this->events[$conKey];

				$sql_result = $link->reap_async_query();
				if (is_object($sql_result)) {
					ob_start();
					call_user_func_array($this->allCon[$conKey][0],array($sql_result->fetch_all(MYSQLI_ASSOC)));
					$content = ob_get_clean();
					$this->allCon[$conKey][1]->sendStatus = true;
					$this->allCon[$conKey][1]->send($content);
				} else {
					echo $link->error, "\n";
				}
				unset($this->busyCon[$conKey]);
				$this->freeCon[$conKey] = $link;
			}

			foreach ($errors as $link) {
				echo $link->error, "1\n";
				$conKey = $this->toUuid($link);
				unset($this->busyCon[$conKey]);
				$this->freeCon[$conKey] = $link;
			}

			foreach ($reject as $link) {
				printf("server is busy, client was rejected.\n", $link->connect_error, $link->error);
				$conKey = $this->toUuid($link);
				unset($this->busyCon[$conKey]);
				$this->freeCon[$conKey] = $link;
			}
			$this->excueQue();


		}



    	return;
        while(true) {
        	if($multi){
        		$this->que->getMsg(ezQue::queDBConBusy);
			}

            if(count($this->busyCon) == 0){
            	if($break)return;
            	continue;
			}
            $read = $errors = $reject = $this->busyCon;
            $re = mysqli_poll($read, $errors, $reject, $time);
            if (false === $re) {
                die('mysqli_poll failed');
            } elseif ($re < 1) {
//                continue;
                return;
            }

            foreach ($read as $link) {
                $conKey = $this->toUuid($link);
                $sql_result = $link->reap_async_query();
                if (is_object($sql_result)) {
                    ob_start();
                    call_user_func_array($this->allCon[$conKey][0],array($sql_result->fetch_all(MYSQLI_ASSOC)));
                    $content = ob_get_clean();
                    $this->allCon[$conKey][1]->sendStatus = true;
                    $this->allCon[$conKey][1]->send($content);
                } else {
                    echo $link->error, "\n";
                }
                unset($this->busyCon[$conKey]);
                $this->freeCon[$conKey] = $link;
            }

            foreach ($errors as $link) {
                echo $link->error, "1\n";
                $conKey = $this->toUuid($link);
                unset($this->busyCon[$conKey]);
                $this->freeCon[$conKey] = $link;
            }

            foreach ($reject as $link) {
                printf("server is busy, client was rejected.\n", $link->connect_error, $link->error);
                $conKey = $this->toUuid($link);
                unset($this->busyCon[$conKey]);
                $this->freeCon[$conKey] = $link;
            }
            $this->excueQue();
            return;
        }
    }
}