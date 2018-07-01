<?php
namespace skype_web_php;

class CurlResponseWrapper {
	
	protected $ch;
	protected $response;
	protected $responseProto;
	protected $reasonPhrase;
	protected $headers;
	protected $responseData;
	protected $error;
	
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
	
	public function __destruct() {
		@curl_close($this->ch);
	}
	
	public function getBody() {
		return $this->response;
	}
	
	public function getHeaders() {
		return $this->headers;
	}
	
	public function getHeader($headerName) {
		return array_key_exists($headerName, $this->headers) ? $this->headers[$headerName] : [];
	}
	
	public function getStatusCode() {
		return $this->responseData['http_code'];
	}
	
	public function getInfos() {
		return $this->responseData;
	}
	
	public function getInfo($keyName) {
		return array_key_exists($keyName, $this->responseData) ? $this->responseData[$keyName] : null;
	}
	
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