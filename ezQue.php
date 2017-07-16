<?php
/**
 * Created by PhpStorm.
 * User: lhe
 * Date: 2017/7/16
 * Time: 18:06
 */

class ezQue{
    private $name = null;
    private $que = null;
    public function __construct($name,$perms = 0666)
    {
        $this->name = $name;
        $key = ftok(__FILE__,'R');
        $this->que = msg_get_queue($key,$perms);
    }
    public function snedMsg($msg,$type = 0){
        msg_send($this->que,$type,$msg);
    }
    public function getMsg(){
        msg_receive($this->que, 0, $message_type, 1024, $message, false, MSG_IPC_NOWAIT);
        return $message;
    }
    public function getCount(){
        return msg_stat_queue($this->que)['msg_qnum'];
    }
}