<?php
/**
 * Skype web API
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
 * @file Transport.php
 * @brief Skype web API
 * @license https://opensource.org/licenses/MIT
 */
namespace skype_web_php;

use Exception;
use DOMDocument;
use DOMXPath;

/**
 * Skype web API
 *
 * <code>
 * // create a new instance of Transport
 * $skype = new Transport($username, $passwd, getcwd().DIRECTORY_SEPARATOR.'app-data'.DIRECTORY_SEPARATOR);
 * $skype->login() or die('Login failed');
 * // list of user's contacts
 * $contacts = $skype->loadContacts();
 * $skype->logout()
 * </code>
 */
class Transport {

	/**
     * @brief CLIENTINFO_NAME
     */
	const CLIENTINFO_NAME = 'skype.com';
	/**
     * @brief CLIENT_VERSION
     */
	const CLIENT_VERSION = '908/1.118.0.30//skype.com';
	/**
     * @brief LOCKANDKEY_APPID
     */
	const LOCKANDKEY_APPID = 'msmsgs@msnmsgr.com';
	/**
     * @brief LOCKANDKEY_SECRET
     */
	const LOCKANDKEY_SECRET = 'Q1P7W2E4J9R8U3S5';
	/**
     * @brief SKYPE_WEB
     */
	const SKYPE_WEB = 'web.skype.com';
	/**
     * @brief CONTACTS_HOST
     */
	const CONTACTS_HOST = 'api.skype.com';
	/**
     * @brief NEW_CONTACTS_HOST
     */
	const NEW_CONTACTS_HOST = 'contacts.skype.com';
	/**
     * @brief DEFAULT_MESSAGES_HOST
     */
	const DEFAULT_MESSAGES_HOST = 'client-s.gateway.messenger.live.com';
	/**
     * @brief LOGIN_HOST
     */
	const LOGIN_HOST = 'login.skype.com';
	/**
     * @brief VIDEOMAIL_HOST
     */
	const VIDEOMAIL_HOST = 'vm.skype.com';
	/**
     * @brief XFER_HOST
     */
	const XFER_HOST = 'api.asm.skype.com';
	/**
     * @brief GRAPH_HOST
     */
	const GRAPH_HOST = 'skypegraph.skype.com';
	/**
     * @brief STATIC_HOST
     */
	const STATIC_HOST = 'static.asm.skype.com';
	/**
     * @brief STATIC_CDN_HOST
     */
	const STATIC_CDN_HOST = 'static-asm.secure.skypeassets.com';
	/**
     * @brief DEFAULT_CONTACT_SUGGESTIONS_HOST
     */
	const DEFAULT_CONTACT_SUGGESTIONS_HOST = 'peoplerecommendations.skype.com';

	/**
     * @brief WebSessionId
     */
	private $webSessionId;
	/**
     * @brief LoginName
     */
	private $loginName;
	/**
     * @brief Password
     */
	private $password;
	/**
     * @brief Username
     */
	private $username;
	/**
     * @brief DataPath
     */
	private $dataPath;
    /**
     * @brief Client
     */
    private $client;
    /**
     * @brief SkypeToken
     */
    private $skypeToken;
    /**
     * @brief SkypeTokenExpires
     */
	private $skypeTokenExpires;
    /**
     * @brief RegToken
     */
	private $regToken;
    /**
     * @brief RegTokenExpires
     */
	private $regTokenExpires;
    /**
     * @brief EndpointUrl
     */
	private $endpointUrl;
    /**
     * @brief EndpointId
     */
	private $endpointId;
    /**
     * @brief EndpointPresenceDocUrl
     */
	private $endpointPresenceDocUrl;
    /**
     * @brief EndpointSubscriptionsUrl
     */
	private $endpointSubscriptionsUrl;
    /**
     * @brief Cloud
     */
	private $cloud;

    /**
     * @brief Endpoints []
     */
    private static $Endpoints = null;

    /**
     *  @brief init of named endpoints
     *  
     *  @return void
     */
    private static function init() {
        if (static::$Endpoints) {
            return;
        }

        static::$Endpoints = [
            'asm'          => new Endpoint('POST',
                'https://api.asm.skype.com/v1/skypetokenauth'),

            'endpoint'     => (new Endpoint('POST',
                'https://client-s.gateway.messenger.live.com/v1/users/ME/endpoints'))
                ->needSkypeToken(),

            'contacts'     => (new Endpoint('GET',
                'https://contacts.skype.com/contacts/v2/users/self/contacts?delta&page_size=100&reason=default'))
                ->needSkypeToken(),

            'send_message' => (new Endpoint('POST',
                'https://%sclient-s.gateway.messenger.live.com/v1/users/ME/conversations/%s/messages'))
                ->needRegToken(),

            'logout'  => (new Endpoint('GET',
                'https://login.skype.com/logout?client_id=578134&redirect_uri=https%3A%2F%2Fweb.skype.com&intsrc=client-_-webapp-_-production-_-go-signin')),
        ];
    }

    /**
     *  @brief constructor
     *  
     *  @param string $username skype login
     *  @param string $password Skype password
     *  @param string $dataPath absolute path to the directory where will be saved sessions files
     *  @return void
     */
    public function __construct($username, $password, $dataPath) {
		$this->username = $this->loginName = $username;
		$this->password = $password;
		if(false !== ($pos=strpos($this->username, '@'))) {
			$this->username = substr($this->username, 0, $pos);
		}
		$this->dataPath = $dataPath;
		if(!file_exists($this->dataPath.$username.'-session.json')) {
			if(!copy($this->dataPath.'_user-session.template.json', $this->dataPath.$username.'-session.json')) {
				die($this->dataPath.' directory seems to be not writable');
			}
		}
        static::init();
		
		$this->client = new CurlRequestWrapper($this->loginName, $this->dataPath.DIRECTORY_SEPARATOR.'curl'.DIRECTORY_SEPARATOR);
		$this->client->registerCallback(function ($Response) {
					$code = $Response->getStatusCode();
					if (($code >= 301 && $code <= 303) || $code == 307 || $code == 308) {
						$matches = array();
						$tmp = $Response->getHeader('Location');
						$location = array_pop($tmp);
						preg_match('#https?://([^-]*-)client\-s#', $location, $matches);
						if (array_key_exists(1, $matches)) {
							if($matches[1] !== $this->cloud) {
								$this->cloud = $matches[1];
							}
						}
					}
					return $Response;
				});
		$this->client->registerCallback(function ($Response) {
					$header = $Response->getHeader('X-Correlation-Id');
					if (count($header) > 0) {
						$this->webSessionId = $header[0];
					}
					//print_r($Response->getHeaders());
					return $Response;
				});
    }

	/**
	 *  @brief get predefined headers according to target hostname
	 *  
	 *  @param string $hostname target server hostname
	 *  @return return an array of predefined headers
	 */
	private function headersByhostname($hostname) {
		$ret = [];
		$ret['Origin'] = 'https://web.skype.com';
		$ret['Referer'] = 'https://web.skype.com/en/';
		$ret['Accept-Encoding'] = 'gzip, deflate';
		if(1==substr_count($hostname, self::DEFAULT_MESSAGES_HOST)) {
			$ret['Accept'] = 'application/json; ver=1.0';
			$ret['ClientInfo'] = 'os=Windows; osVer=10; proc=Win64; lcid=en-us; deviceType=1; country=n/a; clientName='.self::CLIENTINFO_NAME.'; clientVer='.self::CLIENT_VERSION;
			$ret['BehaviorOverride'] = 'redirectAs404';
		} else if(1==substr_count($hostname, self::CONTACTS_HOST) || 1==substr_count($hostname, self::NEW_CONTACTS_HOST) || 1==substr_count($hostname, self::VIDEOMAIL_HOST)) {
			$ret['Accept'] = 'application/json; ver=1.0';
			$ret['X-Skype-Caller'] = 'skype.com';
			$ret['X-Skype-Request-Id'] = substr(uniqid(), -8, 8);
		} else if(1==substr_count($hostname, self::GRAPH_HOST)) {
			$ret['Accept'] = 'application/json';
		} else if(1==substr_count($hostname, self::DEFAULT_CONTACT_SUGGESTIONS_HOST)) {
			$ret['Accept'] = 'application/json';
			$ret['X-RecommenderServiceSettings'] = '{\"experiment\":\"default\",\"recommend\":\"true\"}';
			$ret['X-ECS-ETag'] = 'skype.com';
			$ret['X-Skype-Client'] = self::CLIENT_VERSION;
		} else {
			$ret['Accept'] = '*/*';
		}
		return $ret;
	}

	/**
	 *  @brief send request to the REST server
	 *  
	 *  @param mixed $endpointName a named endpoint or an instance of Endpoint
	 *  @param array $params an array of parameters to pass to the client
	 *  @return Object an instance of CurlResponseWrapper
	 */
	function request($endpointName, $params=[]) {
		
        if ($endpointName instanceof Endpoint){
            $Endpoint = $endpointName;
        } else {
            $Endpoint = static::$Endpoints[$endpointName];
        }
        $Request = $Endpoint->getRequest([
            'skypeToken' => $this->skypeToken,
            'regToken'   => $this->regToken,
			'params' => $params
        ]);
		$headersCandidates = $this->headersByhostname(parse_url($Request->getUri(),  PHP_URL_HOST));
		$params = $Request->getParams();
		if(!array_key_exists('headers', $params)) {
			$params['headers'] = [];
		}
		foreach($headersCandidates as $hck => $hcv) {
			if(!array_key_exists($hck, $params['headers'])) {
				$params['headers'][$hck] = $hcv;
			}
		}
		$Response = $this->client->send($Request->getMethod(), $Request->getUri(), $params);
		return $Response;
	}

    /**
     *  @brief get a DOM document out of a response
     *  
     *  @param mixed $endpointName a named endpoint or an instance of Endpoint
     *  @param array $params an array of parameters to pass to the client
     *  @return DOMDocument
     */
    private function requestDOM($endpointName, $params=[]) {
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        $doc->recover = true;
        $body = $this->request($endpointName, $params)->getBody();
        $doc->loadHTML((string) $body);
        libxml_use_internal_errors(false);
        return $doc;
    }

    /**
     *  @brief get a JSON structure out of a response
     *  
     *  @param mixed $endpointName a named endpoint or an instance of Endpoint
     *  @param array $params an array of parameters to pass to the client
     *  @return json decoded response
     */
    private function requestJSON($endpointName, $params=[]) {
        return json_decode($this->request($endpointName, $params)->getBody());
    }

    /**
     *  @brief load the session skypetoken or fetch a new one
     *  
     *  @return boolean
     */
    public function login() {
		$tmp = SkypeLogin::getSkypeToken($this->loginName, $this->password, $this->dataPath);
		if(is_array($tmp) && isset($tmp['skypetoken'])) {
			$this->skypeToken = $tmp['skypetoken'];
			$this->skypeTokenExpires = (int)$tmp['expires_in'];
			$this->skypeTokenAuth();
			return true;
		} else {
			return false;
		}
    }

	/**
	 *  @brief undocument function
	 *  
	 *  @return boolean
	 */
	public function pingWebHost() {
		if(!empty($this->webSessionId)) {
			$Request = new Endpoint('POST', 'https://web.skype.com/api/v1/session-ping');
			$Request->needSkypeToken();
			$Response = $this->request($Request, ['debug' => false, 'form_params' => ['sessionId' => $this->webSessionId]]);
			return 200 == $Response->getStatusCode();
		}
		return false;
	}

	/**
	 *  @brief set authentication for ASM servers
	 *  
	 *  @return boolean
	 */
	public function skypeTokenAuth() {
		$Response = $this->request('asm', [
				'debug' => false, 
				'form_params' => ['skypetoken' => $this->skypeToken],
				'headers' => ['X-Client-Version' => self::CLIENT_VERSION]
			]);
		return 204 == $Response->getStatusCode();
	}

	/**
	 *  @brief undocumented function
	 *  
	 *  @return object token data
	 */
	public function getPeToken() {
		$Request = new Endpoint('GET', 'https://static.asm.skype.com/pes/v1/petoken');
		$Result = $this->request($Request, ['debug' => false, 'headers' => ['Authorization' => 'skype_token '.$this->skypeToken]]);
		$tmp = json_decode($Result->getBody());
		return 200 == $Result->getStatusCode() && is_object($tmp) && isset($tmp->token) ? $tmp : null;
	}
	
    /**
     *  @brief sign out
     *  
     *  @return always true
     */
    public function logout() {
		echo 'logging out', PHP_EOL;
		$this->request('logout');
        return true;
    }

	/**
	 *  @brief init the messaging environment (endpoint, presenceDoc, subscriptions, ...)
	 *  
	 *  @return boolean
	 */
	public function enableMessaging() {
		$ret = false;
		if($this->setRegistrationToken()) {
			$this->createStatusEndpoint();
			$this->subscribeToResources();
			$this->endpointSetSupportMessageProperties();
			$ret = true;
		} else {
			$ret = false;
		}
		return $ret;
	}

	/**
	 *  @brief free messaging resources
	 *  
	 *  @param boolean $deleteEndpoint endpoint can be saved for next session
	 *  @return void
	 */
	public function disableMessaging($deleteEndpoint=false) {
		$this->unsubscribeToResources();
		if(true===$deleteEndpoint && $this->setEndpointFeaturesAgent(array('url' => $this->endpointUrl, 'key' => $this->regToken))) {
			$this->deleteEndpoint();
		}
	}

    /**
     *  @brief subscribe to messaging endpoints
     *  
     *  @return the URL of the created endpoint of false on error
     */
    public function subscribeToResources()
    {
        $Request = new Endpoint('POST', 'https://%sclient-s.gateway.messenger.live.com/v1/users/ME/endpoints/SELF/subscriptions');
        $Request->needRegToken();

        $Response = $this->request($Request, [
			'format' => [$this->cloud ? $this->cloud : ''],
            'json' => [
                'interestedResources' => [
                    '/v1/threads/ALL',
                    '/v1/users/ME/contacts/ALL',
                    '/v1/users/ME/conversations/ALL/messages',
                    '/v1/users/ME/conversations/ALL/properties',
                ],
                'template' => 'raw',
                'channelType' => 'httpLongPoll'
            ]
        ]);
		if(201 == $Response->getStatusCode()) {
			$this->endpointSubscriptionsUrl = $Response->getHeader('Location')[0];
			return $this->endpointSubscriptionsUrl;
		} else {
			return false;
		}
    }

	/**
	 *  @brief free the subscriptions endpoint
	 *  
	 *  @return boolean
	 */
	public function unsubscribeToResources() {
		if($this->endpointSubscriptionsUrl) {
			$Request = new Endpoint('DELETE', $this->endpointSubscriptionsUrl);
			$Request->needRegToken();
			$Response = $this->request($Request, ['debug' => false]);
			return 200 == $Response->getStatusCode();
		}
		return true;
	}

    /**
     *  @brief create a new presenceDoc endpoint
     *  
     *  @return URL of the created endpoint of false on error
     */
    public function createStatusEndpoint()
    {
        $Request = new Endpoint('PUT', 'https://%sclient-s.gateway.messenger.live.com/v1/users/ME/endpoints/SELF/presenceDocs/messagingService');
        $Request->needRegToken();

        $Response = $this->request($Request, [
			'debug' => false,
			'format' => [$this->cloud ? $this->cloud : ''],
            'json' => [
                'id' => 'messagingService',
                'type' => 'EndpointPresenceDoc',
                'selfLink' => 'uri',
                'privateInfo' =>  ['epname' => 'skype'],
                'publicInfo' =>  [
                    'capabilities' => 'video|audio',
                    'type' => 1,
                    'skypeNameVersion' => self::CLIENTINFO_NAME,
                    'nodeInfo' => 'xx',
                    'version' => self::CLIENT_VERSION,
                ],
            ]
        ]);
		$ret = json_decode($Response->getBody());
		if(200 == $Response->getStatusCode() && is_object($ret) && isset($ret->selfLink)) {
			$this->endpointPresenceDocUrl = $ret->selfLink;
			return $this->endpointPresenceDocUrl;
		} else {
			return false;
		}
    }

	/**
	 *  @brief free the main messaging endpoint
	 *  
	 *  @param int $expiresTreshold expiry treshold
	 *  @return boolean
	 */
	public function deleteEndpoint($expiresTreshold=60) {
		if($this->endpointUrl)  {
			$Request = new Endpoint('DELETE', $this->endpointUrl);
			$Request->needRegToken();
			$Response = $this->request($Request, ['debug' => false]);
			$header = $Response->getHeader('Set-RegistrationToken');
			if(count($header) > 0) {
				$sessionData = json_decode(file_get_contents($this->dataPath.$this->loginName.'-session.json'), true);
				$parts = explode(';', $header[0]);
				$sessionData['regToken']['key'] = trim($parts[0]);
				$sessionData['regToken']['expires'] = trim($parts[1]);
				$sessionData['regToken']['expires'] = (int)substr($sessionData['regToken']['expires'], strpos($sessionData['regToken']['expires'], '=')+1)-$expiresTreshold;
				$sessionData['regToken']['url'] = null;
				$sessionData['regToken']['endpointId'] = null;
				$sessionData['regToken']['cloudPrefix'] = null;
				$this->regToken = $sessionData['regToken']['key'];
				$this->regTokenExpires = $sessionData['regToken']['expires'];
				$this->endpointUrl = $sessionData['regToken']['url'];
				$this->endpointId = $sessionData['regToken']['endpointId'];
				$this->cloud = $sessionData['regToken']['cloudPrefix'];
				if(!file_put_contents($this->dataPath.$this->loginName.'-session.json', json_encode($sessionData, JSON_PRETTY_PRINT))) {
					echo 'session file write error' . PHP_EOL;
				}
			}
			return 200 == $Response->getStatusCode();
		} else {
			return true;
		}
	}

    /**
     *  @brief set user status
     *  
     *  @param string $status a string amongst Online, Busy, Hidden
     *  @return boolean
     */
    public function setStatus($status)
    {
        $Request = new Endpoint('PUT', 'https://%sclient-s.gateway.messenger.live.com/v1/users/ME/presenceDocs/messagingService');
        $Request->needRegToken();

        $Response = $this->request($Request, [
			'format' => [$this->cloud ? $this->cloud : ''],
            'json' => [
                'status' => $status
            ]
        ]);
		return 200 == $Response->getStatusCode(); 
    }

    /**
     *  @brief get the short version of current user's profile
     *  
     *  @return mixed a JSON value or null if error
     */
    public function loadProfile()
    {
        $Request = new Endpoint('GET', 'https://api.skype.com/users/self/displayname');
        $Request->needSkypeToken();

        $Response = $this->requestJSON($Request);

        return isset($Response->username) ? $Response : null;
    }

	/**
	 *  @brief get detialed version of current user's profile
	 *  
	 *  @return mixed a JSON value or null if error
	 */
	public function loadFullProfile() {
        $Request = new Endpoint('GET', 'https://api.skype.com/users/self/profile');
        $Request->needSkypeToken();

        $Response = $this->requestJSON($Request);

        return isset($Response->firstname) ? $Response : null;
	}

	/**
	 *  @brief update current user's profile
	 *  
	 *  @param array $data key, values pairs of fields to update
	 *  @return boolean
	 */
	public function updateProfile(array $data) {
		$Request = new Endpoint('POST', 'https://api.skype.com/users/self/profile/partial');
		$Request->needSkypeToken();
		$Result = $this->request($Request, [
            'json' => ['payload' => $data]
            ]);
        return 200 == $Result->getStatusCode();
	}

	/**
	 *  @brief update the current user's avatar
	 *  
	 *  @param string $filename filename
	 *  @return boolean
	 */
	public function updateAvatar($filename) {
		$Request = new Endpoint('PUT', 'https://avatar.skype.com/v1/avatars/%s');
		$Request->needSkypeToken();
		$filePointer = fopen($filename, 'rb');
		$Result = $this->request($Request, [
			'debug' => false,
			'format' => [$this->loginName],
			'headers' => [
				'Content-Length' => filesize($filename),
				'Accept' => 'application/json, text/javascript',
				'Accept-Encoding' => 'gzip, deflat',
				'Content-Type' => mime_content_type($filename)
			],
            'body' => $filePointer
            ]);
        return 200==$Result->getStatusCode();
	}

	/**
	 *  @brief download an avatar image
	 *  
	 *  @param string $url URL of image
	 *  @param string $targetDir the directory where the file should be written
	 *  @param string $basename optional. force name of downloaded file 
	 *  @return mixed path to the newly created file
	 */
	public function downloadAvatar($url, $targetDir, $basename=null) {
		$Request = new Endpoint('GET', $url);
		$Result = $this->request($Request, ['debug' => false]);
		if(200 == $Result->getStatusCode()) {
			if(null === $basename) {
				$downloadFilename = parse_url($url, PHP_URL_PATH);
				$downloadFilename = substr($downloadFilename, strrpos($downloadFilename, '/')+1);
			} else {
				$downloadFilename = $basename;
			}
			$downloadFilename = rawurlencode($downloadFilename);
			if(is_dir($targetDir) && is_writable($targetDir)) {
				if(file_put_contents($targetDir.DIRECTORY_SEPARATOR.$downloadFilename, $Result->getBody())) {
					$mime = mime_content_type($targetDir.DIRECTORY_SEPARATOR.$downloadFilename);
					$downloadFilenameWithExt = $downloadFilename.'.'.substr($mime, strpos($mime, '/')+1);
					rename($targetDir.DIRECTORY_SEPARATOR.$downloadFilename, $targetDir.DIRECTORY_SEPARATOR.$downloadFilenameWithExt);
					return $targetDir.DIRECTORY_SEPARATOR.$downloadFilenameWithExt;
				} else {
					echo 'file [',$targetDir, DIRECTORY_SEPARATOR, $downloadFilename, '] write_error', PHP_EOL;
					return null;
				}
			} else {
				echo '[', $targetDir,'] is not a directory or not writeable', PHP_EOL;
				return null;
			}
		} else {
			echo $Result->getStatusCode(), ' ', $Result->getBody(), PHP_EOL; 
			return null;
		}
	}
	
    /**
     *  @brief get the detailed list of current user's contacts
     *  
     *  @return mixed a JSON value (array) or null if error
     */
    public function loadContacts() {
        $Response = $this->requestJSON('contacts');
        return isset($Response->contacts) ? $Response->contacts : null;
    }

	/**
	 *  @brief get the list of current user defined groups
	 *  
	 *  @return mixed a JSON value (array) or null if error
	 */
	public function loadGroups() {
		$Request = new Endpoint('GET', 'https://contacts.skype.com/contacts/v2/users/self/groups');
		$Request->needSkypeToken();
		$Response = $this->requestJSON($Request, []);
		return isset($Response->groups) ? $Response->groups : null;
	}
	
	/**
	 *  @brief get the list of current user block list
	 *  
	 *  @return mixed a JSON value (array) or null if error
	 */
	public function getBlockList() {
		$Request = new Endpoint('GET', 'https://contacts.skype.com/contacts/v2/users/self/blocklist');
		$Request->needSkypeToken();
		$Result = $this->requestJSON($Request);
		return isset($Result->blocklist) ? $Result->blocklist : null;
	}
	
	/**
	 *  @brief load contacts, groups, blocklist in a single request 
	 *  
	 *  @return mixed a JSON value or null if error
	 */
	public function initLoadContacts() {
		$Request = new Endpoint('GET', 'https://contacts.skype.com/contacts/v2/users/self');
		$Request->needSkypeToken();
		$Result = $this->request($Request, ['debug' => false]);
		$ret = json_decode($Result->getBody());
		return 200 == $Result->getStatusCode() && is_object($ret) && isset($ret->contacts) ? $ret : null;
	}

	/**
	 *  @brief search a user in Skype directory
	 *  
	 *  @param string $searchstring lookup string
	 *  @return mixed a JSON value or null if error
	 */
	public function searchUserDirectory($searchstring) {
		$Request = new Endpoint('GET', 'https://skypegraph.skype.com/search/v1.1/namesearch/swx/?searchstring=%s&requestId=%s');
		$Request->needSkypeToken();
		$Response = $this->request($Request, ['debug' => false, 'format' => [rawurlencode($searchstring), substr(uniqid(), -6, 6)]]);
		$ret = json_decode($Response->getBody());
		return 200 == $Response->getStatusCode() && is_object($ret) && isset($ret->results) ? $ret->results : null;
	}
	
	/**
	 *  @brief send an authorization request to a user
	 *  
	 *  @param string $mri target user as MRI
	 *  @param string $greeting custom text
	 *  @return boolean
	 */
	public function sendContactRequest($mri, $greeting) {
		$Request = new Endpoint('POST', 'https://contacts.skype.com/contacts/v2/users/self/contacts');
		$Request->needSkypeToken();
		$Result = $this->request($Request, ['debug' => false, 'json' => ['mri' => $mri, 'greeting' => $greeting]]);
		return 200 == $Result->getStatusCode() ? true : false;
	}

	/**
	 *  @brief get the list of pending authorization requests
	 *  
	 *  @return mixed a JSON value or null on error
	 */
	public function getInvites() {
		$Request = new Endpoint('GET', 'https://contacts.skype.com/contacts/v2/users/self/invites');
		$Request->needSkypeToken();
		$Result = $this->requestJson($Request, ['debug' => false]);
		return is_object($Result)||is_array($Result) ? $Result : null;
	}

	/**
	 *  @brief accept or decline a pending authorization request
	 *  
	 *  @param string $mri the user emitting the request (as a MRI)
	 *  @param string $action a value in [accept, decline]
	 *  @return boolean
	 */
	public function acceptOrDeclineInvite($mri, $action) {
		if(!in_array($action, ['decline', 'accept'])) {
			return false;
		}
		$Request = new Endpoint('PUT', 'https://contacts.skype.com/contacts/v2/users/self/invites/%s/%s');
		$Request->needSkypeToken();
		$Result = $this->request($Request, ['debug' => false, 'format' => [$mri, $action]]);
		return 200==$Result->getStatusCode() ? true : false;
	}

	/**
	 *  @brief add a user to the blocklist
	 *  
	 *  @param string $mri target user (as an MRI)
	 *  @return boolean
	 */
	public function blockContact($mri) {
		$Request = new Endpoint('PUT', 'https://contacts.skype.com/contacts/v2/users/self/contacts/blocklist/%s');
		$Request->needSkypeToken();
		$Result = $this->request($Request, ['debug' => false, 'format' => [$mri], 'json' => ['ui_version' => self::CLIENTINFO_NAME, 'report_abuse' => false]]);
		return 200 == $Result->getStatusCode();
	}

	/**
	 *  @brief remove a user from the blocklist
	 *  
	 *  @param string $mri target user (as an MRI)
	 *  @return boolean
	 */
	public function unblockContact($mri) {
		$Request = new Endpoint('DELETE', 'https://contacts.skype.com/contacts/v2/users/self/contacts/blocklist/%s');
		$Request->needSkypeToken();
		$Result = $this->request($Request, ['debug' => false, 'format' => [$mri]]);
		return 200 == $Result->getStatusCode();
	}
	
	/**
	 *  @brief remove authorization for a user
	 *  
	 *  @param string $mri target user (as an MRI)
	 *  @return boolean
	 */
	public function deleteContact($mri) {
		$Request = new Endpoint('DELETE', 'https://contacts.skype.com/contacts/v2/users/self/contacts/%s');
		$Request->needSkypeToken();
		$Result = $this->request($Request, [
				'debug' => false,
				'format' => [$mri],
				'headers' => [
					'Accept' => 'application/json; ver=1.0',
					'Content-Type' => 'application/json',
					'Content-Length' => 0
				]
			]
		);
		return 200==$Result->getStatusCode() ? true : false;
	}

	/**
	 *  @brief use registration token and endpoint from session or fetch a new one
	 *  
	 *  @param int $expiresTreshold expiry treshold
	 *  @return mixed boolean or new endpoint URL
	 */
	public function setRegistrationToken($expiresTreshold=60) {
		$ret = false;
		$sessionData = json_decode(file_get_contents($this->dataPath.$this->loginName.'-session.json'), true);
		$this->regToken = $sessionData['regToken']['key'];
		$this->regTokenExpires = $sessionData['regToken']['expires'];
		$this->endpointUrl = $sessionData['regToken']['url'];
		$this->endpointId = $sessionData['regToken']['endpointId'];
		$this->cloud = $sessionData['regToken']['cloudPrefix'];

		$sessionData['regToken']['expires'] = (int)$sessionData['regToken']['expires'];
		$regTokenExpired = $sessionData['regToken']['expires']<time();
		
		if(true===$regTokenExpired) {
			echo 'registration token has expired',PHP_EOL;
			$fetch = true;
		} else {
			if(empty($sessionData['regToken']['url'])) {
				echo 'session has no endpoint URL', PHP_EOL;
				$fetch = true;
			} else {
				$fetch = !$this->setEndpointFeaturesAgent($sessionData['regToken']);
				if($fetch) {
					echo 'session endpoint probing failed', PHP_EOL;
				}
			}
		}
		if($fetch) {
			echo 'fetch a new registration token', PHP_EOL;
			$ts = time();;
			$Response = $this->request('endpoint', [
				'debug' => false,
				'headers' => [
					'Authentication' => 'skypetoken='.$this->skypeToken,
					'LockAndKey' => 'appId='.self::LOCKANDKEY_APPID.'; time='.$ts.'; lockAndKeyResponse='.self::getMac256Hash($ts, self::LOCKANDKEY_SECRET)					],
				'json' => ['skypetoken' => $this->skypeToken]
			]);
			if(201==$Response->getStatusCode()) {
				$header = $Response->getHeader('Set-RegistrationToken');
				if (count($header) > 0) {
					$parts = explode(';', $header[0]);
					$sessionData['regToken']['key'] = trim($parts[0]);
					$sessionData['regToken']['expires'] = trim($parts[1]);
					$sessionData['regToken']['expires'] = (int)substr($sessionData['regToken']['expires'], strpos($sessionData['regToken']['expires'], '=')+1)-$expiresTreshold;
					$sessionData['regToken']['endpointId'] = trim($parts[2]);
					$sessionData['regToken']['endpointId'] = substr($sessionData['regToken']['endpointId'], strpos($sessionData['regToken']['endpointId'], '=')+1);
					$header = $Response->getHeader('Location');
					if (count($header) > 0) {
						$sessionData['regToken']['url'] = ltrim($header[0]);
						$matches = array();
						preg_match('#https?://([^-]*-)client\-s#', $sessionData['regToken']['url'], $matches);
						if (array_key_exists(1, $matches)) {
							$sessionData['regToken']['cloudPrefix'] = $matches[1];
						} else {
							$sessionData['regToken']['cloudPrefix'] = '';
						}
					}
					$this->regToken = $sessionData['regToken']['key'];
					$this->regTokenExpires = $sessionData['regToken']['expires'];
					$this->endpointUrl = $sessionData['regToken']['url'];
					$this->endpointId = $sessionData['regToken']['endpointId'];
					$this->cloud = $sessionData['regToken']['cloudPrefix'];
					if(!file_put_contents($this->dataPath.$this->loginName.'-session.json', json_encode($sessionData, JSON_PRETTY_PRINT))) {
						echo 'session file write error' . PHP_EOL;
					}
					$ret = $this->endpointUrl;
				}
			} else {
				$ret = false;
			}
		} else {
			$ret = true;
		}
		return $ret;
	}

	/**
	 *  @brief set agent attribute to currentenpoint (also used to probe endpoint)
	 *  
	 *  @param array $sessionData an array containing endpoint URL and registration token
	 *  @return boolean
	 */
	public function setEndpointFeaturesAgent(array $sessionData) {
		if(empty($sessionData['url']) || empty($sessionData['key'])) {
			echo 'empty session data', PHP_EOL;
			return false;
		} else {
			$Request = new Endpoint('PUT', $sessionData['url']);
			$Response = $this->request($Request, [
				'debug' => false,
				'headers' => [
					'RegistrationToken' => $sessionData['key']
				],
				'json' => [
					'endpointFeatures' => 'Agent'
				]
			]);
			$ret = (200 == $Response->getStatusCode());
			if(!$ret) {
				echo $Response->getStatusCode(), ' ', $Response->getBody(), PHP_EOL;
			}
			return $ret;
		}
	}

	/**
	 *  @brief set the supportsMessageProperties flag to true
	 *  
	 *  @return boolean
	 */
	public function endpointSetSupportMessageProperties() {
		$Request = new Endpoint('PUT', 'https://%sclient-s.gateway.messenger.live.com/v1/users/ME/endpoints/SELF/properties?name=supportsMessageProperties');
		$Request->needRegToken();
		$Response = $this->request($Request, ['debug' => false, 'format' => [$this->cloud ? $this->cloud : ''], 'json' => ['supportsMessageProperties' => true]]);
		return 200 == $Response->getStatusCode();
	}

	/**
	 *  @brief self explanatory
	 *  
	 *  @return boolean
	 */
	public function pingGateway() {
		$Request = new Endpoint('GET', 'https://%sclient-s.gateway.messenger.live.com/v1/ng/ping');
		$Response = $this->request($Request, [
			'debug' => false,
			'format' => [$this->cloud ? $this->cloud : ''],
            'headers' => [
				'Content-Type' => 'application/json'
			]
        ]);
		return 200 == $Response->getStatusCode();
	}

	/**
	 *  @brief set TTL for current endpoint
	 *  
	 *  @return boolean
	 */
	public function endpointTtl($ttl=12) {
		$Request = new Endpoint('POST', 'https://%sclient-s.gateway.messenger.live.com/v1/users/ME/endpoints/SELF/active');
		$Response = $this->request($Request, [
			'debug' => false,
			'format' => [$this->cloud ? $this->cloud : ''],
            'headers' => ['Content-Type' => 'application/json'],
			'json' => ['timeout' => $ttl]
        ]);
		return 201 == $Response->getStatusCode();
	}
	
	/**
	 *  @brief get user properties in the current messaging context
	 *  
	 *  @return mixed a JSON value or null if error
	 */
	public function messagingGetMyProperties() {
		$Request = new Endpoint('GET', 'https://%sclient-s.gateway.messenger.live.com/v1/users/ME/properties');
		$Request->needRegToken();
		$Response = $this->request($Request, ['debug' => false, 'format' => [$this->cloud ? $this->cloud : '']]);
		$ret = json_decode($Response->getBody());
		return 200 == $Response->getStatusCode() && is_object($ret) && isset($ret->lastActivityAt) ? $ret : null;
	}

	/**
	 *  @brief list of connected endpoints and availability
	 *  
	 *  @return mixed a JSON value or null if error
	 */
	public function messagingGetMyPresenceDocs() {
		$Request = new Endpoint('GET', 'https://%sclient-s.gateway.messenger.live.com/v1/users/ME/presenceDocs/messagingService?view=expanded');
		$Request->needRegToken();
		$Response = $this->request($Request, ['debug' => false, 'format' => [$this->cloud ? $this->cloud : '']]);
		$ret = json_decode($Response->getBody());
		return 200 == $Response->getStatusCode() && is_object($ret) && isset($ret->type) ? $ret : null;
	}
	
	/**
	 *  @brief add a contact to the conversation list
	 *  
	 *  @param string $mri MRI of target user
	 *  @return boolean
	 */
	public function messagingAddContact($mri) {
		$Request = new Endpoint('PUT', 'https://%sclient-s.gateway.messenger.live.com/v1/users/ME/contacts/%s');
		$Request->needRegToken();
		$Response = $this->request($Request, [
									'debug' => false, 
									'format' => [$this->cloud ? $this->cloud : '', $mri],
									'headers' => ['Content-Length' => 0]
								]);
		return 200 == $Response->getStatusCode();
	}
	
	/**
	 *  @brief undocumented feature
	 *  
	 *  @param array $mriList list of target users (as MRIs)
	 *  @return boolean
	 */
	public function messagingPostContacts(array $mriList) {
		if(0==count($mriList)) {
			return false;
		}
		$objectsList = [];
		while(null !== ($mri = array_shift($mriList))) {
			$objectsList[] = ['id' => $mri];
		}
		$Request = new Endpoint('POST', 'https://%sclient-s.gateway.messenger.live.com/v1/users/ME/contacts');
		$Request->needRegToken();
		$Response = $this->request($Request, [
				'debug' => false, 
				'format' => [$this->cloud ? $this->cloud : ''],
				'json' => $objectsList
			]);
		return 201 == $Response->getStatusCode();
	}

	/**
	 *  @brief get the list of current user's conversations
	 *  
	 *  @return mixed a JSON value or null if error
	 */
	public function loadConversations() {
		$Request = new Endpoint('GET', 'https://%sclient-s.gateway.messenger.live.com/v1/users/ME/conversations?view=msnp24Equivalent&startTime=0&targetType=Passport|Skype|Lync|Thread|Agent');
		$Request->needRegToken();
		$Response = $this->request($Request, ['debug' => false, 'format' => [$this->cloud ? $this->cloud : '']]);
		$json = json_decode($Response->getBody());
		if(200 == $Response->getStatusCode() && is_object($json) && isset($json->conversations)) {
			foreach($json->conversations as $ndx => $conversation) {
				if(isset($conversation->threadProperties) && isset($conversation->threadProperties->members)) {
					if(!is_array($conversation->threadProperties->members)) {
						$json->conversations[$ndx]->threadProperties->members = json_decode($json->conversations[$ndx]->threadProperties->members);
					}
				}
			}
			return $json->conversations;
		} else {
			return null;
		}
	}
	
	/**
	 *  @brief set conversation as empty
	 *  
	 *  @param string $mri target user (as MRI)
	 *  @return boolean
	 */
	public function deleteConversation($mri) {
        $Request = new Endpoint('DELETE', 'https://%sclient-s.gateway.messenger.live.com/v1/users/ME/conversations/%s/messages');
        $Request->needRegToken();
        $Response = $this->request($Request, [
			'debug' => false,
            'format' => [$this->cloud ? $this->cloud : '', $mri]
        ]);
		return 200 == $Response->getStatusCode() ? true : false;
	}
	
    /**
     *  @brief send a text/richText message
     *  
     *  @param string $mri target user (as MRI)
     *  @param string $userDisplayname target user display name
     *  @param string $text message content
     *  @param int $edit_id Id of message in case of message edition
     *  @return mixed Id of message sent or edited
     */
    public function send($mri, $userDisplayname, $text, $edit_id = false) {
        $milliseconds = round(microtime(true) * 1000);
		if(false === strpos($mri, ':')) {
			$mri = '8:live:'.$mri;
		}

        $message_json = [
            'content' => $text,
            'messagetype' => 'RichText',
			'Has-Mentions' => 'false',
			'imdisplayname' => $userDisplayname,
            'contenttype' => 'text',
            'clientmessageid' => (string)$milliseconds,
        ];

        if ($edit_id){
            $message_json['skypeeditedid'] = $edit_id;
            unset($message_json['clientmessageid']);
        }

        $Response = $this->requestJSON('send_message', [
            'json' => $message_json,
            'format' => [$this->cloud ? $this->cloud : '', $mri]
        ]);
        if (is_object($Response) && isset($Response->OriginalArrivalTime)){//if successful sended
            return $milliseconds;//message ID
        }else{
			print_r($Response);
            return false;
        }
    }

	/**
	 *  @brief send a contact card to a user
	 *  
	 *  @param [in] $mri target user (as MRI)
	 *  @param [in] $fromDisplayname display name of the sender
	 *  @param [in] $contactMri MRI of card contact
	 *  @param [in] $contactDisplayname display name of card contact
	 *  @return mixed id of the sent message or false if error
	 */
	public function sendContact($mri, $fromDisplayname, $contactMri, $contactDisplayname=null) {
		if(null === $contactDisplayname) {
			$contactDisplayname = $contactMri;
		}
		$clientmessageid = round(microtime(true) * 1000);
        $Request = new Endpoint('POST', 'https://%sclient-s.gateway.messenger.live.com/v1/users/ME/conversations/%s/messages');
        $Request->needRegToken();
		$requestBody = '{"content":"<contacts><c t=\"s\" s=\"'.$contactMri.'\" f=\"'.$contactDisplayname.'\"/></contacts>","messagetype":"RichText/Contacts", "contenttype":"text", "Has-Mentions":"false", "imdisplayname":"'.$fromDisplayname.'", "clientmessageid":"'.$clientmessageid.'"}'; 
        $Response = $this->request($Request, [
			'debug' => false,
            'format' => [$this->cloud ? $this->cloud : '', $mri],
			'headers' => ['Content-Type' => 'application/json', 'Content-Length' => strlen($requestBody)],
			'body' => $requestBody
        ]);
		return 201 === $Response->getStatusCode() ? $clientmessageid : false;
	}

	/**
	 *  @brief share an image with a list of users
	 *  
	 *  @param array $mrisWithAccessRights a list of MRIs with permissions (ex: [[joe.bloggs => ['read']], ['john.doe' => ['read']]])
	 *  @param [in] $filename local path to the file to share
	 *  @param [in] $fromDisplayname display name of the sender
	 *  @return mixed array of upload infos or false if error
	 */
	public function sendImage($mrisWithAccessRights, $filename, $fromDisplayname) {
		$ret = false;
		if(is_file($filename) && is_readable($filename)) {
			$Request = new Endpoint('POST', 'https://api.asm.skype.com/v1/objects');
			$Response = $this->request($Request, [
				'debug' => false,
				'headers' => [
					'X-Client-Version' => self::CLIENT_VERSION,
					'Authorization' => 'skype_token '.$this->skypeToken,
				],
				'json' => ['type' => 'pish/image', 'permissions' => $mrisWithAccessRights]
			]);
			$tmp = json_decode($Response->getBody());
			if(201 == $Response->getStatusCode() && is_object($tmp) && isset($tmp->id)) {
				$slotId = $tmp->id;
				$filePointer = fopen($filename, 'rb');
				if(is_resource($filePointer)) {
					$Request = new Endpoint('PUT', 'https://api.asm.skype.com/v1/objects/'.$tmp->id.'/content/imgpsh');
					$Response = $this->request($Request, [
						'debug' => false,
						'headers' => [
							'Authorization' => 'skype_token '.$this->skypeToken,
							'Content-Length' => filesize($filename),
							'Content-Type' => mime_content_type($filename),
							'Accept-Encoding' => 'gzip, deflat',
							'Accept' => '*/*'
						],
						'body' => $filePointer
					]);
					if(201 == $Response->getStatusCode()) {
						sleep(1);
						$ready = false;
						$Request = new Endpoint('GET', 'https://api.asm.skype.com/v1/objects/'.$tmp->id.'/views/imgt1/status');
						$Response = $this->request($Request, [
							'debug' => false,
							'headers' => [
								'Authorization' => 'skype_token '.$this->skypeToken,
								'Accept-Encoding' => 'gzip, deflat',
								'Accept' => 'application/json, text/javascript'
							]
						]);
						$tmp = json_decode($Response->getBody());
						if(200 == $Response->getStatusCode() && is_object($tmp) && isset($tmp->view_state)) {
							$statusUrl = $tmp->status_location;
							$Request = new Endpoint('GET', $statusUrl);
							while(false === $ready) {
								sleep(1);
								$Response = $this->request($Request, [
									'debug' => false,
									'headers' => [
										'Authorization' => 'skype_token '.$this->skypeToken,
										'Accept-Encoding' => 'gzip, deflat',
										'Accept' => 'application/json, text/javascript'
									]
								]);
								$tmp = json_decode($Response->getBody());
								if(200 == $Response->getStatusCode() && is_object($tmp) && isset($tmp->view_state)) {
									if('ready' == $tmp->view_state) {
										$ready = true;
										$ret = $tmp;
										$imageUri = rtrim(substr($tmp->view_location, 0, strpos($tmp->view_location, $slotId) + strlen($slotId)),'/');
										$messageJson = '{"content": "<URIObject type=\"Picture.1\" uri=\"'.$imageUri.'\" url_thumbnail=\"'.$tmp->view_location.'\">To view this shared photo, go to: <a href=\"https://login.skype.com/login/sso?go=webclient.xmm&amp;pic='.$slotId.'\">https://login.skype.com/login/sso?go=webclient.xmm&amp;pic='.$slotId.'</a><OriginalName v=\"'.basename($filename).'\"/><meta type=\"photo\" originalName=\"'.basename($filename).'\"/></URIObject>","messagetype": "RichText/UriObject",	"contenttype": "text",	"Has-Mentions": "false",	"imdisplayname": "'.addslashes($fromDisplayname).'",	"clientmessageid": '.round(microtime(true) * 1000).'}';
										foreach($mrisWithAccessRights as $mriTo => $mriToPerms) {
											$Request = new Endpoint('POST', 'https://%sclient-s.gateway.messenger.live.com/v1/users/ME/conversations/%s/messages');
											$Request->needRegToken();
											$Response = $this->request($Request, [
												'debug' => false,
												'format' => [$this->cloud ? $this->cloud : '', $mriTo],
												'headers' => ['Content-Type' => 'application/json'],
												'body' => $messageJson
											]);
										}
									}
								}
							}
						} else {
							echo 'img status KO';
						}
						
						
					} else {
						echo 'img upload KO', PHP_EOL;
						echo $Response->getStatusCode(),' ', $Response->getBody(), PHP_EOL;
					}
				} else {
					unset($filepointer);
				}
			} else {
				echo 'img slot KO', PHP_EOL;
				echo $Response->getStatusCode(),' ', $Response->getBody(), PHP_EOL;
			}
		}
		return $ret;
	}

	/**
	 *  @brief share a file with a list of users
	 *  
	 *  @param array $mrisWithAccessRights a list of MRIs with permissions (ex: [[joe.bloggs => ['read']], ['john.doe' => ['read']]])
	 *  @param [in] $filename local path to the file to share
	 *  @param [in] $fromDisplayname display name of the sender
	 *  @return mixed array of upload infos or false if error
	 */
	public function sendFile($mrisWithAccessRights, $filename, $fromDisplayname) {
		$ret = false;
		if(is_file($filename) && is_readable($filename)) {
			$Request = new Endpoint('POST', 'https://api.asm.skype.com/v1/objects');
			$Response = $this->request($Request, [
				'debug' => false,
				'headers' => [
					'X-Client-Version' => self::CLIENT_VERSION,
					'Authorization' => 'skype_token '.$this->skypeToken,
				],
				'json' => ['type' => 'sharing/file', 'filename' => basename($filename), 'permissions' => $mrisWithAccessRights]
			]);
			$tmp = json_decode($Response->getBody());
			if(201 == $Response->getStatusCode() && is_object($tmp) && isset($tmp->id)) {
				$slotId = $tmp->id;
				$filePointer = fopen($filename, 'rb');
				if(is_resource($filePointer)) {
					$Request = new Endpoint('PUT', 'https://api.asm.skype.com/v1/objects/'.$tmp->id.'/content/original');
					$Response = $this->request($Request, [
						'debug' => false,
						'headers' => [
							'Authorization' => 'skype_token '.$this->skypeToken,
							'Content-Length' => filesize($filename),
							'Content-Type' => 'application/octet-stream',
							'Accept-Encoding' => 'gzip, deflat',
							'Accept' => '*/*'
						],
						'body' => $filePointer
					]);
					if(201 == $Response->getStatusCode()) {
						$ready = false;
						sleep(1);
						$Request = new Endpoint('GET', 'https://api.asm.skype.com/v1/objects/'.$tmp->id.'/views/thumbnail/status');
						$Response = $this->request($Request, [
							'debug' => false,
							'headers' => [
								'Authorization' => 'skype_token '.$this->skypeToken,
								'Accept-Encoding' => 'gzip, deflat',
								'Accept' => 'application/json, text/javascript'
							]
						]);
						$tmp = json_decode($Response->getBody());
						if(200 == $Response->getStatusCode() && is_object($tmp) && isset($tmp->view_state)) {
							$statusUrl = $tmp->status_location;
							$Request = new Endpoint('GET', $statusUrl); 
							while(false === $ready) {
								sleep(1);
								$Response = $this->request($Request, [
									'debug' => false,
									'headers' => [
										'Authorization' => 'skype_token '.$this->skypeToken,
										'Accept-Encoding' => 'gzip, deflat',
										'Accept' => 'application/json, text/javascript'
									]
								]);
								$tmp = json_decode($Response->getBody());
								if(200 == $Response->getStatusCode() && is_object($tmp) && isset($tmp->view_state)) {
									if('ready' == $tmp->view_state) {
										$ready = true;
										if('passed' == $tmp->scan->status) {
											$ret = $tmp;
											$fileUri = rtrim(substr($tmp->view_location, 0, strpos($tmp->view_location, $slotId) + strlen($slotId)),'/');
											$messageJson = '{"content":"<URIObject type=\"File.1\" uri=\"'.$fileUri.'\" url_thumbnail=\"'.$tmp->view_location.'\"><Title>Title: '.basename($filename).'</Title><Description> Description: '.basename($filename).'</Description><a href=\"https://login.skype.com/login/sso?go=webclient.xmm&amp;docid='.$slotId.'\"> https://login.skype.com/login/sso?go=webclient.xmm&amp;docid='.$slotId.'</a><OriginalName v=\"'.basename($filename).'\"/><FileSize v=\"'.filesize($filename).'\"/></URIObject>","messagetype":"RichText/Media_GenericFile","contenttype":"text","Has-Mentions":"false","imdisplayname":"'.addslashes($fromDisplayname).'","clientmessageid":'.round(microtime(true) * 1000).'}';
											foreach($mrisWithAccessRights as $mriTo => $mriToPerms) {
												$Request = new Endpoint('POST', 'https://%sclient-s.gateway.messenger.live.com/v1/users/ME/conversations/%s/messages');
												$Request->needRegToken();
												$Response = $this->request($Request, [
													'debug' => false,
													'format' => [$this->cloud ? $this->cloud : '', $mriTo],
													'headers' => ['Content-Type' => 'application/json'],
													'body' => $messageJson
												]);
											}
										} else {
											$ret = false;
										}
									}
								}
							}
						} else {
							echo 'file status KO', PHP_EOL;
						}
					} else {
						echo 'file upload KO', PHP_EOL;
						echo $Response->getStatusCode(),' ', $Response->getBody(), PHP_EOL;
					}
				} else {
					unset($filepointer);
				}
			} else {
				echo 'file slot KO', PHP_EOL;
				echo $Response->getStatusCode(),' ', $Response->getBody(), PHP_EOL;
			}
		}
		return $ret;
	}
	
    /**
     *  @brief get new messages according to subscriptions previously registered
     *  
     *  @return mixed a JSON value or null if error
     */
    public function getNewMessages(){
        $Request = new Endpoint('POST', $this->endpointSubscriptionsUrl.'/poll');
        $Request->needRegToken();
        $Response = $this->request($Request, [
				'curl' => [CURLOPT_TIMEOUT => 5, CURLOPT_ENCODING => 'identity'],
				'debug' => false,
				'form_params' => ['endpoint' => $this->endpointId],
				'headers' => [
					'Content-Type' => 'application/x-www-form-urlencoded', 
					'Accept' => 'application/json, text/javascript',
					'Expires' => 0,
					'Content-Length' => 0
				],
				'form_params' => []
			]);
		if(204==$Response->getStatusCode()) {
			return null;
		} else if(404 == $Response->getStatusCode()) {
			$this->disableMessaging();
			$this->enableMessaging();
			return null;
		} else {
			$Response = json_decode($Response->getBody());
			return isset($Response->eventMessages) ? $Response->eventMessages : null;
		}
    }

	/**
	 *  @brief initiate a new thread
	 *  
	 *  @param array $members members with roles in the new thread (ex: [[id=>joe.bloggs, role=>User],[john.doe=>Admin]])
	 *  @return mixed url of the new thread or false if error
	 */
	public function initiateThread(array $members) {
        $Request = new Endpoint('POST', 'https://%sclient-s.gateway.messenger.live.com/v1/threads');
        $Request->needRegToken();
        $Response = $this->request($Request, [
            'format' => [$this->cloud ? $this->cloud : ''],
			'json' => ['members' => $members]
        ]);
		$location = $Response->getHeader('Location');
		if(is_array($location)) {
			$location = $location[0];
			return $location;
		} else {
			return false;
		}
	}
	
	/**
	 *  @brief get infos on the selected thread
	 *  
	 *  @param string $id thread ID (as MRI)
	 *  @return mixed a JSON value or null if error
	 */
	public function threadInfos($id) {
        $Request = new Endpoint('GET', 'https://%sclient-s.gateway.messenger.live.com/v1/threads/%s?view=msnp24Equivalent');
        $Request->needRegToken();
        $Response = $this->requestJson($Request, [
            'format' => [$this->cloud ? $this->cloud : '', $id]
        ]);
		return isset($Response->id) ? $Response : null;
	}

	/**
	 *  @brief set the avatar of the selected thread
	 *  
	 *  @param string $id thread ID (as MRI)
	 *  @param array $perms permissions like [read]
	 *  @param string $filename local path of file to upload
	 *  @return mixed a JSON value or false if error
	 */
	public function setThreadAvatar($id, array $perms, $filename) {
		$ret = false;
		if(is_file($filename) && is_readable($filename)) {
			$Request = new Endpoint('POST', 'https://api.asm.skype.com/v1/objects');
			$Response = $this->request($Request, [
				'debug' => false,
				'headers' => [
					'X-Client-Version' => self::CLIENT_VERSION,
					'Authorization' => 'skype_token '.$this->skypeToken,
				],
				'json' => ['type' => 'avatar/group', 'permissions' => [$id => $perms]]
			]);
			$tmp = json_decode($Response->getBody());
			if(201 == $Response->getStatusCode() && is_object($tmp) && isset($tmp->id)) {
				$slotId = $tmp->id;
				$filePointer = fopen($filename, 'rb');
				if(is_resource($filePointer)) {
					$Request = new Endpoint('PUT', 'https://api.asm.skype.com/v1/objects/'.$tmp->id.'/content/avatar');
					$Response = $this->request($Request, [
						'debug' => false,
						'headers' => [
							'Authorization' => 'skype_token '.$this->skypeToken,
							'Content-Length' => filesize($filename),
							'Content-Type' => mime_content_type($filename),
							'Accept-Encoding' => 'gzip, deflat',
							'Accept' => '*/*'
						],
						'body' => $filePointer
					]);
					if(201 == $Response->getStatusCode()) {
						sleep(1);
						$ready = false;
						$Request = new Endpoint('GET', 'https://api.asm.skype.com/v1/objects/'.$tmp->id.'/views/avatar_fullsize/status');
						$Response = $this->request($Request, [
							'debug' => false,
							'headers' => [
								'Authorization' => 'skype_token '.$this->skypeToken,
								'Accept-Encoding' => 'gzip, deflat',
								'Accept' => 'application/json, text/javascript'
							]
						]);
						$tmp = json_decode($Response->getBody());
						if(200 == $Response->getStatusCode() && is_object($tmp) && isset($tmp->view_state)) {
							$statusUrl = $tmp->status_location;
							$Request = new Endpoint('GET', $statusUrl);
							while(false === $ready) {
								sleep(1);
								$Response = $this->request($Request, [
									'debug' => false,
									'headers' => [
										'Authorization' => 'skype_token '.$this->skypeToken,
										'Accept-Encoding' => 'gzip, deflat',
										'Accept' => 'application/json, text/javascript'
									]
								]);
								$tmp = json_decode($Response->getBody());
								if(200 == $Response->getStatusCode() && is_object($tmp) && isset($tmp->view_state)) {
									if('ready' == $tmp->view_state) {
										$ready = true;
										$ret = $tmp;
										$Request = new Endpoint('PUT', 'https://%sclient-s.gateway.messenger.live.com/v1/threads/%s/properties?name=picture');
										$Request->needRegToken();
										$Response = $this->request($Request, [
											'debug' => false,
											'format' => [$this->cloud ? $this->cloud : '', $id],
											'json' => ['picture' => 'URL@'.$tmp->view_location]
										]);
									}
								}
							}
						} else {
							echo 'img status KO';
						}
						
						
					} else {
						echo 'img upload KO', PHP_EOL;
						echo $Response->getStatusCode(),' ', $Response->getBody(), PHP_EOL;
					}
				} else {
					unset($filepointer);
				}
			} else {
				echo 'img slot KO', PHP_EOL;
				echo $Response->getStatusCode(),' ', $Response->getBody(), PHP_EOL;
			}
		}
		return $ret;
	}

	/**
	 *  @brief add a member or edit role if member exists
	 *  
	 *  @param string $id MRI of selected thread
	 *  @param string $addId MRI of the target user
	 *  @param string $role a value in [User, Admin]
	 *  @return boolean
	 */
	public function addOrEditThreadMember($id, $addId, $role) {
        $Request = new Endpoint('PUT', 'https://%sclient-s.gateway.messenger.live.com/v1/threads/%s/members/%s');
        $Request->needRegToken();
        $Response = $this->request($Request, [
            'format' => [$this->cloud ? $this->cloud : '', $id, $addId],
			'json' => ['role' => $role]
        ]);
		return 200 == $Response->getStatusCode();
	}
	
	/**
	 *  @brief kick a member in selected thread
	 *  
	 *  @param [in] $id MRI of the selected thread
	 *  @param [in] $rmId MRI of the target user
	 *  @return boolean
	 */
	public function removeThreadMember($id, $rmId) {
        $Request = new Endpoint('DELETE', 'https://%sclient-s.gateway.messenger.live.com/v1/threads/%s/members/%s');
        $Request->needRegToken();
        $Response = $this->request($Request, [
            'format' => [$this->cloud ? $this->cloud : '', $id, $rmId]
        ]);
		return 200 == $Response->getStatusCode();
	}

	/**
	 *  @brief set a thread property in [joiningenabled, historydisclosed, topic]
	 *  
	 *  @param string $id MRI of selected thread
	 *  @param string $key property name
	 *  @param boolean $value property value
	 *  @return boolean
	 */
	public function setThreadProperty($id, $key, $value) {
		if(!in_array($key, ['joiningenabled', 'historydisclosed', 'topic'])) {
			echo 'property [', $key, '] not found', PHP_EOL;
		}
		$Request = new Endpoint('PUT', 'https://%sclient-s.gateway.messenger.live.com/v1/threads/%s/properties?name=%s');
		$Request->needRegToken();
        $Response = $this->request($Request, [
            'format' => [$this->cloud ? $this->cloud : '', $id, $key],
			'json' => [$key => $value]
        ]);
		return 200 == $Response->getStatusCode();
	}

	/**
	 *  @brief set conversation as empty
	 *  
	 *  @param string $id MRI of selected thread
	 *  @return boolean
	 */
	public function deleteThread($id) {
		$Request = new Endpoint('DELETE', 'https://%sclient-s.gateway.messenger.live.com/v1/users/ME/conversations/%s/messages');
		$Request->needRegToken();
        $Response = $this->request($Request, [
			'debug' => false,
            'format' => [$this->cloud ? $this->cloud : '', $id]
        ]);
		
		/*
        $Request = new Endpoint('DELETE', 'https://%sclient-s.gateway.messenger.live.com/v1/threads/%s');
        $Request->needRegToken();
        $Response = $this->request($Request, [
			'debug' => true,
            'format' => [$this->cloud ? $this->cloud : '', $id],
			'json' => []
        ]);
		echo $Response->getStatusCode(),' ', $Response->getBody(), PHP_EOL;
		*/
		return 200 == $Response->getStatusCode();
	}

	/**
	 *  @brief convert an integer to hexadecimal
	 *  
	 *  @param integer $n the integer to convert
	 *  @return string
	 */
	private static function int32ToHexString($n){
		$hexChars = '0123456789abcdef';
		$hexString = '';
		for($i=0; $i<4; $i++) {
			$hexString .= $hexChars[($n >> ($i * 8 + 4)) & 15];
			$hexString .= $hexChars[($n >> ($i * 8)) & 15];
		}
		return $hexString;
	}
	
	/**
	 *  @brief applay XOR on two integers
	 *  
	 *  @param int $a first parameter
	 *  @param int $b second parameter
	 *  @return integer
	 */
	private static function int64Xor($a, $b){
		$sA = decbin($a);
		$sB = decbin($b);
		$sC = '';
		$sD = '';
		$diff = abs(strlen($sA) - strlen($sB));
		for($i=0; $i<$diff; $i++) {
			$sD .= '0';
		}
		if(strlen($sA) < strlen($sB)) {
			$sD .= $sA;
			$sA = $sD;
		} else if(strlen($sB) < strlen($sA)) {
			$sD .= $sB;
			$sB = $sD;
		}
		for($i=0; $i<strlen($sA); $i++) {
			if($sA[$i] == $sB[$i]) {
				$sC .= '0';
			} else {
				$sC .= '1';
			}
		}
		return bindec($sC);
	}

	/**
	 *  @brief undocumented function
	 *  
	 *  @param array $pdwData pdwData
	 *  @param array $pInHash pInHash
	 *  @return array macs and sums
	 */
	private static function cS64($pdwData, $pInHash){
		$MODULUS = 2147483647;
		$CS64_a = $pInHash[0] & $MODULUS;
		$CS64_b = $pInHash[1] & $MODULUS;
		$CS64_c = $pInHash[2] & $MODULUS;
		$CS64_d = $pInHash[3] & $MODULUS;
		$CS64_e = 242854337;
		$pos = 0;
		$qwDatum = 0;
		$qwMAC = 0;
		$qwSum = 0;
		$iLoopBreak = floor(count($pdwData)/2);
		for($i=0; $i<$iLoopBreak; $i++) {
			$qwDatum = intval($pdwData[$pos]);
			$pos += 1;
			$qwDatum *= $CS64_e;
			$qwDatum = $qwDatum % $MODULUS;
			$qwMAC += $qwDatum;
			$qwMAC *= $CS64_a;
			$qwMAC += $CS64_b;
			$qwMAC = $qwMAC % $MODULUS;
			$qwSum += $qwMAC;
			$qwMAC += intval($pdwData[$pos]);
			$pos += 1;
			$qwMAC *= $CS64_c;
			$qwMAC += $CS64_d;
			$qwMAC = $qwMAC % $MODULUS;
			$qwSum += $qwMAC;
		}
		$qwMAC += $CS64_b;
		$qwMAC = $qwMAC % $MODULUS;
		$qwSum += $CS64_d;
		$qwSum = $qwSum % $MODULUS;
		return [$qwMAC, $qwSum];
	}

	/**
	 *  @brief compute the Skype hashmac256 of given value
	 *  
	 *  @param integer $challenge input value
	 *  @return string the computed hash
	 */
	public static function getMac256Hash($challenge) {
		$clearText = $challenge . self::LOCKANDKEY_APPID;
		$clearText .= str_repeat('0',  (8 - strlen($clearText) % 8));
		$cchClearText = floor(strlen($clearText) / 4);
		$pClearText = [];
		for($i=0; $i<$cchClearText; $i++) {
			$pClearText = array_merge(array_slice($pClearText, 0, $i) , [0] , array_slice($pClearText, $i));
			for($pos=0; $pos<4; $pos++) {
				$pClearText[$i] += ord($clearText[4 * $i + $pos]) * (256 ** $pos);
			}
		}
		$sha256Hash = [0, 0, 0, 0];
		$hash = strtoupper(hash('sha256', $challenge . self::LOCKANDKEY_SECRET,  false));
		$iLoopBreak = count($sha256Hash);
		for($i=0; $i<$iLoopBreak; $i++) {
			$sha256Hash[$i] = 0;
			for($pos=0; $pos<4; $pos++) {
				$dpos = 8 * $i + $pos * 2;
				$tmp = substr($hash, $dpos, 2);
				$sha256Hash[$i] += hexdec($tmp) * (256 ** $pos);
			}
		}
		$macHash = self::cS64($pClearText, $sha256Hash);
		$macParts = [$macHash[0], $macHash[1], $macHash[0], $macHash[1]];
		$ret = [];
		for($i=0; $i<4; $i++) {
			$ret[$i] = self::int32ToHexString(self::int64Xor($sha256Hash[$i], $macParts[$i]));
		}
		return join('', $ret);
	}
}