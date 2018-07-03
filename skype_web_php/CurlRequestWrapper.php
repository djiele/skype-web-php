<?php
namespace skype_web_php;

class CurlRequestWrapper {
	
	protected $ch;
	protected $sessOptions;
	protected $pathToCookieJar;
	protected $callbacks;
	
	protected $baseOptions = [
		CURLOPT_ENCODING => '',
		CURLOPT_TIMEOUT => 10,
		CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; ...) Gecko/20100101 Firefox/60.0',
		CURLOPT_COOKIESESSION => false,
		CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_NONE,
		CURLOPT_SSLVERSION => CURL_SSLVERSION_DEFAULT,
		CURLOPT_SSL_VERIFYHOST => 2,
		CURLOPT_HEADER => true,
		CURLOPT_AUTOREFERER => true,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_VERBOSE  => false,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_MAXREDIRS => 10,
		CURLOPT_CONNECTTIMEOUT => 5,
	];
	
	public function __construct($pathtoCookieJar=null) {
		if(null !== $pathtoCookieJar) {
			$this->pathToCookieJar = rtrim($pathtoCookieJar, DIRECTORY_SEPARATOR);
			$this->baseOptions[CURLOPT_COOKIEJAR] = $this->pathToCookieJar.'/cookie.txt';
			$this->baseOptions[CURLOPT_COOKIEFILE] = $this->pathToCookieJar.'/cookie.txt';
		}
		$this->callbacks = [];
		$this->sessOptions = [];
	}
	
	protected function initBaseOptions() {
		foreach($this->baseOptions as $k => $v) {
			curl_setopt($this->ch, $k, $v);
		}
	}
	
	public function registerCallback($callback) {
		$this->callbacks[] = $callback;
	}
	
	public function send($method, $url, $params=[]) {
		$this->ch = curl_init();
		$this->initBaseOptions();
		switch($method) {
			case 'GET':
				curl_setopt($this->ch, CURLOPT_HTTPGET, true);
				break;
			case 'POST':
				curl_setopt($this->ch, CURLOPT_POST, true);
				break;
			case 'PUT':
				curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'PUT');
				$params['Headers']['X-HTTP-Method-Override'] = 'PUT';
				break;
			case 'DELETE':
				curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
				$params['Headers']['X-HTTP-Method-Override'] = 'DELETE';
				break;
			case 'PATCH':
				curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
				$params['Headers']['X-HTTP-Method-Override'] = 'PATCH';
				break;
			case 'HEAD':
				curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'HEAD');
				$params['Headers']['X-HTTP-Method-Override'] = 'HEAD';
				curl_setopt($this->ch, CURLOPT_NOBODY, 'true');
				break;
			case 'OPTIONS':
				curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'OPTIONS');
				$params['Headers']['X-HTTP-Method-Override'] = 'OPTIONS';
				break;
		}
		if(!isset($params['headers'])) {
			$params['headers'] = [];
		}
		if(array_key_exists('format', $params)) {
			$url = vsprintf($url, $params['format']);
			curl_setopt($this->ch, CURLOPT_URL, $url);
			unset($params['format']);
		} else {
			curl_setopt($this->ch, CURLOPT_URL, $url);
		}
		foreach($params as $k => $v) {
			if('curl' == $k) {
				foreach($v as $kv => $vv) {
					curl_setopt($this->ch, $kv, $vv);
				}
			}
			if('debug' == $k) {
				curl_setopt($this->ch, CURLOPT_VERBOSE, $v); 
			}
			if('json' == $k) {
				$params['headers']['Content-Type'] = 'application/json';
				if(!is_string($v)) {
					$v = json_encode($v);
				}
				$params['headers']['Content-Length'] = strlen($v);
				curl_setopt($this->ch, CURLOPT_POSTFIELDS, $v);
			} else if('form_params' == $k) {
				if(!array_key_exists('Content-Type', $params['headers']) || 'application/x-www-form-urlencoded' == $params['headers']['Content-Type']) {
					$v = http_build_query($v, null, '&', PHP_QUERY_RFC1738);
					$params['headers']['Content-Type'] = 'application/x-www-form-urlencoded';
					$params['headers']['Content-Length'] = strlen($v);
				} else if('text/plain') {
					$params['headers']['Content-Length'] = strlen($v);
				}
				curl_setopt($this->ch, CURLOPT_POSTFIELDS, $v);
			} else if('body' == $k) {
				if(is_string($v)) {
					$params['headers']['Content-Length'] = strlen($v);
					curl_setopt($this->ch, CURLOPT_POSTFIELDS, $v);
				} else if(is_resource($v)) {
					curl_setopt($this->ch, CURLOPT_PUT, true);
					curl_setopt($this->ch, CURLOPT_UPLOAD, true);
					curl_setopt($this->ch, CURLOPT_INFILESIZE, (int)$params['headers']['Content-Length']);
					curl_setopt($this->ch, CURLOPT_INFILE, $v);
				}
			}
		}
		if(0<count($params['headers'])) {
			$requestHeaders = [];
			foreach($params['headers'] as $k=>$v) {
				$requestHeaders[] = $k.': '.$v;
				unset($params['headers'][$k]);
			}
			if(in_array($method, array('POST', 'PUT'))) {
				$requestHeaders[] = 'Expect:';
			}
			curl_setopt($this->ch, CURLOPT_HTTPHEADER, $requestHeaders);
		}
		
		
		$response = new CurlResponseWrapper($this->ch);
		foreach($this->callbacks as $cb) {
			$response = call_user_func_array($cb, array($response));
		}
		@curl_close($this->ch);
		//echo $method, ' ', $url, PHP_EOL;
		return $response;
	}
}
