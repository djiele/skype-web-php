<?php
/**
 * prepared request to be sent by cURL client
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
 * @file Request.php
 * @brief prepared request to be sent by cURL client
 * @license https://opensource.org/licenses/MIT
 */

namespace skype_web_php;

/**
 * prepared request to be sent by cURL client
 *
 * <code>
 * // create a new instance of Request
 * $request = new Request('GET', 'https://www.example.com', ['debug => true']);
 * $request->withHeader('Authorization', 'bearer ldfsdmdfsfk...dlfdkfdlfkl');
 * </code>
 */
class Request
{

    /**
     * @brief Method
     */
    protected $method;
    /**
     * @brief Uri
     */
    protected $uri;
    /**
     * @brief Params
     */
    protected $params;

    /**
     * @brief constructor
     *
     * @param string $method HTTP method
     * @param string $uri request URL
     * @param array $params parameters of the request
     * @return void
     */
    public function __construct($method, $uri, $params)
    {
        $this->method = $method;
        $this->uri = $uri;
        $this->params = $params;
    }

    /**
     * @brief add request parameters (may override)
     *
     * @param array $params parameters to inject
     * @return void
     */
    public function withParams($params)
    {
        foreach ($params as $k => $v) {
            $this->params[$k] = $v;
        }
    }

    /**
     * @brief add request headers (may override)
     *
     * @param string $headerName
     * @param string $headerValue
     * @return void
     */
    public function withHeader($headerName, $headerValue)
    {
        if (!array_key_exists('headers', $this->params)) {
            $this->params['headers'] = [];
        }
        $this->params['headers'][$headerName] = $headerValue;
        return $this;
    }

    /**
     * @brief return the HTTP method of current request
     *
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * @brief return the URL of current request
     *
     * @return string
     */
    public function getUri()
    {
        return $this->uri;
    }

    /**
     * @brief return the parameters of current request
     *
     * @return array
     */
    public function getParams()
    {
        return $this->params;
    }
}