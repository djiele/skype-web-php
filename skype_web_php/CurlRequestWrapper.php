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
     * @brief PathToCookieJar
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
	 *  @brief constructor
	 *  
	 *  @param string $pathtoCookieJar directory path where to write cookie file
	 *  @return void
	 */
	public function __construct($pathtoCookieJar=null) {
		$this->baseOptions = [
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
		if(null !== $pathtoCookieJar) {
			$this->pathToCookieJar = rtrim($pathtoCookieJar, DIRECTORY_SEPARATOR);
			$this->baseOptions[CURLOPT_COOKIEJAR] = $this->pathToCookieJar.'/cookie.txt';
			$this->baseOptions[CURLOPT_COOKIEFILE] = $this->pathToCookieJar.'/cookie.txt';
		}
		$this->callbacks = [];
		$this->sessOptions = [];
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
			$response = call_user_func_array($cb, [$response]);
		}
		@curl_close($this->ch);
		//echo $method, ' ', $url, PHP_EOL;
		return $response;
	}
}
