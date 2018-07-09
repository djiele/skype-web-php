<?php
/**
 * yet another light cURL request wrapper
 *
 * LICENSE: Permission is hereby granted, free of charge, to any person obtaining a copy of this software
 * and associated documentation files (the "Software"), to deal in the Software without restriction,
 * including without limitation the rights to use, copy, modify, merge, publish, distribute,
 * sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished
 * to do so, subject to the following conditions: The above copyright notice and this permission notice
 * shall be included in all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING
 * BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE
 * AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM,
 * DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 * 
 * @package skype_web_php
 * @file CurlRequestWrapper.php
 * @brief yet another light cURL request wrapper
 * @license https://opensource.org/licenses/MIT
 */
namespace skype_web_php;

/**
 * yet another light cURL request wrapper
 *
 * <code>
 * // create a new instance of CurlRequestWrapper
 * $client = new CurlRequestWrapper(getcwd().DIRECTORY_SEPARATOR.'app-data'.DIRECTORY_SEPARATOR.'curl'.DIRECTORY_SEPARATOR);
 * $response = $client->send('GET', 'https://www.example.com');
 * // echo the status code
 * echo $response->getStatusCode(), PHP_EOL;
 * </code>
 */
class CurlRequestWrapper {
	
	/**
     * @brief cURL resource
     */
	protected $ch;
	/**
     * @brief SessOptions []
     */
	protected $sessOptions;
	/**
     * @brief UserLogin
     */
	protected $userLogin;
	/**
     * @brief pathToCookieJar
     */
	protected $pathToCookieJar;
	/**
     * @brief Callbacks []
     */
	protected $callbacks;
	/**
     * @brief BaseOptions []
     */
	protected $baseOptions;
	/**
     * @brief Cookies []
     */
	protected $cookies;
	/**
     * @brief Current URL
     */
	protected $currentUrl;
	
	/**
	 *  @brief constructor
	 *  
	 *  @param string $pathToCookieJar directory path where to write cookie file
	 *  @return void
	 */
	public function __construct($userLogin, $pathToCookieJar=null) {
		$this->baseOptions = [
			CURLOPT_ENCODING => '',
			CURLOPT_TIMEOUT => 10,
			CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; ...) Gecko/20100101 Firefox/60.0',
			CURLOPT_COOKIESESSION => 0,
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
		if(null !== $pathToCookieJar) {
			$this->userLogin = $userLogin;
			if('.php' == substr($pathToCookieJar, -4)) {
				$this->pathToCookieJar = $pathToCookieJar;
				if(is_file($this->pathToCookieJar)) {
					require $this->pathToCookieJar;
				} else {
					$cookies = [];
					file_put_contents($this->pathToCookieJar, '<?php $cookies = '.var_export($cookies, true).';');
				}
				$this->cookies = $cookies;
			} else {
				$this->pathToCookieJar = rtrim($pathToCookieJar, DIRECTORY_SEPARATOR);
				$this->baseOptions[CURLOPT_COOKIEJAR] = $this->pathToCookieJar.'/'.$userLogin.'-cookie.txt';
				$this->baseOptions[CURLOPT_COOKIEFILE] = $this->pathToCookieJar.'/'.$userLogin.'-cookie.txt';
			}
		}
		$this->callbacks = [];
		$this->sessOptions = [];
	}
	
	/**
	 *  @brief destructor
	 *  
	 *  @return void
	 */
	public function __destruct() {
		$ts = time();
		if(null !== $this->pathToCookieJar && '.php' == substr($this->pathToCookieJar, -4)){
			foreach($this->cookies as $kDomain => $domain) {
				foreach($domain as $kPath => $path) {
					foreach($path as $kCookie => $cookie) {
						if($cookie['expires'] == 0 || $ts>$cookie['expires']) {
							unset($this->cookies[$kDomain][$kPath][$kCookie]);
						}
					}
				}
			}
			file_put_contents($this->pathToCookieJar, '<?php $cookies = '.var_export($this->cookies, true).';');
		}
	}

	/**
	 *  @brief set boot options for a request
	 *  
	 *  @return void
	 */
	protected function initBaseOptions() {
		foreach($this->baseOptions as $k => $v) {
			curl_setopt($this->ch, $k, $v);
		}
	}

	/**
	 *  @brief update the cookies array
	 *  
	 *  @param CurlResponseWrapper $response a response object from which to get cookies
	 *  @return void
	 */
	protected function updateCookies($response) {
		$ts = time();
		$tmp = $response->getHeader('Set-Cookie');
		if(is_array($tmp) && 0<count($tmp)) {
			foreach($tmp as $v) {
				$v = $this->parseCookie($v);
				foreach($v as $vv) {
					if('.' != substr($vv['domain'], 0, 1)) {
						$vv['domain'] = '.'.$vv['domain'];
					}
					if(!array_key_exists($vv['domain'], $this->cookies)) {
						$this->cookies[$vv['domain']] = [];
					}
					if(!array_key_exists($vv['path'], $this->cookies[$vv['domain']])) {
						$this->cookies[$vv['domain']][$vv['path']] = array();
					}
					if(array_key_exists($vv['name'], $this->cookies[$vv['domain']][$vv['path']])) {
						if(0<$vv['expires'] && $vv['expires']<$ts) {
							unset($this->cookies[$vv['domain']][$vv['path']][$vv['name']]);
						} else {
							$this->cookies[$vv['domain']][$vv['path']][$vv['name']] = ['value' => $vv['value'], 'expires' => $vv['expires']];
						}
					} else {
						if(0==$vv['expires'] || $vv['expires']>$ts) {
							$this->cookies[$vv['domain']][$vv['path']][$vv['name']] = ['value' => $vv['value'], 'expires' => $vv['expires']];
						}
					}
				}
			}
		}
		print_r($this->cookies);
	}

	/**
	 *  @brief parse a HTTP cookie
	 *  
	 *  @param string $cookie the raw cookie contents
	 *  @return array
	 */
	protected function parseCookie($cookieHeader) {
		$parsedCookies = [];
		$cookieParts = ['name' => null, 'value' => null, 'expires' => 0, 'path' => dirname(parse_url($this->currentUrl, PHP_URL_PATH)), 'domain' => parse_url($this->currentUrl, PHP_URL_HOST)];
		$parts = explode(';', $cookieHeader);
		foreach($parts as $ndx=>$part) {
			if(false === strpos($part, '=')) {
				$parts[$ndx] = $part.'=';
			}
			$parts[$ndx] = trim($parts[$ndx]);
			if(0===strpos($parts[$ndx], 'expires=')) {
				$date = substr($parts[$ndx], 0, strpos($parts[$ndx], ' GMT')+4);
				$date = substr($date, strpos($date, '=')+1);
				$parts[$ndx] = str_replace($date, strtotime($date), $parts[$ndx]);
			}
		}
		$cookieHeader = join(';', $parts);
		$cookies = explode(',', $cookieHeader);
		foreach($cookies as $ndx=>$cookie) {
			$cookieFillParts = $cookieParts;
			$cookie = trim($cookie);
			$cookieTokens = explode(';', $cookie);
			$token = array_shift($cookieTokens);
			$cookieFillParts['name'] = substr($token, 0, strpos($token,'='));
			$cookieFillParts['value'] = substr($token, strpos($token,'=')+1);
			while(null !== ($token=array_shift($cookieTokens))) {
				$k = substr($token, 0, strpos($token,'='));
				$v = substr($token, strpos($token,'=')+1);
				$cookieFillParts[$k] = $v;
			}
			$parsedCookies[] = $cookieFillParts;
		}
		return $parsedCookies;
	}
	
	/**
	 *  @brief prepare the Cookie header 
	 *  
	 *  @param string $url target URL
	 *  @return void
	 */
	public function getUrlCookies($url) {
		$ts = time();
		$cookies = [];
		$host = parse_url($url, PHP_URL_HOST);
		if('.' != substr($host, 0, 1)) {
			$host = '.'.$host;
		}
		$path = parse_url($url, PHP_URL_PATH);
		foreach($this->cookies as $kDomain => $vDomain) {
			$kDomainLen = strlen($kDomain);
			if($kDomain != substr($host, -$kDomainLen)) {
				echo 'no match for ', $host, PHP_EOL;
				continue;
			}
			foreach($vDomain as $kPath => $vPath) {
				$kPathLen = strlen($kPath);
				if($kPath != substr($path, 0, $kPathLen)) {
					echo 'no match for ', $path, PHP_EOL;
					continue;
				}
				foreach($vPath as $kCookie => $cookie) {
					if(0 == $cookie['expires'] || $cookie['expires'] > $ts) {
						$cookies[] = $kCookie.': '.$cookie['value'];
					}
				}
			}
		}
		return $cookies;
	}
	
	/**
	 *  @brief register a callback function to be run on each response 
	 *  
	 *  @param function $callback callback function
	 *  @return void
	 */
	public function registerCallback($callback) {
		$this->callbacks[] = $callback;
	}
	
	/**
	 *  @brief build a request and send it to the target host
	 *  
	 *  @param string $method a value in [GET, POST,PUT, DELETE, PATCH, HEAD, OPTIONS]
	 *  @param string $url target URL
	 *  @param array $params request parameters
	 *  @return CurlResponseWrapper
	 */
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
		$this->currentUrl = $url;
		
		$cookies = $this->getUrlCookies($url);
		if(0<count($cookies)) {
			$params['headers']['Cookie'] = $cookies;
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
				if(is_array($v)) {
					foreach($v as $vv) {
						$requestHeaders[] = $k.': '.$vv;
					}
				} else {
					$requestHeaders[] = $k.': '.$v;
				}
				unset($params['headers'][$k]);
			}
			if(in_array($method, array('POST', 'PUT'))) {
				$requestHeaders[] = 'Expect:';
			}
			curl_setopt($this->ch, CURLOPT_HTTPHEADER, $requestHeaders);
		}
		
		
		$response = new CurlResponseWrapper($this->ch);
		$this->currentUrl = $response->getInfo('url');
		foreach($this->callbacks as $cb) {
			$response = call_user_func_array($cb, [$response]);
		}
		$this->updateCookies($response);
		@curl_close($this->ch);
		return $response;
	}
}
