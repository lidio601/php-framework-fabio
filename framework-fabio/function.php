<?php

define('LABEL_NEW',' - richiesto - ');

function detect_mobile() {
	// https://developer.mozilla.org/en-US/docs/Mobile/Viewport_meta_tag
	return isset($_SERVER['HTTP_USER_AGENT']) && preg_match('/(alcatel|amoi|android|avantgo|blackberry|benq|cell|cricket|docomo|elaine|htc|iemobile|iphone|ipad|ipaq|ipod|j2me|java|midp|mini|mmp|mobi|motorola|nec-|nokia|palm|panasonic|philips|phone|playbook|sagem|sharp|sie-|silk|smartphone|sony|symbian|t-mobile|telus|up\.browser|up\.link|vodafone|wap|webos|wireless|xda|xoom|zte)/i', $_SERVER['HTTP_USER_AGENT']);
}

/**
 * Determine if supplied string is a valid GUID
 *
 * @param string $md5 String to validate
 * @return boolean
 */
function isValidMd5($md5)
{
    return !empty($md5) && preg_match('/^[a-f0-9]{32}$/', $md5);
}

/*
 *	function varGET ( $name , [$default] ) : string
 *	prende la variabile con nome $name dall'array $_GET
 *	se non viene trovata, restituisco il valore di $default
 *	@param name	il nome della variabile GET da cercare
 *	@param default	valore sostituitivo da assegnare alla variabile in uscita
 */
function varGET($name,$default='') {
	return isset($_REQUEST[$name])&&strlen(strval($_REQUEST[$name])) ? $_REQUEST[$name] : $default;
}

/*
 *	function checkPermission ( $feature , [$mode] ) : boolean
 *	Sfrutta le tabelle del Poros Core per derminare se la $feature
 *	passata esiste nella tabella per il componente corrente identificato
 *	dal nome contenuto nella costante COMPONENT_NAME.
 *	opzionalmente si può passare il tipo di accesso richiesto.
 *	se l'utente corrente non è loggato o non possiede i permessi richiesti
 *	restituisco l'xml di errore
 *	@param feature	Nome della feature del componente
 *	@param mode	tipo di accesso richiesto; valore fra READ, UPD, INS, DEL
 *	@see poros_core component
 *	@see autenticazione in joomla cms
 *	@see notifyXML (local function)
 */
function checkPermission($feature,$mode='READ',$only_check=false) {
	if($feature=='menu')	return true;
	global $database, $user, $my;
	if( is_object($my) ) {
		$user = $my->id;
	}
	$database->setQuery("SELECT `ID_POROS_CORE_FEATURE`
	FROM `poros_core_feature`
	WHERE `POROS_CORE_FEATURE` = '".$feature."'
	AND `ID_POROS_CORE_COMPONENT` = (SELECT ID_POROS_CORE_COMPONENT FROM `poros_core_component` WHERE POROS_CORE_COMPONENT = '".COMPONENT_NAME."' LIMIT 1)
	LIMIT 1");
	$id_feature = intval($database->loadResult());
	if($id_feature > 0) {	
		if(count(explode('|',$mode))>1) {
			# INS|UPD|DEL to
			# INS '`,`PERM_' UPD '`,`PERM_' DEL
			$mode = str_replace('|','`,`PERM_',$mode);
		}
		$database->setQuery("SELECT `".str_replace('PERM_PERM_','PERM_','PERM_'.$mode)."`
		FROM `poros_core_permission`
		JOIN `poros_core_cat`
			ON `poros_core_cat`.`ID_POROS_CORE_CAT` = `poros_core_permission`.`ID_POROS_CORE_CAT`
		WHERE `ID_POROS_CORE_FEATURE` = '".$id_feature."'
		AND `poros_core_cat`.`ID_POROS_CORE_CAT` = (SELECT ID_POROS_CORE_CAT FROM `poros_core_user` WHERE `id` = '".$user."' LIMIT 1)
		LIMIT 1");
		$perm = $database->loadObjectList();
		if(count($perm)==1) {
			$perm = current($perm);
			foreach($perm as $k=>$p) {
				if($p == 'false') {
					if(!$only_check) {
						notifyXML(403,'Access denied to '.$feature.' for '.strtolower(str_replace('PERM_','',$k)).'!');
					} else {
						return false;
					}
				}
			}
			return true;
		}
		//if($perm == 'true') return true;
		else {
			//if(DEBUG)	echo $database->getQuery();
			if(!$only_check) {
				notifyXML(403,'Access denied to '.$feature.' for '.strtolower($mode).'!');
			} else {
				return false;
			}
		}
	}
	//if(DEBUG)	echo $database->getQuery();
	if(!$only_check) {
		notifyXML(403,'Access denied to unknown feature '.$feature.'!');
	} else {
		return false;
	}
}

/*
 *	function describeQuerytoXML ( $tablefrom , $dbe , [$id] ) : XMLString
 *	restituisce una stringa XML che permette descrive la tabella $tablefrom
 *	reperibile tramite l'oggetto JDatabase $dbe passato.
 *	Opzionalmente è possibile passare il parametro $id per caricare nella
 *	descrizione XML anche i valori del record associato al record con id $id.
 *	Il campo ID viene identificato convenzionalmente come il primo campo
 *	descritto nella query di CREATE TABLE $tablefrom.
 *	@param tablefrom	nome della tabella di cui si desidera caricare l'XML descrittivo
 *	@param dbe	JDatabase object riguardante una connessione Mysql attiva
 *	@param id	permette di caricare nell'XML i valori dei campi memorizzati nel record con id passato
 *	@see SHOW FULL FIELD FROM MySQL Query
 *	@see SHOW CREATE TABLE Mysql Query
 *	@see notifyXML (local function)
 */
function describeQuerytoXML($tablefrom,$dbe,$id=0,$columns=array()) {

	$ris = "";
	$values = array();
	$dbe->setQuery("SHOW FULL FIELDS FROM `".$tablefrom."`");
	$rows = $dbe->loadObjectList();
	# ricavo le opzioni dalla tabella relazionata
	$dbe->setQuery("SHOW CREATE TABLE `".$tablefrom."`");
	$sqlcreate = $dbe->loadAssocList();
	if(count($sqlcreate)==1) {
		$sqlcreate = current($sqlcreate);
		$sqlcreate = $sqlcreate['Create Table'];
	} else {
		notifyXML(404,'Could not find table definitio for '.$tablefrom);
	}
	//print_r($rows);	die();
	if( !count($rows) && DEBUG ) {
		/*header('Content-type: text/plain; charset=UTF');
		echo $dbe->getErrorMsg();
		echo $dbe->getQuery();
		die();*/
		notifyXML(400,$dbe->getErrorMsg()."<br />".$dbe->getQuery());
	}
	# prendo i valori dei campi del record con id passato
	if($id > 0) {
		$key_id = current($rows)->Field;
		$dbe->setQuery("SELECT * FROM `".$tablefrom."` WHERE `".$key_id."` = '".$id."' LIMIT 1");
		if(count($dbe->loadObjectList())==1) {
			$values = current($dbe->loadAssocList());
		} else {
			notifyXML(404,$dbe->getQuery().'Record #'.$id.' not found!');
		}
	}
	$ris .= "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";
	$ris .= "<rows stat=\"OK\">\n";

	$type = implode('.',array_slice(explode('.',basename($_SERVER['REQUEST_URI'])),0,1));
	if($type == 'form') {
		$feature = implode('.',array_slice(explode('.',basename($_SERVER['REQUEST_URI'])),-2,1));
		if(checkPermission($feature,'UPD',true) && defined('SHOW_EDIT_BUTTON') && SHOW_EDIT_BUTTON )
			$ris .= "\t<row type=\"button\" label=\"\" id=\"form_".implode('.',array_slice(explode('.',basename($_SERVER['REQUEST_URI'])),1,-1))."\"/>\n";
		if(checkPermission($feature,'DEL',true))
			$ris .= "\t<row type=\"button\" label=\"\" id=\"del_".implode('.',array_slice(explode('.',basename($_SERVER['REQUEST_URI'])),1,-1))."\"/>\n";
			
	}

	for($i=0;$i<count($rows);$i++) {
		if(count($columns) && !in_array($rows[$i]->Field,$columns)) {
			continue;
		}
		$ris .= "\t<row ";
		$type = $rows[$i]->Type;
		if(strpos(' '.$type,'(')!=false) {
			$type = trim(substr($type,0,strpos($type,'(')));
			$type = strtolower($type);
			if($type == 'enum') {
				$val = $rows[$i]->Type;
				$val = substr($val,strpos($val,'('));
				$val = trim($val,'()');
				$val = str_replace("'","",$val);
				$val = explode(',',$val);
				for($j=0;$j<count($val);$j++) $val[$j] = $val[$j].'='.$val[$j];
				$val = implode(',',$val);
				$ris .= "options=\"".$val."\" ";
			}
		}
		if($rows[$i]->Key == 'MUL' && strpos(' '.$sqlcreate,'FOREIGN KEY (`'.$rows[$i]->Field.'`)') != false) {
			//print_r($sqlcreate);
			# individuo la riga che mi interessa
			$sqlcreate2 = substr($sqlcreate,strpos($sqlcreate,'CONSTRAINT'));
			$sqlcreate2 = substr($sqlcreate2,strpos($sqlcreate2,'FOREIGN KEY (`'.$rows[$i]->Field.'`)'));
			$sqlcreate2 = substr($sqlcreate2,0,strpos($sqlcreate2,"\n"));
			# estraggo il riferimento a tabella e campo FOREIGN
			$tableto = substr($sqlcreate2,strpos($sqlcreate2,'REFERENCES ')+strlen('REFERENCES '));
			$tableto = substr($tableto,0,strpos($tableto,' '));
			$tableto = trim($tableto,' `');
			
			# gestione chiavi "foreign key"
			$type = 'selection';
			$ris .= "options=\"";
			# estraggo il nome del campo chiave nella tabella FOREIGN
			$fieldto = substr($sqlcreate2,strpos($sqlcreate2,$tableto)+strlen($tableto));
			$fieldto = trim($fieldto,' `(');
			$fieldto = substr($fieldto,0,strpos($fieldto,' '));
			$fieldto = trim($fieldto,' `()');
			# estraggo i valori storati nella tabella
			$dbe->setQuery("SELECT * FROM `".$tableto."` ORDER BY `".$fieldto."` ASC");
			$options = $dbe->loadAssocList();
			# estraggo il nome del campo da usare come label
			// uso il campo successivo a quello della chiave
			$option = current($options);
			$labelto = -1;
			foreach(array_keys($option) as $opt) {
				if($labelto == '')	$labelto = $opt;
				if($opt == $fieldto)	$labelto = '';
			}
			if($labelto == '')	$labelto = current(array_keys($option));
			if($tableto == 'operatore')	$labelto = 'COGNOME';
			if($tableto == 'anagrafica') $labelto = 'RAGIONE_SOCIALE';
			/*if(DEBUG) {
				echo "<br /><strong>sqlcreate: ".$sqlcreate2;
				echo "<br /><strong>tableto: ".$tableto;
				echo "<br /><strong>fieldto: ".$fieldto;
				echo "<br /><strong>labelto: ".$labelto;
				echo "<br />".print_r($options,1);
				die();
			}*/
			reset($options);
			foreach($options as $option) {
				$ris .= $option[$fieldto].'='.str_replace("&","&amp;",str_replace("&amp;","&",str_replace('"','',str_replace("'","\'",$option[$labelto])))).',';
			}
			$ris = substr($ris,0,-1);
			//$sqlcreate = $db->loadResult
			$ris .= "\" ";
		}
		if(substr($type,0,3) == 'int') {
			$type = 'int';
		}
		switch($type) {
			case 'text':
			case 'longtext':
			case 'mediumtext':
				$type = 'textarea';
				break;
			case 'decimal':
			case 'float':
			case 'int':
				$type = 'number';
				break;
			case 'datetime':
			case 'timestamp':
			case 'date':
				$type = 'calendar';
				break;
			case 'enum':
				$type = 'selection';
				break;
			case 'tinyint':
				$type = 'selection';
				$ris .= "options=\"1=yes,0=no\" ";
				break;
			case 'varchar':
				$type = 'text';
				break;
			default:
				//$type = 'text';
		}
		//}
		$ris .= "type=\"".$type."\" ";
		if($rows[$i]->Comment != '') {
			$ris .= "label=\"".$rows[$i]->Comment."\" ";
		} else {
			if($rows[$i]->Field=="ID") {
				$ris .= "label=\"".$rows[$i]->Field."\" ";
			} else {
				$ris .= "label=\"".ucwords(str_replace("_"," ",strtolower($rows[$i]->Field)))."\" ";
			}
		}
		$ris .= "id=\"".$rows[$i]->Field."\" ";
		if($id > 0 && isset($values[$rows[$i]->Field]) && $values[$rows[$i]->Field] != '') {
			$ris .= "value=\"".$values[$rows[$i]->Field]."\" ";
		} elseif($rows[$i]->Default!='') {
			$ris .= "value=\"".$rows[$i]->Default."\" ";
		}
		$ris .= "/>\n";
				
	}
	$ris .= "</rows>\n";
	return $ris;
}

/*
 *	function notifyXML ( $code , $message ) : void
 *	permette di terminare rapidamente il programma corrente
 *	per notificare un errore o l'esito positivo di un'operazione.
 *	Fornisce un metodo di notifica tramite XMLString universale.
 *	@param code	codice HTTP dell'errore
 *	@param message	testo della notifica da includere nella stringa XML
 *	@see RFC 2616 for HTTP Status Code
 *	@link http://www.faqs.org/rfcs/rfc2616
 */
function notifyXML($code,$message) {
	$c = $code;
	//$code = 200;
	header("HTTP/1.0 ".$code." ".status_from_code($code),true);//,$code);
	header("Status: ".$code,true);
	$code = $c;
	header('Content-type: text/xml; charset=UTF-8',true);
	echo "<rows stat=\"".status_from_code($code)."\">\n";
	echo "	<message code=\"".$code."\"><![CDATA[ ".addslashes($message)." ]]></message>\n";
	echo "</rows>";
	die();
}

/*
 *	function status_from_code ( $code ) : string
 *	restituisce il testo corrispondente all'HTTP STATUS CODE passato
 *	@param code	codice dello status HTTP
 *	@see RFC 2616 for HTTP Status Code
 *	@link http://www.faqs.org/rfcs/rfc2616
 */
function status_from_code($code) {
	$buf = array(
		100 => 'Continue',	101 => 'Switching Protocols',	200 => 'OK',	201 => 'Created',	202 => 'Accepted',	203 => 'Non-Authoritative Information',
		204 => 'No Content',	205 => 'Reset Content',	206 => 'Partial Content',	300 => 'Multiple Choices',	301 => 'Moved Permanently',	302 => 'Found',
		303 => 'See Other',	304 => 'Not Modified',	305 => 'Use Proxy',	307 => 'Temporary Redirect',	400 => 'Bad Request',	401 => 'Unauthorized',
		402 => 'Payment Required',	403 => 'Forbidden',	404 => 'Not Found',	405 => 'Method Not Allowed',	406 => 'Not Acceptable',
		407 => 'Proxy Authentication Required',	408 => 'Request Time-out',	409 => 'Conflict',	410 => 'Gone',	411 => 'Length Required',
		412 => 'Precondition Failed',	413 => 'Request Entity Too Large',	414 => 'Request-URI Too Large',	415 => 'Unsupported Media Type',
		416 => 'Requested range not satisfiable',	417 => 'Expectation Failed',	500 => 'Internal Server Error',	501 => 'Not Implemented',
		502 => 'Bad Gateway',	503 => 'Service Unavailable',	504 => 'Gateway Time-out',	505 => 'HTTP Version not supported'
	);
	return isset($buf[$code]) ? $buf[$code] : "";
}

/*
 *	function bindObjtoQuery ( $db , $table , $tobind , [ [$exec] , $id ] ) : string
 *	restituisce una query SQL per l'inserimento o l'aggiornamento di un record
 *	nella tabella $table. La struttura della stessa viene reperita attraverso
 *	l'oggetto JDatabase $db passato e opzionalmente si può eseguire direttamente la query
 *	inline passando l'argomento $exec con valore true.
 *	il parametro $id serve per specificare se si deve effettuare una query di insert
 *	o di update. N.B. convenzionalmente il primo campo di una tabella viene usato come
 *	campo ID principale della stessa; per questo motivo non viene modificato con l'eventuale
 *	valore presente nell'array $tobind.
 *	@param db	JDatabase object che identifica una connessione attiva a un server Mysql
 *	@param table	nome della tabella di cui creare la query di insert/update
 *	@param tobind	array associativo dove sono contenuti i valori dei campi della query
 *	@param exec	specifica se eseguire inline la query oppure restituirla semplicemente
 *	@param id	identifica un record su cui si vuole effettuare la query di UPDATE
 */
function bindObjtoQuery($db,$table,$tobind,$exec=false,$id=0) {
	$ris = "";
	$err = "";
	$values = array();
	$db->setQuery("SHOW FULL FIELDS FROM `".$table."`");
	$rows = $db->loadObjectList();
	if( !count($rows) && DEBUG ) {
		notifyXML(400,$db->getErrorMsg()."<br />".$db->getQuery());
	}
	$notfirst = false;
	foreach($rows as $row) {
		if( substr($row->Field,0,2) == "ID" || $row->Key == 'MUL' || substr($row->Type,0,4)=='enum') {
			if(isset($tobind[$row->Field]) && strpos(' '.$tobind[$row->Field],')') > 0) {
				$tobind[$row->Field] = substr($tobind[$row->Field],0,strpos($tobind[$row->Field],')'));
			}
		}
		if( isset($tobind[$row->Field]) && $row->Default != 'NULL') {
			$values[$row->Field] = isset($tobind[$row->Field])&&strval($tobind[$row->Field])!=""&&$notfirst ? ($tobind[$row->Field]!=LABEL_NEW ? $tobind[$row->Field] : $row->Default) : $row->Default;
			if($values[$row->Field]=='')	$values[$row->Field] = 'NULL';
		}
		if(!$notfirst)	$notfirst = $row->Field;
	}
	///////////////////////////////////////////////////////////////////////////
	# creo le query di insert o update
	if($id > 0) {
		# query di update
		$ris .= "UPDATE `".$table."` SET ";
		foreach($values as $k => $v) {
			if($v == LABEL_NEW)	$v = '';
			if($v == 'NULL' || $v == '') {
				$ris .= "`".$k."` = NULL, \n";
			} else {
				$ris .= "`".$k."` = '".$v."', \n";
			}
		}
		$ris = substr($ris,0,-3)."\n WHERE `".$notfirst."` = '".$id."' LIMIT 1";
	} else {
		# query di update
		$ris .= "INSERT INTO `".$table."`( `".implode('`, `',array_keys($values))."`) VALUES ( '".implode("', '",$values)."')";
	}
	$ris = str_replace("'NULL'","NULL",$ris);
	///////////////////////////////////////////////////////////////////////////
	# in base al parametro <exec> decido se eseguire direttamente le query
	if($exec) {
		$sql = $ris;
		if($id > 0) {
			/////////////////////////////////////////////////////
			# Eseguo la query di UPDATE
			$db->setQuery($sql);
			$db->query();
			if($db->getErrorNum() == 0) {
				notifyXML(200,'Succesfully updated');
			} else {
				if(DEBUG) {
					notifyXML(400,$db->getErrorNum().') '.$db->getErrorMsg()."<br />".$db->getQuery());
				} else {
					switch($db->getErrorNum()) {
						case 1048:
							$cname = $db->getErrorMsg();
							$cname = substr($cname,strpos($cname,'Column ')+strlen('Column '));
							$cname = trim($cname,"\"'\\");
							$cname = substr($cname,0,strpos($cname,' '));
							$cname = trim($cname,"\"'\\");
							$err = "Fornire il campo \"".$cname."\"";
							break;
						case 1452:
							$cname = $db->getErrorMsg();
							$cname = substr($cname,strpos($cname,'FOREIGN KEY ')+strlen('FOREIGN KEY '));
							$cname = trim($cname,"\"'\\()`");
							$cname = substr($cname,0,strpos($cname,' '));
							$cname = trim($cname,"\"'\\()`");
							$err = "Fornire un valore valido per il campo \"".$cname."\"";
							break;
						default:
							$err .= $db->getErrorNum().') '.$db->getErrorMsg().' '.$db->getQuery();
							break;	
					}
					notifyXML(400,$err);
				}
			}
		} else {
			/////////////////////////////////////////////////////
			# Eseguo la query di INSERT
			$db->setQuery($sql);
			$db->query();
			if($db->getAffectedRows() == 1) {
				notifyXML(200,'Succesfully inserted with id #'.$db->insertid());
			} else {
				if(DEBUG) {
					notifyXML(400,$db->getErrorNum().') '.$db->getErrorMsg()."<br />".$db->getQuery());
				} else {
					switch($db->getErrorNum()) {
						case 1048:
							$cname = $db->getErrorMsg();
							$cname = substr($cname,strpos($cname,'Column ')+strlen('Column '));
							$cname = trim($cname,"\"'\\");
							$cname = substr($cname,0,strpos($cname,' '));
							$cname = trim($cname,"\"'\\");
							$err = "Fornire il campo \"".$cname."\"";
							break;
						case 1452:
							$cname = $db->getErrorMsg();
							$cname = substr($cname,strpos($cname,'FOREIGN KEY ')+strlen('FOREIGN KEY '));
							$cname = trim($cname,"\"'\\()`");
							$cname = substr($cname,0,strpos($cname,' '));
							$cname = trim($cname,"\"'\\()`");
							$err = "Fornire un valore valido per il campo \"".$cname."\"";
							break;
						default:
							$err .= $db->getErrorNum().') '.$db->getErrorMsg();
							break;	
					}
					//$err .= print_r($_GET,1);
					notifyXML(400,$err);
				}
			}
		}
	} /////////////////////////////////////////////////////////////////////////// fine exec
	return $ris;
}

/*
 *	function sqltoXML ( $dbo , $append_one_blank = false ) : XML
 *	restituisce un oggetto XML che contiene i record della query SQL settata nell'oggetto
 *	dbo passato come argomento
 *	@param dbo	JDatabase object che identifica una connessione attiva a un server Mysql
 *	@param append_one_blank	Boolean, devo includere un record vuoto in fondo alla lista dei record esistenti?
 */
function sqltoXML($dbo) {
	$rows = $dbo->loadObjectList();
	if( !count($rows) && DEBUG ) {
		notifyXML(404,$dbo->getErrorMsg()."<br />\n".$dbo->getQuery());
	}
	return oltoXML($rows);
}

function oltoXML($rows,$field_to_jump=array()) {
	$ris  = "";
	$ris .= "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";
	$ris .= "<rows stat=\"OK\">\n";
	for($i=0;$i<count($rows);$i++) {
		$ris .= "\t<row ";
		if(is_array($rows[$i]) || is_object($rows[$i])) {
			foreach($rows[$i] as $k=>$v) {
				if( is_array($field_to_jump) && in_array($k,$field_to_jump) ) {
					continue;
				}
				//$ris .= $k."=\"".htmlentities($v,0,'UTF-8')."\" ";
				//$ris .= $k."=\"".$v."\" ";
				$v = str_replace("&","&amp;",$v);
				//$v = str_replace("'","&#39;",$v);
				$v = str_replace("\"","'",$v);
				$ris .= $k."=\"".$v."\" ";
			}
		} else {
			$ris .= "value=\"".strval($rows[$i])."\" ";
		}
		$ris .= "/>\n";
	}
	$ris .= 	"</rows>\n";
	return $ris;
}

function otoXML($row,$field_to_jump=array(),$name="rows") {
	$ris  = "";
	$ris .= "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";
	$ris .= "<".$name." stat=\"OK\">\n";
	if(is_array($row) || is_object($row)) {
		foreach($row as $k=>$v) {
			if( is_array($field_to_jump) && in_array($k,$field_to_jump) ) {
				continue;
			}
			//$ris .= $k."=\"".htmlentities($v,0,'UTF-8')."\" ";
			//$ris .= $k."=\"".$v."\" ";
			$v = str_replace("&","&amp;",$v);
			//$v = str_replace("'","&#39;",$v);
			$v = str_replace("\"","'",$v);
			$ris .= $k."=\"".$v."\" ";
		}
	} else {
		$ris .= strval($row);
	}
	$ris .= 	"</".$name.">\n";
	return $ris;
}

/*
 *	function switch_dei_task ( $azione , $database , $tabella , $id ) : XML
 *	switch dei task del file PHP che gestisce un form della tabella $tabella
 *	@param azione	READ|INS|UPD definisce l'azione da fare sulla tabella
 *	@param database	JDatabase object che identifica una connessione attiva a un server Mysql
 *	@param tabella	nome della tabella su cui operare
 *	@param id	di default a 0
 */
function switch_dei_task($azione,$database,$tabella,$id) {
	switch($azione) {
		default:
		case 'READ':
			// publishing
			$str = describeQuerytoXML($tabella,$database,$id);
			header('Content-type: text/xml; charset=UTF-8');
			echo $str;
			// end-publishing
		break;
		case 'INS':
			$exec = true & DEBUG==0;
			$sql = bindObjtoQuery($database,$tabella,$_GET,$exec);
			if(DEBUG)	echo $sql;
		break;
		case 'UPD':
			$exec = true & DEBUG==0;
			if($id > 0) {
				$sql = bindObjtoQuery($database,$tabella,$_GET,$exec,$id);
				if(DEBUG)	echo $sql;
			} else	notifyXML(403,'Occorre un identificativo del record da aggiornare!');
		break;
		case 'DEL':
			$exec = true & DEBUG==0;
			if($id > 0) {
				$database->setQuery("SHOW FULL FIELDS FROM `".$tabella."`");
				$rows = $database->loadObjectList();
				if( !count($rows) ) {
					notifyXML(400,$database->getErrorMsg()."<br />".$database->getQuery());
					notifyXML(404,"Impossibile trovare la tabella ".$tabella." nel db corrente!");
				} else {
					$idfield = $rows[0]->Field;
					$sql = "DELETE FROM `".$tabella."` WHERE `".$idfield."` = '".$id."' LIMIT 1";
					if($exec) {
						$database->setQuery($sql);
						$database->query();
						if($database->getAffectedRows() == 1) {
							notifyXML(200,'Record with id #'.$id.' was successfully deleted');
						} else {
							$err = '';
							switch($database->getErrorNum()) {
								case 1048:
									$cname = $database->getErrorMsg();
									$cname = substr($cname,strpos($cname,'Column ')+strlen('Column '));
									$cname = trim($cname,"\"'\\");
									$cname = substr($cname,0,strpos($cname,' '));
									$cname = trim($cname,"\"'\\");
									$err = "Fornire il campo \"".$cname."\"";
									break;
								case 1452:
									$cname = $database->getErrorMsg();
									$cname = substr($cname,strpos($cname,'FOREIGN KEY ')+strlen('FOREIGN KEY '));
									$cname = trim($cname,"\"'\\()`");
									$cname = substr($cname,0,strpos($cname,' '));
									$cname = trim($cname,"\"'\\()`");
									$err = "Fornire un valore valido per il campo \"".$cname."\"";
									break;
								default:
									$err .= $database->getErrorNum().') '.$database->getErrorMsg();
									break;	
							}
							notifyXML(400,$err);
						}
					} else {
						echo $sql;
					}
				}
			} else {
				notifyXML(403,'Occorre un identificativo del record da eliminare!');
			}
		break;
	}
}


/*
 *	function queryList ( $dbe , $tabella , $limit = 0 , $limitstart = 0 , $order = '' ) : XML
 *	ritorna la Query di list della tabella specificata
 */
function queryList($dbe, $tabella,$limit=0,$limitstart=0,$order='',$columns=array()) {
	$ris = "";
	$values = array();
	$dbe->setQuery("SHOW FULL FIELDS FROM `".$tabella."`");
	$rows = $dbe->loadObjectList();
	# ricavo le opzioni dalla tabella relazionata
	$dbe->setQuery("SHOW CREATE TABLE `".$tabella."`");
	$sqlcreate = $dbe->loadAssocList();
	if(count($sqlcreate)==1) {
		$sqlcreate = current($sqlcreate);
		$sqlcreate = $sqlcreate['Create Table'];
	} else {
		notifyXML(404,'Could not find table definitio for '.$tabella);
	}
	if( !count($rows) && DEBUG ) {
		/*header('Content-type: text/plain; charset=UTF');
		echo $dbe->getErrorMsg();
		echo $dbe->getQuery();
		die();*/
		notifyXML(400,$dbe->getErrorMsg()."<br />".$dbe->getQuery());
	}

	$query_select = "SELECT ";
	$query_from = "";
	$where = "";

	for($i=0;$i<count($rows);$i++) {
		
		if(count($columns) && !in_array($rows[$i]->Field,$columns)) {
			continue;
		}
	
		$type = $rows[$i]->Type;
		if($rows[$i]->Key == 'MUL' && strpos(' '.$sqlcreate,'FOREIGN KEY (`'.$rows[$i]->Field.'`)') != false) {
			# gestione chiavi "foreign key"
			$type = 'selection';
			$ris .= "options=\"";
			//print_r($sqlcreate);
			# individuo la riga che mi interessa
			$sqlcreate2 = substr($sqlcreate,strpos($sqlcreate,'CONSTRAINT'));
			$sqlcreate2 = substr($sqlcreate2,strpos($sqlcreate2,'FOREIGN KEY (`'.$rows[$i]->Field.'`)'));
			$sqlcreate2 = substr($sqlcreate2,0,strpos($sqlcreate2,"\n"));
			# estraggo il riferimento a tabella e campo FOREIGN
			$tableto = substr($sqlcreate2,strpos($sqlcreate2,'REFERENCES ')+strlen('REFERENCES '));
			$tableto = substr($tableto,0,strpos($tableto,' '));
			$tableto = trim($tableto,' `');
			# estraggo il nome del campo chiave nella tabella FOREIGN
			$fieldto = substr($sqlcreate2,strpos($sqlcreate2,$tableto)+strlen($tableto));
			$fieldto = trim($fieldto,' `(');
			$fieldto = substr($fieldto,0,strpos($fieldto,' '));
			$fieldto = trim($fieldto,' `()');
			# estraggo i valori storati nella tabella
			$dbe->setQuery("DESCRIBE `".$tableto."`");
			$options = $dbe->loadObjectList();
			# estraggo il nome del campo da usare come label, uso il campo successivo a quello della chiave
			$labelto = -1;
			foreach($options as $option) {
				if($labelto == '')	$labelto = $option->Field;
				if($option->Field == $fieldto)	$labelto = '';
			}
		
			$tablelabelto = $tableto.substr_count($query_from,"LEFT JOIN `".$tableto."`");
		
			if($labelto == '')	$labelto = current(array_keys($option));
			
			if($tableto == 'operatore')	$labelto = 'COGNOME';
			
			$query_select .= " `".TABLE_NAME."`.`".$rows[$i]->Field."` AS '_".$rows[$i]->Field."',\n\t";
			$query_select .= " ".$tablelabelto.".`".$labelto."` AS '".$rows[$i]->Field."',\n\t";
				//($rows[$i]->Comment!=""?$rows[$i]->Comment:$rows[$i]->Field)."',\n\t";
			$query_from .= "\n LEFT JOIN `".$tableto."` AS ".$tablelabelto."\n\t";
			$query_from .= "ON ".$tablelabelto.".`".$fieldto."` = `".$tabella."`.`".$rows[$i]->Field."`";
		} else {
			$query_select .= " `".$tabella."`.`".$rows[$i]->Field."`,\n\t";
		}
		
		if(strlen(stripslashes(strval(varGET($rows[$i]->Field,'')))) > 0 ) {
			$where .= " AND `".$tabella."`.`".$rows[$i]->Field."` IN ( '".trim(stripslashes(strval(varGET($rows[$i]->Field,''))),"'")."' ) ";
		}
	}
	$query_select = trim($query_select,"\t ,\n");
	$query_select.= "\n FROM `".$tabella."`\n".$query_from;
	$query_select.= "\n WHERE (1) ".$where;
	
	if(intval($limit) > 0) {
		$query_select .= " LIMIT $limit ";
		if(intval($limitstart)) {
			$query_select .= ", $limitstart ";
		}
	}
	
	if(strlen($order)) {
		$query_select .= " ORDER BY ".$order;
	}

	return $query_select;
}


/**
		Use the radius
	With the pois/search and pois/event request, the location of the user will be passed.
	Please use a feasible perimeter, if non is given to limit the results to valid information
	in the surroundings of the user.
	A simple way of calculating a surroundings search is by calculating the distance between
	the user location a your POI and comparing it to your radius given.
	
	// Calculate the distance between two POIs 
	$distance = distanceBetweenLLAsInMeters($latitude1, $longitude1, $latitude2, $longitude2);
	
	// Compare this distance to a defined value. If the distance is greater than the maximum radius,
	// the poi will not be output
	if($distance > MAX_RADIUS)
	  $showPOI = false;
	else
	  $showPOI = true;
 */

if (!function_exists('distanceBetween')) {
	/**
	* Calculate the distance in meters between two points given by latitude and longitude
	* @param float $latitude1 
	* @param float $longitude1
	* @param float $latitude2
	* @param float $longitude2
	* @return float distance in meters
	*/
	function distanceBetween($latitude1, $longitude1, $latitude2, $longitude2) {
		$deg2RadNumber = (float)(pi() / 180);
		$earthRadius= 6371009;
		$latitudeDistance=($latitude1-$latitude2)*$deg2RadNumber;
		$longitudeDistance=($longitude1-$longitude2)*$deg2RadNumber;
		$a = pow(sin($latitudeDistance/2.0),2);
		$a = $a + cos($latitude1*$deg2RadNumber) * cos($latitude2*$deg2RadNumber) * pow(sin($longitudeDistance/2.0),2);
		$c = 2.0 * atan2( sqrt($a) , sqrt(1.0-$a) );
		$distance = $earthRadius*$c;
		return $distance;
	}
}

/**
 * sostituisce tutti i caratteri non ammessi e ritorna la stringa passata come sequenza alfanumerica
 */
function alfanumerico($str) {
	//echo preg_replace('/[^a-zA-Z0-9]/','','2323abc')."<br />";
	return preg_replace('/[^a-zA-Z0-9]/','',strval($str));
}

/**
 * verifico se la stringa passata è alfanumerica (true)
 */
function is_alfanumerico($str) {
	return !preg_match('/[^a-zA-Z0-9]/',strval($str));
}

function getHTMLHeader() {
	return str_replace("\t","  ",'<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
	<meta http-equiv="content-type" content="text/html; charset=UTF-8">
	<meta name="viewport" content="initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
	<title>SL App</title>
	<script type="text/javascript" src="/slcore/slvote/dhtmlxTouch/touchui_debug.js"></script>
	<link rel="stylesheet" type="text/css" href="/slcore/slvote/dhtmlxTouch/touchui.css">
	<style type="text/css">
		html { overflow:scroll; } html, body { background-color:#ffffff; }
	</style>
</head>
<body>
');
}

function getHTMLFooter() {
	return str_replace("\t","  ","
</body>
</html>");
}

function getLabel($lbl) {
	return ucwords(strtolower($lbl));
}

function optimizeCode($str) {
	while(strpos(" ".$str,"\n\t")!=false) {
		$str = str_replace("\n\t","\n",$str);
	}
	$str = str_replace("{\n","{",$str);
	$str = str_replace("\n}","}",$str);
	$str = str_replace("[\n","[",$str);
	$str = str_replace("\n]","]",$str);
	$str = str_replace(",\n",",",$str);
	$str = str_replace(",]","]",$str);
	$str = str_replace(";\n","\n",$str);
	$str = str_replace("\n",";\n",$str);
	$str = str_replace("\n;\n","\n",$str);
	return $str;
}

?>
