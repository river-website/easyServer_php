<?php
/**
 * Created by PhpStorm.
 * User: lhe
 * Date: 2017/7/16
 * Time: 18:06
 */

class ezQue{
	const queDBConFree  = 1;
	const queDBConBusy  = 2;
	const queDBSql 		= 3;
	const queEventDone  = 4;

	private $que = null;
	private $curPid = null;
	private $queIDList = array();
	private $curQueID = 0;
	private $queType = null;

	public function __construct($perms = 0666){
		$key = ftok(__FILE__,'R');
		if(msg_queue_exists($key))msg_remove_queue(msg_get_queue($key,$perms));
		$this->que = msg_get_queue($key,$perms);
		$this->curPid = getmygid();
		$this->queType = ezQue::queEventDone.$this->curPid;
	}

	public function sendMsg($type,$data,$serialize = false,$func = null,$args = null,$block = true){
		if($serialize) {
			$msg['pid'] = $this->curPid;
			$msg['queID'] = $this->curQueID++;
			$msg['data'] = $data;
		}else $msg = $data;
		msg_send($this->que,$type,$msg,true, $block);
		if(!empty($func))
			$this->queIDList[$msg['queID']] = array($func,$args);
	}

	public function getMsg($type = 0, $wait = false){
		if($wait == true)$flags = MSG_EAGAIN;
		else $flags = MSG_IPC_NOWAIT;
		msg_receive($this->que, $type, $message_type, 8192, $message, true, $flags);
		return $message;
	}

	public function onQueEventDone(){
		while(true){
			$msg = $this->getMsg($this->queType,false);
			if(empty($msg))return;
			if(empty($msg['queID'])||empty($msg['data'])||empty($this->queIDList[$msg['queID']]))continue;
			$info = $this->queIDList[$msg['queID']];
			var_dump($info);
			call_user_func_array($info[0],array($msg['data'],$info[1]));
		}
	}
}