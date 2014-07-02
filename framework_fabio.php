<?php
/*
You can include this path
>	dirname(__FILE__).DIRECTORY_SEPARATOR 
in your php include_path setting so you can easly
include this library by writing:
>	require_once('framework_fabio.php');
*/
require_once(dirname(__FILE__).DIRECTORY_SEPARATOR.'framework-fabio'.DIRECTORY_SEPARATOR.'common.php');

require_once('css_packer.php');
require_once('recaptchalib.php');
require_once('plist_parser.php');
require_once('javascript_packer.php');

?>