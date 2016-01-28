<?php

defined('BASE_DIR') or define('BASE_DIR','/');

///////////////////////////////////////////////////////////////////////
// Configuration
if(defined('DEBUG') && DEBUG) {
	ini_set('display_errors', 'On');
	ini_set('display_startup_errors', 'On');
	error_reporting(E_ALL);
}

defined('DIE_ON_DB_ACCESS') or define('DIE_ON_DB_ACCESS',true);

///////////////////////////////////////////////////////////////////////
// Prevent any possible XSS attacks via $_GET.
if(!defined('JUMP_XSS_CONTROL')) {
	foreach ($_GET as $check_url) {
		$check_url = strtolower(strval($check_url));
	    if ((preg_match("<[^\>]*script*\"?[^\>]*>", $check_url)) || (preg_match("<[^\>]*object*\"?[^\>]*>", $check_url)) ||
	        (preg_match("<[^\>]*iframe*\"?[^\>]*>", $check_url)) || (preg_match("<[^\>]*applet*\"?[^\>]*>", $check_url)) ||
	        (preg_match("<[^\>]*meta*\"?[^\>]*>", $check_url)) || (preg_match("<[^\>]*style*\"?[^\>]*>", $check_url)) ||
	        (preg_match("<[^\>]*\<form*\"?[^\>]*>", $check_url)) ) {
	    //if(DEBUG) {
	    	echo "<h1>Due to ".$check_url."</h1>";
	    //}
	    die('Access denied!!!');
	    }
		unset($check_url);
	}
}
///////////////////////////////////////////////////////////////////////


// Get DBO connection
require_once(dirname(__FILE__).'/framework/database.php');
	
function _getDBO($options) {
	/*$options = array(
		'driver' => 'mysql',
		'host' => 'localhost',
		'user' => 'DBUSER',
		'password' => 'DBPASS',
		'database' => 'DBNAME',
		'prefix' => 'jos_'
	);
	$options = array(
		'driver' => 'sqllite',
		'file' => './dbfile'
	);*/
	$toRet = &JDatabase::getInstance( $options );
	if( DIE_ON_DB_ACCESS && !is_object($toRet) )	die($options['user'].'@'.$options['host'].' '.$toRet);
	//if( is_object($toRet) && DEBUG && $toRet->getErrorNum() > 0)	echo 'JDatabase::getInstance: Could not connect to database <br />' . 'joomla.library:'.$toRet->getErrorNum().' - '.$toRet->getErrorMsg();
	return $toRet;
}

//richiedo le funzioni di servizio
require_once(dirname(__FILE__).DIRECTORY_SEPARATOR.'function.php');

if (php_sapi_name() != "cli") {
	header( 'Expires: Mon, 26 Mar 1988 08:30:00 GMT' );
	header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
	header( 'Cache-Control: no-store, no-cache, must-revalidate' );
	header( 'Cache-Control: post-check=0, pre-check=0', false );
	header( 'Pragma: no-cache' );
}

///////////////////////////////////////////////////////////////////////

function ffflush() {
	flush();
	ob_flush();
}

?>
