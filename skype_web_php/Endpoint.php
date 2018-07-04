<?php
/**
 *  @file Endpoint.php
 *  @brief prepare requests to be sent by the transport class
 */
namespace skype_web_php;

/**
 * Class Endpoint
 *
 * @package skype_web_php
 */
class Endpoint
{

    /**
     * @var Method
     */
    private $method;
    /**
     * @var Uri
     */
    private $uri;
    /**
     * @var Params
     */
    private $params;
    /**
     * @var Requires
     */
    private $requires = [
        'skypeToken' => false,
        'regToken' => false,
    ];

    /**
     *  @brief constructor
     *  
     *  @param string $method a value in [GET, POST,PUT, DELETE, PATCH, HEAD, OPTIONS]
     *  @param string $uri target URL
     *  @return void
     */
    public function __construct($method, $uri, array $params = [], array $requires = [])
    {
        $this->method = $method;
        $this->uri = $uri;
        $this->params = $params;
        if (!array_key_exists('headers', $this->params)) {
            $this->params['headers'] = [];
        }
        $this->requires = array_merge($this->requires, $requires);
    }

    /**
     *  @brief set the skypeToken flag to true
     *  
     *  @return $this
     */
    public function needSkypeToken()
    {
        $this->requires['skypeToken'] = true;

        return $this;
    }

    /**
     *  @brief get the skypeToken flag
     *  
     *  @return $this
     */
    public function skypeToken()
    {
        return $this->requires['skypeToken'];
    }

    /**
     *  @brief set the regToken flag to true
     *  
     *  @return $this
     */
    public function needRegToken()
    {
        $this->requires['regToken'] = true;

        return $this;
    }

    /**
     *  @brief get the regToken flag
     *  
     *  @return $this
     */
    public function regToken()
    {
        return $this->requires['regToken'];
    }

    /**
     *  @brief process place holders in URL
     *  
     *  @param array $args list of values
     *  @return Endpoint
     */
    public function format($args)
    {
        return new Endpoint($this->method, vsprintf($this->uri, $args), $this->params, $this->requires);
    }

    /**
     *  @brief get a prepared request to be sent by the cURL client
     *  
     *  @return Request
     */
    public function getRequest($args = [])
    {
        $Request = new Request($this->method, $this->uri, $this->params);
		$Request->withParams($args['params']);
        if ($this->requires['skypeToken']) {
            $Request = $Request->withHeader('X-SkypeToken', $args['skypeToken']);
        }
        if ($this->requires['regToken']) {
            $Request = $Request->withHeader('RegistrationToken', $args['regToken']);
        }
        return $Request;
    }

	/**
	 *  @brief get the URI of current Endpoint
	 *  
	 *  @return string
	 */
	public function getUri() {
		return $this->uri;
	}

	/**
	 *  @brief get the HTTP method of current Endpoint
	 *  
	 *  @return string
	 */
	public function getMethod() {
		return $this->method;
	}
}