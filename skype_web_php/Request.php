<?php
/**
 *  @file Request.php
 *  @brief prepared request to be sent by cURL client
 */
namespace skype_web_php;

/**
 * Class Request
 *
 * @package skype_web_php
 */
class Request {
	
    /**
     * @var Method
     */
	protected $method;
    /**
     * @var Uri
     */
	protected $uri;
    /**
     * @var Params
     */
	protected $params;
	
	/**
	 *  @brief constructor
	 *  
	 *  @param string $method HTTP method
	 *  @param string $uri request URL
	 *  @param array $params parameters of the request
	 *  @return void
	 */
	public function __construct($method, $uri, $params){
		$this->method = $method;
		$this->uri = $uri;
		$this->params = $params;
	}

	/**
	 *  @brief add request parameters (may override)
	 *  
	 *  @param array $params parameters to inject
	 *  @return void
	 */
	public function withParams($params) {
		foreach($params as $k => $v) {
			$this->params[$k] = $v;
		}
	}

	/**
	 *  @brief add request headers (may override)
	 *  
	 *  @param array $params parameters to inject
	 *  @return void
	 */
	public function withHeader($headerName, $headerValue) {
		if(!array_key_exists('headers', $this->params)) {
			$this->params['headers'] = [];	
		}
		$this->params['headers'][$headerName] = $headerValue;
		return $this;
	}

	/**
	 *  @brief return the HTTP method of current request
	 *  
	 *  @return string
	 */
	public function getMethod() {
		return $this->method;
	}

	/**
	 *  @brief return the URL of current request
	 *  
	 *  @return string
	 */
	public function getUri() {
		return $this->uri;
	}

	/**
	 *  @brief return the parameters of current request
	 *  
	 *  @return array
	 */
	public function getParams() {
		return $this->params;
	}
}