<?php
namespace skype_web_php;

class Request {
	
	protected $method, $uri, $params;
	
	public function __construct($method, $uri, $params){
		$this->method = $method;
		$this->uri = $uri;
		$this->params = $params;
	}
	
	public function withParams($params) {
		foreach($params as $k => $v) {
			$this->params[$k] = $v;
		}
	}
	
	public function withHeader($headerName, $headerValue) {
		if(!array_key_exists('headers', $this->params)) {
			$this->params['headers'] = [];	
		}
		$this->params['headers'][$headerName] = $headerValue;
		return $this;
	}
	
	public function getMethod() {
		return $this->method;
	}
	
	public function getUri() {
		return $this->uri;
	}
	
	public function getParams() {
		return $this->params;
	}
}