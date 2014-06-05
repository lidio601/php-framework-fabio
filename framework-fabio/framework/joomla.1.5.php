<?php

define('JPATH_BASE',realpath(dirname(__FILE__))); 
//.DS.'..'.DS.'..'.DS.'..'.DS.'..'.DS));
define('JPATH_ROOT',JPATH_BASE);

function doJoomla_1_5Validation($dbe,$conf) {
	$cookie_name = md5(getHash('administrator'));
	$session_id = 0;
	if( isset($_COOKIE) && is_array($_COOKIE) && isset($_COOKIE[$cookie_name]) ) {
		$session_id = strval($_COOKIE[$cookie_name]);
	}
	$dbe->setQuery("SELECT `userid`
	FROM `#__session`
	WHERE `session_id` = '".$session_id."'
		AND `guest` = '0'
		AND ( `time` + ".(intval($conf->lifetime)*60)." ) - UNIX_TIMESTAMP() > 0
	LIMIT 1");
	session_id( $session_id );
	session_start();
	return intval($dbe->loadResult());
}

function getHash( $seed ) {
	global $conf;
	//$conf =& JFactory::getConfig();
	$ris =  md5( $conf->secret .  $seed  );
	return $ris;
}

?>
