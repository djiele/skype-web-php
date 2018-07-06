<?php
/**
 * request endpoint
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
 * @file Endpoint.php
 * @brief request endpoint
 * @license https://opensource.org/licenses/MIT
 */
namespace skype_web_php;

/**
 * request endpoint
 *
 * <code>
 * // create a new instance of Endpoint
 * $endpoint = new Endpoint('GET', 'https://www.example.com', ['debug => true'], ['regToken' => true]);
 * </code>
 */
class Endpoint
{

    /**
     * @brief Method
     */
    private $method;
    /**
     * @brief Uri
     */
    private $uri;
    /**
     * @brief Params
     */
    private $params;
    /**
     * @brief Requires
     */
    private $requires = ['skypeToken' => false, 'regToken' => false];

    /**
     *  @brief constructor
     *  
     *  @param string $method a value in [GET, POST,PUT, DELETE, PATCH, HEAD, OPTIONS]
     *  @param string $uri target URL
     *  @param array $params request parameters
     *  @param array $requires request requirements (skypeToken, regToken)
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