<?php

class ezAsynDB{
    private $event = null;
    private $connectCount = 20;
    private $allCon = array();
    private $busyCon = array();
    private $freeCon = array();
    private $sqlQue = array();

    public function __construct($event){
        $this->event = $event;
    }

    public function add($sqlCon){
        if(count($this->allCon)<$this->connectCount){
            var_dump($sqlCon);
            $conKey = (int)$sqlCon;
            $this->allCon[$conKey] = $sqlCon;
            $this->freeCon[$conKey] = $sqlCon;
            $this->event->add($sqlCon,ezEvent::read,array($this,'onMessage'));
        }
    }
    public function del($sqlCon){
        $conKey = (int)$sqlCon;
        if(!empty($this->busyCon[$conKey]))
            return false;
        if(!empty($this->allCon[$conKey]))
            unset($this->allCon[$conKey]);
        if(!empty($this->freeCon[$conKey]))
            unset($this->freeCon[$conKey]);
        $this->event->del($sqlCon,ezEvent::read);
        return true;
    }
    public function excute($sql,$asyn = true){
        if(count($this->freeCon) == 0)
            $this->sqlQue[] = $sql;
        else{
            $sqlCon = array_shift($this->freeCon);
            $row = mysqli_query($sqlCon, $sql,MYSQLI_ASYNC);
            $this->busyCon[] = $sqlCon;
        }
    }
    private function onMessage($con){
        $res = @mysqli_reap_async_query($con);
        return $res->fetch_all(MYSQLI_ASSOC);
    }
}