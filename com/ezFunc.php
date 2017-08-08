<?php
/**
 * Created by PhpStorm.
 * User: win10
 * Date: 2017/7/25
 * Time: 16:41
 */
date_default_timezone_set("PRC");
if (!function_exists('ezServerLog')) {
	function ezServerLog($msg){
		if(ezGLOBALS::$log){
		    $date = date('Y-m-d',time());
		    $file = ezGLOBALS::$runTimePath."log-$date.log";
		    file_put_contents($file,ezGLOBALS::$processName." -> $msg\n",FILE_APPEND);
        }
	}
}

if (!function_exists('ezDebugLog')) {
    function ezDebugLog($msg){
        if(ezGLOBALS::$debug){
            ezServerLog($msg);
        }
    }
}