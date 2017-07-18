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
    public function sendMsg($type,$data){
    	var_dump($data);
    	echo "\n";
    	$a = json_encode($data);
    	echo $a."\n";
    	return;
    	var_dump(serialize($data));
		msg_send($this->que,$type,serialize($data),false);

		msg_receive($this->que, 0, $message_type, 8196, $message, false, MSG_IPC_NOWAIT);
//		var_dump(unserialize($message));
    }
    public function getMsg($type){
        msg_receive($this->que, $type, $message_type, 1024, $message, true, MSG_IPC_NOWAIT);
        return $message;
    }
    public function getCount(){
        return msg_stat_queue($this->que)['msg_qnum'];
    }
}