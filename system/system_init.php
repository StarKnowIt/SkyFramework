<?php 

if(!defined('APP_ROOT_PATH')) 
	define('APP_ROOT_PATH', str_replace('system/system_init.php', '', str_replace('\\', '/', __FILE__)));

require APP_ROOT_PATH."init.php";


if(IS_DEBUG){
	ini_set("display_errors", 1);
	error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING ^ E_DEPRECATED);
 	$GLOBALS['msg']->set_debug(true);
}else
	error_reporting(0);
	
//输出后台URL文件名称
define('URL_NAME',app_conf("URL_NAME"));
$GLOBALS['tmpl']->assign("URL_NAME",URL_NAME);

?>