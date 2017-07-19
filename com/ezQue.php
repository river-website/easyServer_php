<?php
/**
 * Created by PhpStorm.
 * User: lhe
 * Date: 2017/7/16
 * Time: 18:06
 */

class ezQue{
	const queDBConFree = 1;
	const queDBSql 		= 2;

    private $que = null;

    public function __construct($perms = 0666){
        $key = ftok(__FILE__,'R');
        $this->que = msg_get_queue($key,$perms);
    }
    public function sendMsg($type,$data){
		msg_send($this->que,$type,$data);
    }
    public function getMsg($type = 0){
        msg_receive($this->que, $type, $message_type, 8192, $message, true, MSG_IPC_NOWAIT);
        return $message;
    }
}