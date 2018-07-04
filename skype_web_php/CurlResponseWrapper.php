<?php
/**
 *  @file CurlResponseWrapper.php
 *  @brief yet another light cURL response wrapper.
 */
namespace skype_web_php;

/**
 * Class CurlResponseWrapper
 * @package skype_web_php
 */
class CurlResponseWrapper {
	
	protected $ch;
	protected $response;
	protected $responseProto;
	protected $reasonPhrase;
	protected $headers;
	protected $responseData;
	protected $error;
	
	/**
	 *  @brief constructor
	 *  
	 *  @param resource $channel a resource acquired with curl_init
	 *  @param int $retryOnError nb of retry in case of errors
	 *  @param int $retrySleep sleep time between each attempt
	 *  @return void
	 */
	public function __construct($channel, $retryOnError=5, $retrySleep=2) {
		$this->ch = $channel;
		$this->response = curl_exec($this->ch);
		$this->responseData = curl_getinfo($this->ch);
		$this->headers = [];
		for($i=0; $i<$retryOnError; $i++) {
			$errNo = curl_errno($this->ch);
			if(0 == $this->responseData['http_code'] || 0<$errNo) {
				$this->error = $errNo.' '.curl_error($this->ch);
				sleep($retrySleep);
				$this->response = curl_exec($this->ch);
				$this->responseData = curl_getinfo($this->ch);
			} else {
				$this->error = null;
				break;
			}
		}
		if(null == $this->error) {
			$this->parseResponse();
		} else {
			echo 'cURL error ', $this->responseData['url'], ' [',$this->error,']', PHP_EOL;
		}
	}
	
	/**
	 *  @brief destructor. close any cUrl resource opened
	 *  
	 *  @return void
	 */
	public function __destruct() {
		@curl_close($this->ch);
	}
	
	/**
	 *  @brief get the response body
	 *  
	 *  @return string
	 */
	public function getBody() {
		return $this->response;
	}

	/**
	 *  @brief return the response protocol (ex HTTP/1.1)
	 *  
	 *  @return string
	 */
	public function getResponseProto() {
		return $this->responseProto;
	}

	/**
	 *  @brief return the response reason phrase (ex NOT FOUND)
	 *  
	 *  @return string
	 */
	public function getReasonPhrase() {
		return $this->reasonPhrase;
	}
	
	/**
	 *  @brief get response headers
	 *  
	 *  @return array
	 */
	public function getHeaders() {
		return $this->headers;
	}
	
	/**
	 *  @brief get a particular header
	 *  
	 *  @param string $headerName the requested header
	 *  @return array
	 */
	public function getHeader($headerName) {
		return array_key_exists($headerName, $this->headers) ? $this->headers[$headerName] : [];
	}
	
	/**
	 *  @brief get the status code of last request
	 *  
	 *  @return integer
	 */
	public function getStatusCode() {
		return $this->responseData['http_code'];
	}
	
	/**
	 *  @brief get the infos returned by curl_getinfo
	 *  
	 *  @return array
	 */
	public function getInfos() {
		return $this->responseData;
	}
	
	/**
	 *  @brief get a particular info from curl_getinfo
	 *  
	 *  @param string $keyName the key to find
	 *  @return mixed or null if not found
	 */
	public function getInfo($keyName) {
		return array_key_exists($keyName, $this->responseData) ? $this->responseData[$keyName] : null;
	}

	/**
	 *  @brief parse the text response into class members
	 *  
	 *  @return void
	 */
	protected function parseResponse() {
		$headerLen = curl_getinfo($this->ch, CURLINFO_HEADER_SIZE);
		$tmp = explode("\n", rtrim(substr($this->response, 0, $headerLen)));
		$li = array_shift($tmp);
		$tokens = explode(' ', rtrim($li));
		$this->responseProto = array_shift($tokens);
		array_shift($tokens);
		$this->reasonPhrase = join(' ', $tokens);
		while(null !== ($li = array_shift($tmp))) {
			$li = rtrim($li, "\r");
			$k = substr($li, 0, ($p=strpos($li, ':')));
			$v = substr($li, $p+2);
			if(!array_key_exists($k, $this->headers)) {
				$this->headers[$k] = [];
			}
			$this->headers[$k][] = $v;
		}
		$this->response = substr($this->response, $headerLen);
	}
}