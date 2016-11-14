<?php

if (PHP_VERSION >= '5.0.0'){
	$begin_run_time = @microtime(true);
}else{
	$begin_run_time = @microtime();
}
@set_magic_quotes_runtime (0);

define('MAGIC_QUOTES_GPC',get_magic_quotes_gpc()?True:False);
if(!defined('IS_CGI'))
	define('IS_CGI',substr(PHP_SAPI, 0,3)=='cgi' ? 1 : 0 );

if(!defined('_PHP_FILE_')) {
	if(IS_CGI) {
    	//CGI/FASTCGI模式下
        $_temp  = explode('.php',$_SERVER["PHP_SELF"]);
        define('_PHP_FILE_',  rtrim(str_replace($_SERVER["HTTP_HOST"],'',$_temp[0].'.php'),'/'));
	}else {
    	define('_PHP_FILE_',  rtrim($_SERVER["SCRIPT_NAME"],'/'));
    }
}

if(!defined('APP_ROOT')) {
	// 网站URL根目录
    $_root = dirname(_PHP_FILE_);
    $_root = (($_root=='/' || $_root=='\\')?'':$_root);
    $_root = str_replace("/system","",$_root);
    define('APP_ROOT', $_root  );
}

//引入时区配置及定义时间函数
if(function_exists('date_default_timezone_set')){
	if(app_conf('DEFAULT_TIMEZONE')){
		date_default_timezone_set(app_conf('DEFAULT_TIMEZONE'));
	}else{
		date_default_timezone_set('PRC');
	}
	
}
	
//引入数据库的系统配置及定义配置函数
require APP_ROOT_PATH.'system/common.php';
//end 引入时区配置及定义时间函数

require APP_ROOT_PATH.'system/define.php';
if(file_exists(APP_ROOT_PATH."public/install.lock")){
	update_sys_config();
}

$sys_config = require APP_ROOT_PATH.'system/config.php';

$distribution_cfg = array(
	"CACHE_CLIENT"	    =>	"",  //备选配置,使用到的有memcached,memcacheSASL,DBCache
	"CACHE_PORT"	    =>	"",  //备选配置（memcache使用的端口，默认为11211,DB为3306）
	"CACHE_USERNAME"    =>	"",  //备选配置
	"CACHE_PASSWORD"    =>	"",  //备选配置
	"CACHE_DB"	        =>	"",  //备选配置,用DB做缓存时的库名
	"CACHE_TABLE"	    =>	"",  //备选配置,用DB做缓存时的表名
		
	"SESSION_CLIENT"	=>	"",  //备选配置,使用到的有memcached,memcacheSASL,DBCache
	"SESSION_PORT"	    =>	"",  //备选配置（memcache使用的端口，默认为11211,DB为3306）
	"SESSION_USERNAME"	=>	"",  //备选配置
	"SESSION_PASSWORD"	=>	"",  //备选配置
	"SESSION_DB"	    =>	"",  //备选配置,用DB做缓存时的库名
	"SESSION_TABLE"	    =>	"",  //备选配置,用DB做缓存时的表名
	"SESSION_FILE_PATH"	=>	"public/session", //session保存路径(为空表示web环境默认路径)
	//"SESSION_FILE_PATH"	=>	"",
	"DB_CACHE_APP"	    =>	array(
		"index"
	),	
	//支持查询缓存的表
	"DB_CACHE_TABLES"	=>	array(
		"adv",
		"api_login",
		"article",
		"article_cate",
		"bank",
		"conf",
		"deal",
		"deal_cate",
		"faq",
		"help",
		"index_image",
		"link",
		"link_group",
		"nav",
	),   
				
	"DB_DISTRIBUTION" => array(
		// 			array(
		// 				'DB_HOST'=>'localhost',
		// 				'DB_PORT'=>'3306',
		// 				'DB_NAME'=>'o2onew1',
		// 				'DB_USER'=>'root',
		// 				'DB_PWD'=>'',
		// 			),
		// 			array(
		// 				'DB_HOST'=>'localhost',
		// 				'DB_PORT'=>'3306',
		// 				'DB_NAME'=>'o2onew2',
		// 				'DB_USER'=>'root',
		// 				'DB_PWD'=>'',
		// 			),
	), //数据只读查询的分布
	"OSS_DOMAIN"	    =>	"",  //远程存储域名
	"OSS_FILE_DOMAIN"	=>	"",	 //远程存储文件域名(主要指脚本与样式)
	"OSS_BUCKET_NAME"	=>	"",  //针对阿里oss的bucket_name
	"OSS_ACCESS_ID"	    =>	"",
	"OSS_ACCESS_KEY"	=>	"",
);

//关于分布式配置
$distribution_cfg["CACHE_TYPE"]	         = "File"; //File,Memcached,MemcacheSASL,Xcache,Db
$distribution_cfg["CACHE_LOG"]	         = false;  //是否需要在本地记录cache的key列表
$distribution_cfg["SESSION_TYPE"]	     = "File"; //"Db/MemcacheSASL/File"
$distribution_cfg['ALLOW_DB_DISTRIBUTE'] = false;  //是否支持读写分离
$distribution_cfg["CSS_JS_OSS"]	         = false; //脚本样式是否同步到oss
$distribution_cfg["OSS_TYPE"]	         = ""; //同步文件存储的类型: ES_FILE,ALI_OSS,NONE 分别为原es_file.php同步,阿里云OSS,以及无OSS分布
$distribution_cfg['DOMAIN_ROOT']	     =	'';  //域名根
$distribution_cfg['COOKIE_PATH']	     =	'/';
$distribution_cfg["ORDER_DISTRIBUTE_COUNT"]	=	"5"; //订单表分片数量
//end 分布式


//定义$_SERVER['REQUEST_URI']兼容性
if (!isset($_SERVER['REQUEST_URI'])){
	if (isset($_SERVER['argv'])){
		$uri = $_SERVER['PHP_SELF'] .'?'. $_SERVER['argv'][0];
	}else{
		$uri = $_SERVER['PHP_SELF'] .'?'. $_SERVER['QUERY_STRING'];
	}
	$_SERVER['REQUEST_URI'] = $uri;
}
filter_request($_GET);
filter_request($_POST);


if(IS_DEBUG)
	error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);
else
	error_reporting(0);


//关于session
if(!class_exists("FanweSessionHandler")){
	class FanweSessionHandler{
		private $savePath;
		private $mem;   //Memcache使用
		private $db;	//数据库使用
		private $table; //数据库使用
	
		function open($savePath, $sessionName){
			$this->savePath = APP_ROOT_PATH.$GLOBALS['distribution_cfg']['SESSION_FILE_PATH'];
			if($GLOBALS['distribution_cfg']['SESSION_TYPE']=="MemcacheSASL"){
				$this->mem = require_once APP_ROOT_PATH."system/cache/MemcacheSASL/MemcacheSASL.php";
				$this->mem = new MemcacheSASL;
				$this->mem->addServer($GLOBALS['distribution_cfg']['SESSION_CLIENT'], $GLOBALS['distribution_cfg']['SESSION_PORT']);
				$this->mem->setSaslAuthData($GLOBALS['distribution_cfg']['SESSION_USERNAME'],$GLOBALS['distribution_cfg']['SESSION_PASSWORD']);
			}elseif($GLOBALS['distribution_cfg']['SESSION_TYPE']=="Db"){
				$pconnect         = false;
				$session_client   = $GLOBALS['distribution_cfg']['SESSION_CLIENT']==""?app_conf('DB_HOST'):$GLOBALS['distribution_cfg']['SESSION_CLIENT'];
				$session_port     = $GLOBALS['distribution_cfg']['SESSION_PORT']==""?app_conf('DB_PORT'):$GLOBALS['distribution_cfg']['SESSION_PORT'];
				$session_username = $GLOBALS['distribution_cfg']['SESSION_USERNAME']==""?app_conf('DB_USER'):$GLOBALS['distribution_cfg']['SESSION_USERNAME'];
				$session_password = $GLOBALS['distribution_cfg']['SESSION_PASSWORD']==""?app_conf('DB_PWD'):$GLOBALS['distribution_cfg']['SESSION_PASSWORD'];
				$session_db       = $GLOBALS['distribution_cfg']['SESSION_DB']==""?app_conf('DB_NAME'):$GLOBALS['distribution_cfg']['SESSION_DB'];
				$this->db         = new mysql_db($session_client.":".$session_port, $session_username,$session_password,$session_db,'utf8',$pconnect);
				$this->table      = $GLOBALS['distribution_cfg']['SESSION_TABLE']==""?DB_PREFIX."session":$GLOBALS['distribution_cfg']['SESSION_TABLE'];
			}else{
				if (!is_dir($this->savePath)) {
					@mkdir($this->savePath, 0777);
				}
			}
			return true;
		}
	
		function close(){
			return true;
		}
	
		function read($id){
			$sess_id = "sess_".$id;
			if($GLOBALS['distribution_cfg']['SESSION_TYPE']=="MemcacheSASL"){
				return $this->mem->get("$this->savePath/$sess_id");
			}elseif($GLOBALS['distribution_cfg']['SESSION_TYPE']=="Db"){
				$session_data = $this->db->getRow("select session_data,session_time from ".$this->table." where session_id = '".$sess_id."'",true);
				if($session_data['session_time'] < NOW_TIME){
					return false;
				}else{
					return $session_data['session_data'];
				}
			}else{
				$file = "$this->savePath/$sess_id";
				if (filemtime($file) + SESSION_TIME < time() && file_exists($file)) {
					@unlink($file);
				}
				$data = (string)@file_get_contents($file);
				return $data;
			}
		}
	
		function write($id, $data){
			$sess_id = "sess_".$id;
			if($GLOBALS['distribution_cfg']['SESSION_TYPE']=="MemcacheSASL"){
				return $this->mem->set("$this->savePath/$sess_id",$data,SESSION_TIME);
			}elseif($GLOBALS['distribution_cfg']['SESSION_TYPE']=="Db"){
				$session_data = $this->db->getRow("select session_data,session_time from ".$this->table." where session_id = '".$sess_id."'",true);
				if($session_data){
					$session_data['session_data'] = $data;
					$session_data['session_time'] = NOW_TIME + SESSION_TIME;
					$this->db->autoExecute($this->table, $session_data,"UPDATE","session_id = '".$sess_id."'");
				}else{
					$session_data['session_id'] = $sess_id;
					$session_data['session_data'] = $data;
					$session_data['session_time'] = NOW_TIME+SESSION_TIME;
					$this->db->autoExecute($this->table, $session_data);
				}
				return true;
			}else{
				return file_put_contents("$this->savePath/$sess_id", $data) === false ? false : true;
			}
		}
	
		function destroy($id){
			$sess_id = "sess_".$id;
			if($GLOBALS['distribution_cfg']['SESSION_TYPE']=="MemcacheSASL"){
				$this->mem->delete($sess_id);
			}elseif($GLOBALS['distribution_cfg']['SESSION_TYPE']=="Db"){
				$this->db->query("delete from ".$this->table." where session_id = '".$sess_id."'");
			}else{
				$file = "$this->savePath/$sess_id";
				if (file_exists($file)) {
					@unlink($file);
				}
			}
			return true;
		}
	
		function gc($maxlifetime){
			if($GLOBALS['distribution_cfg']['SESSION_TYPE']=="MemcacheSASL"){
	
			}elseif($GLOBALS['distribution_cfg']['SESSION_TYPE']=="Db"){
				$this->db->query("delete from ".$this->table." where session_time < ".NOW_TIME);
			}else{
				foreach (glob("$this->savePath/sess_*") as $file) {
					if (filemtime($file) + SESSION_TIME < time() && file_exists($file)) {
						@unlink($file);
					}
				}
			}
			return true;
		}
		// TODO: Need to be releazied
		// This callback is executed when a new session ID is required. 
		// No parameters are provided, and the return value should be a 
		// string that is a valid session ID for your handler.
		function create_sid(){
			// 
		}
		
	}
}


//关于session的开启
if(!function_exists("es_session_start")){
	function es_session_start($session_id){
		// Set the session cookie parameters
		// @link http://www.php.net/manual/en/function.session-set-cookie-params.php
		/*
		 * Parameters:
		 * lifetime int            Lifetime of the session cookie, defined in seconds. 
		 * path string[optional]   Path on the domain where the cookie will work. Use a single slash ('/') for all paths on the domain. 
		 * domain string[optional] Cookie domain, for example 'www.php.net'. To make cookies visible on all subdomains then the domain must be prefixed with a dot like '.php.net'. 
         * secure bool[optional]   If true cookie will only be sent over secure connections. 
		 * httponly bool[optional] If set to true then PHP will attempt to send the httponly flag when setting the session cookie
		 */
		session_set_cookie_params(0,$GLOBALS['distribution_cfg']['COOKIE_PATH'],$GLOBALS['distribution_cfg']['DOMAIN_ROOT'],false,true);
		if($GLOBALS['distribution_cfg']['SESSION_FILE_PATH'] != ""
				|| $GLOBALS['distribution_cfg']['SESSION_TYPE']== "MemcacheSASL"
				|| $GLOBALS['distribution_cfg']['SESSION_TYPE']== "Db"){
			 
			$handler = new FanweSessionHandler();
			// @see http://php.net/manual/en/function.session-set-save-handler.php
			// session_set_save_handler() sets the user-level session storage 
			// functions which are used for storing and retrieving data associated 
			// with a session. This is most useful when a storage method other than 
			// those supplied by PHP sessions is preferred. i.e. Storing the session 
			// data in a local database.
			session_set_save_handler(
				array($handler, 'open'),
				array($handler, 'close'),
				array($handler, 'read'),
				array($handler, 'write'),
				array($handler, 'destroy'),
				array($handler, 'gc')
			);
		}
		if($session_id){
			// Get and/or set the current session id
			session_id($session_id);
		}
		@session_start();
	}
}


//end 引入数据库的系统配置及定义配置函数
require APP_ROOT_PATH.'system/db/db.php';
require APP_ROOT_PATH.'system/utils/es_cookie.php';
require APP_ROOT_PATH.'system/utils/es_session.php';
//es_session::start();
 
if(app_conf("URL_MODEL")==1){
	//重写模式
	$current_url = APP_ROOT;	
	if(isset($_REQUEST['rewrite_param']))
		$rewrite_param = $_REQUEST['rewrite_param'];
	else
		$rewrite_param = "";
	
	$rewrite_param = str_replace(array( "\"","'" ), array("",""), $rewrite_param);
	$rewrite_param = explode("/",$rewrite_param);
	$rewrite_param_array = array();
	foreach($rewrite_param as $k=>$param_item){
		if($param_item!='')
			$rewrite_param_array[] = $param_item;
	}	
	foreach ($rewrite_param_array as $k=>$v){
		if(substr($v,0,1)=='-'){
			//扩展参数
			$v = substr($v,1);
			$ext_param = explode("-",$v);
			foreach($ext_param as $kk=>$vv){
				if($kk%2==0){
					if(preg_match("/(\w+)\[(\w+)\]/",$vv,$matches)){
						$_GET[$matches[1]][$matches[2]] = $ext_param[$kk+1];
					}else
						$_GET[$ext_param[$kk]] = $ext_param[$kk+1];
					
					if($ext_param[$kk]!="p"){
						$current_url.=$ext_param[$kk];	
						$current_url.="-".$ext_param[$kk+1]."-";
					}
				}
			}			
		}elseif($k==0){
			//解析ctl与act
			$ctl_act = explode("-",$v);
			if($ctl_act[0]!='id'){
				
				$_GET['ctl'] = !empty($ctl_act[0])?$ctl_act[0]:"";
				$_GET['act'] = !empty($ctl_act[1])?$ctl_act[1]:"";	
		
				$current_url.="/".$ctl_act[0];	
				if(!empty($ctl_act[1]))
					$current_url.="-".$ctl_act[1]."/";	
				else
					$current_url.="/";	
			}else{
				//扩展参数
				$ext_param = explode("-",$v);
				foreach($ext_param as $kk=>$vv){
					if($kk%2==0){
						if(preg_match("/(\w+)\[(\w+)\]/",$vv,$matches)){
							$_GET[$matches[1]][$matches[2]] = $ext_param[$kk+1];
						}else
							$_GET[$ext_param[$kk]] = $ext_param[$kk+1];
						
						if($ext_param[$kk]!="p"){
							if($kk==0)
								$current_url.="/";
							$current_url.=$ext_param[$kk];	
							$current_url.="-".$ext_param[$kk+1]."-";	
						}
					}
				}
			}
		}elseif($k==1){
			//扩展参数
			$ext_param = explode("-",$v);
			foreach($ext_param as $kk=>$vv){
				if($kk%2==0){
					if(preg_match("/(\w+)\[(\w+)\]/",$vv,$matches)){
						$_GET[$matches[1]][$matches[2]] = $ext_param[$kk+1];
					}else
						$_GET[$ext_param[$kk]] = $ext_param[$kk+1];
					
					if($ext_param[$kk]!="p"){
						$current_url.=$ext_param[$kk];	
						$current_url.="-".$ext_param[$kk+1]."-";
					}
				}
			}			
		}
	}
	$current_url = substr($current_url,-1)=="-"?substr($current_url,0,-1):$current_url;	

}
unset($_REQUEST['rewrite_param']);
unset($_GET['rewrite_param']);



//定义缓存
require APP_ROOT_PATH.'system/cache/Cache.php';
$cache = CacheService::getInstance();
require_once APP_ROOT_PATH."system/cache/CacheFileService.php";
$fcache = new CacheFileService();  //专用于保存静态数据的缓存实例
$fcache->set_dir(APP_ROOT_PATH."public/runtime/data/");
//end 定义缓存

//定义DB
define('DB_PREFIX', app_conf('DB_PREFIX')); 
if(!file_exists(APP_ROOT_PATH.'public/runtime/app/db_caches/'))
	mkdir(APP_ROOT_PATH.'public/runtime/app/db_caches/',0777);
$pconnect = false;
$db = new mysql_db(app_conf('DB_HOST').":".app_conf('DB_PORT'), app_conf('DB_USER'),app_conf('DB_PWD'),app_conf('DB_NAME'),'utf8',$pconnect);
//end 定义DB


//定义模板引擎
require  APP_ROOT_PATH.'system/template/template.php';
if(!file_exists(APP_ROOT_PATH.'public/runtime/app/tpl_caches/'))
	mkdir(APP_ROOT_PATH.'public/runtime/app/tpl_caches/',0777);	
if(!file_exists(APP_ROOT_PATH.'public/runtime/app/tpl_compiled/'))
	mkdir(APP_ROOT_PATH.'public/runtime/app/tpl_compiled/',0777);
$tmpl = new AppTemplate();
//end 定义模板引擎


$_REQUEST = array_merge($_GET,$_POST);
filter_request($_REQUEST);

require APP_ROOT_PATH.'system/utils/message_send.php';
$msg = new message_send();


define("DEFAULT_ACTION_NAME",'index');
?>