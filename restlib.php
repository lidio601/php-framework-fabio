<?php

/**
	@see http://en.wikipedia.org/wiki/Representational_state_transfer
	@see http://www.gen-x-design.com/archives/create-a-rest-api-with-php/
	@see http://www.gen-x-design.com/archives/making-restful-requests-in-php/
	*/

class RestUtils {
	
	public static function processRequest() {
		// get our verb
		$request_method = strtolower($_SERVER['REQUEST_METHOD']);
		//print_r($_SERVER);
		//$request_method = strtolower($_SERVER['PATH_INFO']);
		//print_r($request_method);
		
		$return_obj		= new RestRequest();
		// we'll store our data here
		$data			= array();
		//print_r($data);

		switch ($request_method) {
			// gets are easy...
			case 'get':
				$data = $_GET;
				/*if(empty($data)) {
					$data = explode("/", $_SERVER['PATH_INFO']);
				}*/
				break;
				// so are posts
			case 'post':
				$data = $_POST;
				/*if(empty($data)) {
					$data = explode("/", $_SERVER['PATH_INFO']);
				}*/
				break;
				// here's the tricky bit...
			case 'put':
				// basically, we read a string from PHP's special input location,
				// and then parse it out into an array via parse_str... 
				// per the PHP docs:
				// Parses str  as if it were the query string 
				// passed via a URL and sets
				// variables in the current scope.
				parse_str(file_get_contents('php://input'), $put_vars);
				$data = $put_vars;
				break;
		}

		// store the method
		$return_obj->setMethod($request_method);

		// set the raw data, so we can access it if needed (there may be
		// other pieces to your requests)
		$return_obj->setRequestVars($data);

		if(isset($data['data'])) {
			// translate the JSON to an Object for use however you want
			$return_obj->setData(json_decode($data['data']));
		}
		return $return_obj;
	}

	public static function sendResponse($status = 200, $body = '', $content_type = 'text/html') {
		$status_header = 'HTTP/1.1 ' . $status . ' ' . RestUtils::getStatusCodeMessage($status);
		// set the status
		header($status_header);
		// set the content type
		header('Content-type: ' . $content_type);

		// pages with body are easy
		if($body != '') {
			// send the body
			echo $body;
			exit;
		}
		// we need to create the body if none is passed
		else {
			// create some body messages
			$message = '';

			// this is purely optional, but makes the pages a little nicer to read
			// for your users.  Since you won't likely send a lot of different status codes,
			// this also shouldn't be too ponderous to maintain
			switch($status) {
				case 401:
					$message = 'You must be authorized to view this page.';
					break;
				case 404:
					$message = 'The requested URL ' . $_SERVER['REQUEST_URI'] . ' was not found.';
					break;
				case 500:
					$message = 'The server encountered an error processing your request.';
					break;
				case 501:
					$message = 'The requested method is not implemented.';
					break;
			}

			// servers don't always have a signature turned on (this is an apache directive "ServerSignature On")
			$signature = ($_SERVER['SERVER_SIGNATURE'] == '') ? $_SERVER['SERVER_SOFTWARE'] . ' Server at ' . $_SERVER['SERVER_NAME'] . ' Port ' . $_SERVER['SERVER_PORT'] : $_SERVER['SERVER_SIGNATURE'];

			// this should be templatized in a real-world solution
			$body = '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
		<title>' . $status . ' ' . RestUtils::getStatusCodeMessage($status) . '</title>
	</head>
	<body>
		<h1>' . RestUtils::getStatusCodeMessage($status) . '</h1>
		<p>' . $message . '</p>
		<hr />
		<address>' . $signature . '</address>
	</body>
</html>';

			echo $body;
			exit;
		}
	}

	public static function getStatusCodeMessage($status) {
		// these could be stored in a .ini file and loaded
		// via parse_ini_file()... however, this will suffice
		// for an example
		$codes = Array(
		    100 => 'Continue',
		    101 => 'Switching Protocols',
		    200 => 'OK',
		    201 => 'Created',
		    202 => 'Accepted',
		    203 => 'Non-Authoritative Information',
		    204 => 'No Content',
		    205 => 'Reset Content',
		    206 => 'Partial Content',
		    300 => 'Multiple Choices',
		    301 => 'Moved Permanently',
		    302 => 'Found',
		    303 => 'See Other',
		    304 => 'Not Modified',
		    305 => 'Use Proxy',
		    306 => '(Unused)',
		    307 => 'Temporary Redirect',
		    400 => 'Bad Request',
		    401 => 'Unauthorized',
		    402 => 'Payment Required',
		    403 => 'Forbidden',
		    404 => 'Not Found',
		    405 => 'Method Not Allowed',
		    406 => 'Not Acceptable',
		    407 => 'Proxy Authentication Required',
		    408 => 'Request Timeout',
		    409 => 'Conflict',
		    410 => 'Gone',
		    411 => 'Length Required',
		    412 => 'Precondition Failed',
		    413 => 'Request Entity Too Large',
		    414 => 'Request-URI Too Long',
		    415 => 'Unsupported Media Type',
		    416 => 'Requested Range Not Satisfiable',
		    417 => 'Expectation Failed',
		    500 => 'Internal Server Error',
		    501 => 'Not Implemented',
		    502 => 'Bad Gateway',
		    503 => 'Service Unavailable',
		    504 => 'Gateway Timeout',
		    505 => 'HTTP Version Not Supported'
		);
		return (isset($codes[$status])) ? $codes[$status] : '';
	}
}

class RestRequest {
	
	protected $url;
	protected $verb;
	protected $requestBody;
	protected $requestLength;
	protected $username;
	protected $password;
	protected $acceptType;
	protected $responseBody;
	protected $responseInfo;
	
	public function __construct ($url = null, $verb = 'GET', $requestBody = null) {
		$this->url				= $url;
		$this->verb				= $verb;
		$this->requestBody		= $requestBody;
		$this->requestLength	= 0;
		$this->username			= null;
		$this->password			= null;
		$this->acceptType		= 'application/json';
		$this->responseBody		= null;
		$this->responseInfo		= null;
		
		if ($this->requestBody !== null) {
			$this->buildPostBody();
		}
	}
	
	public function getRequestBody() {
		return $this->requestBody;
	}
	
	public function setMethod ($m) {
		$this->verb = $m;
	}
	
	public function setRequestVars ($data) {
		$this->requestBody = $data;
	}
	
	public function flush () {
		$this->requestBody		= null;
		$this->requestLength	= 0;
		$this->verb				= 'GET';
		$this->responseBody		= null;
		$this->responseInfo		= null;
	}
	
	public function execute () {
		$ch = curl_init();
		$this->setAuth($ch);
		
		try {
			switch (strtoupper($this->verb)) {
				case 'GET':
					$this->executeGet($ch);
					break;
				case 'POST':
					$this->executePost($ch);
					break;
				case 'PUT':
					$this->executePut($ch);
					break;
				case 'DELETE':
					$this->executeDelete($ch);
					break;
				default:
					throw new InvalidArgumentException('Current verb (' . $this->verb . ') is an invalid REST verb.');
			}
		} catch (InvalidArgumentException $e) {
			curl_close($ch);
			throw $e;
		} catch (Exception $e) {
			curl_close($ch);
			throw $e;
		}
		
	}
	
	public function buildPostBody ($data = null) {
		$data = ($data !== null) ? $data : $this->requestBody;
		
		if (!is_array($data)) {
			throw new InvalidArgumentException('Invalid data input for postBody.  Array expected');
		}
		
		$data = http_build_query($data, '', '&');
		$this->requestBody = $data;
	}
	
	protected function executeGet ($ch) {	
		$this->doExecute($ch);
	}
	
	protected function executePost ($ch) {
		if (!is_string($this->requestBody)) {
			$this->buildPostBody();
		}
		
		curl_setopt($ch, CURLOPT_POSTFIELDS, $this->requestBody);
		curl_setopt($ch, CURLOPT_POST, 1);
		
		$this->doExecute($ch);	
	}
	
	protected function executePut ($ch) {
		if (!is_string($this->requestBody)) {
			$this->buildPostBody();
		}
		
		$this->requestLength = strlen($this->requestBody);
		
		$fh = fopen('php://memory', 'rw');
		fwrite($fh, $this->requestBody);
		rewind($fh);
		
		curl_setopt($ch, CURLOPT_INFILE, $fh);
		curl_setopt($ch, CURLOPT_INFILESIZE, $this->requestLength);
		curl_setopt($ch, CURLOPT_PUT, true);
		
		$this->doExecute($ch);
		
		fclose($fh);
	}
	
	protected function executeDelete ($ch) {
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
		$this->doExecute($ch);
	}
	
	protected function doExecute (&$curlHandle) {
		$this->setCurlOpts($curlHandle);
		$this->responseBody = curl_exec($curlHandle);
		$this->responseInfo	= curl_getinfo($curlHandle);
		curl_close($curlHandle);
	}
	
	protected function setCurlOpts (&$curlHandle) {
		curl_setopt($curlHandle, CURLOPT_TIMEOUT, 10);
		curl_setopt($curlHandle, CURLOPT_URL, $this->url);
		curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curlHandle, CURLOPT_HTTPHEADER, array ('Accept: ' . $this->acceptType));
	}
	
	protected function setAuth (&$curlHandle) {
		if ($this->username !== null && $this->password !== null) {
			curl_setopt($curlHandle, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
			curl_setopt($curlHandle, CURLOPT_USERPWD, $this->username . ':' . $this->password);
		}
	}
	
	public function getAcceptType () {
		return $this->acceptType;
	} 
	
	public function setAcceptType ($acceptType) {
		$this->acceptType = $acceptType;
	} 
	
	public function getPassword () {
		return $this->password;
	} 
	
	public function setPassword ($password) {
		$this->password = $password;
	} 
	
	public function getResponseBody () {
		return $this->responseBody;
	} 
	
	public function getResponseInfo () {
		return $this->responseInfo;
	} 
	
	public function getUrl () {
		return $this->url;
	} 
	
	public function setUrl ($url) {
		$this->url = $url;
	} 
	
	public function getUsername () {
		return $this->username;
	} 
	
	public function setUsername ($username) {
		$this->username = $username;
	} 
	
	public function getVerb () {
		return $this->verb;
	} 
	
	public function getMethod () {
		return $this->getVerb();
	}
	
	public function setVerb ($verb) {
		$this->verb = $verb;
	} 
}

/*

$request = new RestRequest('http://example.com/api/user/1', 'GET');
$request->setUsername (’muh_atta’);
$request->setPassword (xxxxx’);
$request->execute();
echo '<pre>' . print_r($request, true) . '</pre>';

$data = RestUtils::processRequest();

switch($data->getMethod) {
	case 'get':
		// retrieve a list of users
		break;
	case 'post':
		$user = new User();
		$user->setFirstName($data->getData()->first_name);  // just for example, this should be done cleaner
		// and so on...
		$user->save();
		break;
	// etc, etc, etc...
}
switch($data->getMethod)
{
	// this is a request for all users, not one in particular
	case 'get':
		$user_list = getUserList(); // assume this returns an array

		if($data->getHttpAccept == 'json')
		{
			RestUtils::sendResponse(200, json_encode($user_list), 'application/json');
		}
		else if ($data->getHttpAccept == 'xml')
		{
			// using the XML_SERIALIZER Pear Package
			$options = array
			(
				'indent' => '     ',
				'addDecl' => false,
				'rootName' => $fc->getAction(),
				XML_SERIALIZER_OPTION_RETURN_RESULT => true
			);
			$serializer = new XML_Serializer($options);

			RestUtils::sendResponse(200, $serializer->serialize($user_list), 'application/xml');
		}

		break;
	// new user create
	case 'post':
		$user = new User();
		$user->setFirstName($data->getData()->first_name);  // just for example, this should be done cleaner
		// and so on...
		$user->save();

		// just send the new ID as the body
		RestUtils::sendResponse(201, $user->getId());
		break;
}

// figure out if we need to challenge the user
if(empty($_SERVER['PHP_AUTH_DIGEST']))
{
	header('HTTP/1.1 401 Unauthorized');
	header('WWW-Authenticate: Digest realm="' . AUTH_REALM . '",qop="auth",nonce="' . uniqid() . '",opaque="' . md5(AUTH_REALM) . '"');

	// show the error if they hit cancel
	die(RestControllerLib::error(401, true));
}

// now, analayze the PHP_AUTH_DIGEST var
if(!($data = http_digest_parse($_SERVER['PHP_AUTH_DIGEST'])) || $auth_username != $data['username'])
{
	// show the error due to bad auth
	die(RestUtils::sendResponse(401));
}

// so far, everything's good, let's now check the response a bit more...
$A1 = md5($data['username'] . ':' . AUTH_REALM . ':' . $auth_pass);
$A2 = md5($_SERVER['REQUEST_METHOD'] . ':' . $data['uri']);
$valid_response = md5($A1 . ':' . $data['nonce'] . ':' . $data['nc'] . ':' . $data['cnonce'] . ':' . $data['qop'] . ':' . $A2);

// last check..
if($data['response'] != $valid_response)
{
	die(RestUtils::sendResponse(401));
}

*/

?>