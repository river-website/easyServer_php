<?php
/**
 * Created by PhpStorm.
 * User: win10
 * Date: 2017/7/25
 * Time: 16:41
 */

if (!function_exists('echoDebug')) {
	function echoDebug($msg){
		if(ezGLOBALS::$debug)
			echo $msg."\n";
	}
}