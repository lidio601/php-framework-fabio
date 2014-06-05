<?php

require('common.php');

header('Content-type: text/xml; charset=UTF-8');
$dbe->getTableList();
echo SqlToXML($dbe,false);

?>
