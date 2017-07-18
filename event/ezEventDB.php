<?php

class ezEventDB{
    const queDBFree = 1;
    const queDBSql = 2;

    private $event = null;
    private $connectCount = 20;
    private $allCon = array();
    private $busyCon = array();
    private $freeCon = array();
    private $sqlQue = array();
    private $que = null;
    static public $pubName = 'mysqlConnectList';
    public function __construct($event){
        $this->event = $event;
        $this->que = new ezQue('db');

    }

    static public function getInterface(){
        if(is_file(self::pubName)){
            $fp = fopen(self::pubName,'w+');

        }
    }
    static public function createInterface(){
        $DB = new ezAsynDB();
        file_put_contents(‘mysqlConnect’,serialize($DB));
    }
    public function add($sqlCon){
        if($this->que->getCount()<$this->connectCount){
            $this->que->sendMsg(ezEventDB::queDBFree,$sqlCon);
        }
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
        $sqlCon = $this->que->getMsg(ezEventDB::queDBFree);
        $row = mysqli_query($sqlCon,$sql);
        return $row->fetch_all(MYSQLI_ASSOC);


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

    public function loop(){
        while(true) {
            if(count($this->busyCon) == 0)return;
            $read = $errors = $reject = $this->busyCon;
            $re = mysqli_poll($read, $errors, $reject, 0.02);
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