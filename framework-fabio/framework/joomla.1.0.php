<?php

function sessionCookieValue( $id=null , $session_type=2 ) {
	$type 		= $session_type;
	$browser 	= @$_SERVER['HTTP_USER_AGENT'];
	switch ($type) {
		case 2:
		// 1.0.0 to 1.0.7 Compatibility
		// lowest level security
			$value 			= md5( $id . $_SERVER['REMOTE_ADDR'] );
			break;
		case 1:
		// slightly reduced security - 3rd level IP authentication for those behind IP Proxy
			$remote_addr 	= explode('.',$_SERVER['REMOTE_ADDR']);
			$ip				= $remote_addr[0] .'.'. $remote_addr[1] .'.'. $remote_addr[2];
			$value 			= md5( $GLOBALS['mosConfig_secret'] . md5( $id . $ip . $browser ) );
			break;
		default:
		// Highest security level - new default for 1.0.8 and beyond
			$ip				= $_SERVER['REMOTE_ADDR'];
			$value 			= md5( $GLOBALS['mosConfig_secret'] . md5( $id . $ip . $browser ) );
			break;
	}
	return $value;
}


/**
 * Abstract Table class
 *
 * Parent classes to all tables.
 *
 * @abstract
 * @package 	Joomla.Framework
 * @subpackage	Table
 * @since		1.0
 * @tutorial	Joomla.Framework/jtable.cls
 */
if(!class_exists('mosSession')) {
class mosSession extends JTable
{
	var $username;
	var $session_id;
	var $time;
	var $guest;
	var $userid;
	var $usertype;
	var $gid;
	var $jaclplus;
}
}

function doJoomla_1_0Validation($database,$mosConfig_live_site,$mosConfig_session_type) {
	//session_start();
	//session_id($_COOKIE['PHPSESSID']);
	$user = 0;
	$cookiename = md5('site'.substr(strstr($mosConfig_live_site,'//'),2));
	//$cookiename = md5('site'.$mosConfig_live_site);
	//echo 'site '.$mosConfig_live_site."  => ".substr(strstr($mosConfig_live_site,'//'),2).' => '.$cookiename.' = '.(isset($_COOKIE[$cookiename])?$_COOKIE[$cookiename]:'nd').'<br />';

	if(isset($_COOKIE) && is_array($_COOKIE) && isset($_COOKIE[$cookiename])) {
		$cookieval = $_COOKIE[$cookiename];
		$cookievalue = sessionCookieValue($cookieval,$mosConfig_session_type);
		$session = new mosSession('#__session', 'session_id', $database );
		if($session->load($cookievalue)) {
			//echo "<br />".$cookievalue." time ".$session->time." ".date('H:i:s',$session->time)." ora &egrave; ".date('H:i:s')." => ".strtotime(date('H:i:s'));
			//echo "<br />".print_r($session);
			if(intval($session->userid) > 0) {
				$user = intval($session->userid);
			}
			$session->time = strtotime(date('H:i:s'));
			$session->store();
		}
	}//die();
	return $user;
}

?>
