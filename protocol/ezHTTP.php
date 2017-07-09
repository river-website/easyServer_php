<?php

class ezHTTP{

	public static function decode($recv_buffer, ezTCP $connection)
	{
		// Init.
		$_POST  = $_GET = $_COOKIE = $_REQUEST = $_SESSION = $_FILES = array();
		$GLOBALS['HTTP_RAW_POST_DATA'] = '';
		// Clear cache.
		// HttpCache::$header   = array('Connection' => 'Connection: keep-alive');
		// HttpCache::$instance = new HttpCache();
		// $_SERVER
		$_SERVER = array(
			'QUERY_STRING'		 => '',
			'REQUEST_METHOD'	   => '',
			'REQUEST_URI'		  => '',
			'SERVER_PROTOCOL'	  => '',
			'SERVER_SOFTWARE'	  => 'esayPHP',
			'SERVER_NAME'		  => '',
			'HTTP_HOST'			=> '',
			'HTTP_USER_AGENT'	  => '',
			'HTTP_ACCEPT'		  => '',
			'HTTP_ACCEPT_LANGUAGE' => '',
			'HTTP_ACCEPT_ENCODING' => '',
			'HTTP_COOKIE'		  => '',
			'HTTP_CONNECTION'	  => '',
			'REMOTE_ADDR'		  => '',
			'REMOTE_PORT'		  => '0',
			'REQUEST_TIME'		 => time()
		);

		// Parse headers.
		list($http_header, $http_body) = explode("\r\n\r\n", $recv_buffer, 2);
		$header_data = explode("\r\n", $http_header);

		list($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $_SERVER['SERVER_PROTOCOL']) = explode(' ',
			$header_data[0]);

		$http_post_boundary = '';
		unset($header_data[0]);
		foreach ($header_data as $content) {
			// \r\n\r\n
			if (empty($content)) {
				continue;
			}
			list($key, $value)  = explode(':', $content, 2);
			$key = str_replace('-', '_', strtoupper($key));
			$value = trim($value);
			$_SERVER['HTTP_' . $key] = $value;
			switch ($key) {
				// HTTP_HOST
				case 'HOST':
					$tmp = explode(':', $value);
					$_SERVER['SERVER_NAME'] = $tmp[0];
					if (isset($tmp[1])) {
						$_SERVER['SERVER_PORT'] = $tmp[1];
					}
					break;
				// cookie
				case 'COOKIE':
					parse_str(str_replace('; ', '&', $_SERVER['HTTP_COOKIE']), $_COOKIE);
					break;
				// content-type
				// case 'CONTENT_TYPE':
					if (!preg_match('/boundary="?(\S+)"?/', $value, $match)) {
						if ($pos = strpos($value, ';')) {
							$_SERVER['CONTENT_TYPE'] = substr($value, 0, $pos);
						} else {
							$_SERVER['CONTENT_TYPE'] = $value;
						}
					} else {
						$_SERVER['CONTENT_TYPE'] = 'multipart/form-data';
						$http_post_boundary	  = '--' . $match[1];
					}
					break;
				case 'CONTENT_LENGTH':
					$_SERVER['CONTENT_LENGTH'] = $value;
					break;
			}
		}

		// Parse $_POST.
		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			if (isset($_SERVER['CONTENT_TYPE'])) {
				switch ($_SERVER['CONTENT_TYPE']) {
					case 'multipart/form-data':
						// self::parseUploadFiles($http_body, $http_post_boundary);
						break;
					case 'application/x-www-form-urlencoded':
						parse_str($http_body, $_POST);
						break;
				}
			}
		}

		// HTTP_RAW_REQUEST_DATA HTTP_RAW_POST_DATA
		$GLOBALS['HTTP_RAW_REQUEST_DATA'] = $GLOBALS['HTTP_RAW_POST_DATA'] = $http_body;

		// QUERY_STRING
		$_SERVER['QUERY_STRING'] = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
		if ($_SERVER['QUERY_STRING']) {
			// $GET
			parse_str($_SERVER['QUERY_STRING'], $_GET);
		} else {
			$_SERVER['QUERY_STRING'] = '';
		}

		// REQUEST
		$_REQUEST = array_merge($_GET, $_POST);

		// REMOTE_ADDR REMOTE_PORT
		// $_SERVER['REMOTE_ADDR'] = $connection->getRemoteIp();
		// $_SERVER['REMOTE_PORT'] = $connection->getRemotePort();

		return array('get' => $_GET, 'post' => $_POST, 'cookie' => $_COOKIE, 'server' => $_SERVER, 'files' => $_FILES);
	}
}