<?php
/**
 * Microsoft oauth authentication for Skype
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
 * @file SkypeLogin.php
 * @brief Microsoft oauth authentication for Skype
 */
namespace skype_web_php;

require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'json.php';

/**
 * Microsoft oauth authentication for Skype
 *
 * <code>
 * $tokenData = SkypeLogin::getSkypeToken('joe.bloggs', 'password', getcwd().DIRECTORY_SEPARATOR.'app-data'.DIRECTORY_SEPARATOR);
 * </code>
 */
class SkypeLogin {

    /**
     * @brief LoginUrl
     */
	static protected $loginUrl = 'https://login.skype.com/login?client_id=578134&redirect_uri=https%3A%2F%2Fweb.skype.com';

	/**
	 *  @brief extract the JSON object ServerData from a response body
	 *  
	 *  @param [in] $string Description for $string
	 *  @return stdClass
	 */
	static public function parseServerData($string) {
		$srvDataStart = strpos($string, '<script type="text/javascript">var ServerData');
		$srvData = substr($string, $srvDataStart);
		$srvData = substr($srvData, strpos($srvData, '{'));
		$srvDataEnd = strpos($srvData, ';</script>');
		$srvData = substr($srvData, 0, $srvDataEnd);
        file_put_contents('ServerData.json', $srvData);
		$json = new \Services_JSON();
		$srvData = $json->decode($srvData);
		return $srvData;
	}

	/**
	 *  @brief load the Skype token from a session or fetch a new one if expired
	 *  
	 *  @param string $login user login
	 *  @param string $passwd user password
	 *  @param string $dataPath local path where to find session file
	 *  @param int $expiresTreshold expiry treshold
	 *  @return array Skype token and expires value
	 */
	static public function getSkypeToken($login, $passwd, $dataPath, $expiresTreshold=3600) {
		$sessionData = json_decode(file_get_contents($dataPath.$login.'-session.json'), true);
		$sessionData['skypeToken']['expires_in'] = (int)$sessionData['skypeToken']['expires_in'];
		if($sessionData['skypeToken']['expires_in']<time()) {
			$skypeToken = self::fetchSkypeToken($login, $passwd, $dataPath);
			$sessionData['skypeToken']['skypetoken'] = $skypeToken['skypetoken'];
			$sessionData['skypeToken']['expires_in'] = (int)$skypeToken['expires_in'];
			$sessionData['skypeToken']['expires_in'] += (time()-$expiresTreshold);
			if(!file_put_contents($dataPath.$login.'-session.json', json_encode($sessionData, JSON_PRETTY_PRINT))) {
				echo 'session file write error [', $dataPath.$login.'-session.json]', PHP_EOL;
			}
		}
		return $sessionData['skypeToken'];
	}

	/**
	 *  @brief process oauth login
	 *  
	 *  @param string $login user login
	 *  @param string $passwd user password
	 *  @param [in] $dataPath local path where to find data and cURL cookie file directory
	 *  @return mixed array Skype token and expires value or null if error
	 */
	static public function fetchSkypeToken($login, $passwd, $dataPath) {
		$skypeToken = null;
		$skypeTokenExpires = null;
		$client = new CurlRequestWrapper($login, $dataPath.DIRECTORY_SEPARATOR.'curl'.DIRECTORY_SEPARATOR.$login.'-cookies.php');

		$tmp = $client->send('GET', self::$loginUrl, ['debug' => false]);
		$response = $tmp->getBody();
        //file_put_contents('response-001.html', $response);

		$srvData = self::parseServerData($response);
		if(is_object($srvData)) {
			$urlPost = $srvData->urlPost;
			$ppft = $srvData->sFTTag;
			$ppft = substr($ppft, strpos($ppft,'value="')+7);
			$ppft = substr($ppft, 0,strpos($ppft, '"'));
            $ppftKey = $srvData->sFTTag;
            $ppftKey = substr($ppftKey, strpos($ppftKey,'name="')+6);
            $ppftKey = substr($ppftKey, 0, strpos($ppftKey, '"'));
			$postData = array('login' => $login, 'passwd' => $passwd, $ppftKey => $ppft);
			if(isset($srvData->bF) && !empty($srvData->bF)) {
				$postData['PPSX'] = $srvData->bF;
			} else if(isset($srvData->bl) && !empty($srvData->bl)) {
				$postData['PPSX'] = $srvData->bl;
			}
            file_put_contents('PPFT.php', '<?php $PPFT = '.print_r($postData, true));
		} else {
			$doc =  new \DOMDocument();
			@$doc->loadHTML($response, LIBXML_NOWARNING | LIBXML_NOERROR);
			$forms = $doc->getElementsByTagName('form');
			if(0==$forms->length) {
				return null;
			}
			$urlPost = $forms[0]->getAttribute('action');
			$postData = array();
			foreach($forms[0]->childNodes as $input) {
				if('input' == $input->nodeName) {
					$postData[$input->getAttribute('name')] = $input->getAttribute('value');
				}
			}
			$doc = null;
		}

		$tmp = $client->send('POST', $urlPost, ['debug' => false, 'form_params' => $postData]);
		$response2 = $tmp->getBody();
         //file_put_contents('response-002.html', $response2);

		$doc =  new \DOMDocument();
		@$doc->loadHTML($response2, LIBXML_NOWARNING | LIBXML_NOERROR);
		$forms = $doc->getElementsByTagName('form');
		if(0==$forms->length) {
			$srvData = self::parseServerData($response2);
			if(!empty($srvData->sErrTxt)) {
				echo $srvData->sErrTxt, PHP_EOL;
			} else {
				echo 'undefined login error', PHP_EOL;
			}
			return null;
		}		
		$urlPost = $forms[0]->getAttribute('action');
		$postData = array();
		foreach($forms[0]->childNodes as $input) {
			if('input' == $input->nodeName) {
				$postData[$input->getAttribute('name')] = $input->getAttribute('value');
			}
		}
		$doc = null;
		
		$tmp = $client->send('POST', $urlPost, ['debug' => false, 'form_params' => $postData]);
		$response3 = $tmp->getBody();
         //file_put_contents('response-003.html', $response3);
		
		$doc =  new \DOMDocument();
		@$doc->loadHTML($response3, LIBXML_NOWARNING | LIBXML_NOERROR);
		$forms = $doc->getElementsByTagName('form');
		$urlPost = $forms[0]->getAttribute('action');
		$postData = array();
		foreach($forms[0]->childNodes as $input) {
			if('input' == $input->nodeName) {
				if($input->hasAttribute('value')){
					if('skypetoken' == $input->getAttribute('name')) {
						$skypeToken = $input->getAttribute('value');
					} else if('expires_in' == $input->getAttribute('name')) {
						$skypeTokenExpires = $input->getAttribute('value');
					}
					$postData[$input->getAttribute('name')] = $input->getAttribute('value');
				} else {
					$postData[$input->getAttribute('name')] = '';
				}
			}
		}
		$doc = null;

		if(!$skypeToken) {
			$tmp = $client->send('POST', $urlPost, ['debug' => false, 'form_params' => $postData]);
			$response4 = $tmp->getBody();
             //file_put_contents('response-004.html', $response4);
			
			$doc =  new \DOMDocument();
			@$doc->loadHTML($response4, LIBXML_NOWARNING | LIBXML_NOERROR);
			$forms = $doc->getElementsByTagName('form');
			$urlPost = $forms[0]->getAttribute('action');
			$postData = array();
			foreach($forms[0]->childNodes as $input) {
				if('input' == $input->nodeName) {
					if($input->hasAttribute('value')){
						if('skypetoken' == $input->getAttribute('name')) {
							$skypeToken = $input->getAttribute('value');
						} else if('expires_in' == $input->getAttribute('name')) {
							$skypeTokenExpires = $input->getAttribute('value');
						}
						$postData[$input->getAttribute('name')] = $input->getAttribute('value');
					} else {
						$postData[$input->getAttribute('name')] = '';
					}
				}
			}
		}
		if($skypeToken) {
			$tmp = $client->send('POST', $urlPost, ['debug' => false, 'form_params' => $postData]);
			$response5 = $tmp->getBody();
             //file_put_contents('response-005.html', $response5);
		}
		return empty($skypeToken) ? null : array('skypetoken' => $skypeToken, 'expires_in' => $skypeTokenExpires);
	}
}
