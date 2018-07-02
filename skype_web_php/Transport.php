<?php
namespace skype_web_php;

use Exception;
use DOMDocument;
use DOMXPath;

class Transport {

	const CLIENTINFO_NAME = 'skype.com';
	const CLIENT_VERSION = '908/1.118.0.30//skype.com';
	const LOCKANDKEY_APPID = 'msmsgs@msnmsgr.com';
	const LOCKANDKEY_SECRET = 'Q1P7W2E4J9R8U3S5';
	const SKYPE_WEB = 'web.skype.com';
	const CONTACTS_HOST = 'api.skype.com';
	const NEW_CONTACTS_HOST = 'contacts.skype.com';
	const DEFAULT_MESSAGES_HOST = 'client-s.gateway.messenger.live.com';
	const LOGIN_HOST = 'login.skype.com';
	const VIDEOMAIL_HOST = 'vm.skype.com';
	const XFER_HOST = 'api.asm.skype.com';
	const GRAPH_HOST = 'skypegraph.skype.com';
	const STATIC_HOST = 'static.asm.skype.com';
	const STATIC_CDN_HOST = 'static-asm.secure.skypeassets.com';
	const DEFAULT_CONTACT_SUGGESTIONS_HOST = 'peoplerecommendations.skype.com';

    /**
     * @var Client
     */
	private $webSessionId;
	private $loginName;
	private $password;
	private $username;
	private $dataPath;
    private $client;
    private $skypeToken;
	private $skypeTokenExpires;
    private $regToken;
	private $regTokenExpires;
	private $endpointUrl;
	private $endpointId;
	private $endpointPresenceDocUrl;
	private $endpointSubscriptionsUrl;
    private $cloud;

    /**
     * @var Endpoint []
     */
    private static $Endpoints = null;

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
		
		$this->client = new CurlRequestWrapper($this->dataPath.DIRECTORY_SEPARATOR.'curl'.DIRECTORY_SEPARATOR);
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
	
	private function autoInjectHeadersByHostname($hostname) {
		$ret = [];
		if(1==substr_count($hostname, self::DEFAULT_MESSAGES_HOST)) {
			$ret['ClientInfo'] = 'os=Windows; osVer=10; proc=Win64; lcid=en-us; deviceType=1; country=n/a; clientName='.self::CLIENTINFO_NAME.'; clientVer='.self::CLIENT_VERSION;
			$ret['Accept'] = 'application/json; ver=1.0';
		} else if(1==substr_count($hostname, self::CONTACTS_HOST) || 1==substr_count($hostname, self::NEW_CONTACTS_HOST) || 1==substr_count($hostname, self::VIDEOMAIL_HOST)) {
			$ret['X-Stratus-Caller'] = 'skype.com';
			$ret['X-Stratus-Request'] = substr(uniqid(), -8, 8);
			$ret['Accept'] = 'application/json; ver=1.0';
		} else if(1==substr_count($hostname, self::GRAPH_HOST)) {
			$ret['Accept'] = 'application/json';
		} else if(1==substr_count($hostname, self::DEFAULT_CONTACT_SUGGESTIONS_HOST)) {
			$ret['X-RecommenderServiceSettings'] = '{\"experiment\":\"default\",\"recommend\":\"true\"}';
			$ret['X-ECS-ETag'] = 'skype.com';
			$ret['X-Skype-Client'] = self::CLIENT_VERSION;
			$ret['Accept'] = 'application/json';
		} else {
			$ret['Accept'] = '*/*';
		}
		return $ret;
	}

    /**
     * Выполнить реквест по имени endpoint из статического массива
     *
     * @param string $endpointName
     * @param array $params
     * @return ResponseInterface
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
		$Response = $this->client->send($Request->getMethod(), $Request->getUri(), $Request->getParams());
		return $Response;
	}
    private function __request($endpointName, $params=[]) {
        if ($endpointName instanceof Endpoint){
            $Endpoint = $endpointName;
        } else {
            $Endpoint = static::$Endpoints[$endpointName];
        }

		$tmp = $this->autoInjectHeadersByHostname(parse_url($Endpoint->getUri(), PHP_URL_HOST));
		if(!isset($params['headers'])) {
			$params['headers'] = [];	
		}
		foreach($tmp as $k => $v) {
			if(!array_key_exists($k, $params['headers'])) {
				$params['headers'][$k] = $v;
			}
		}

        if (array_key_exists('format', $params)) {
            $format = $params['format'];
            unset($params['format']);
            $Endpoint = $Endpoint->format($format);
        }
        $Request = $Endpoint->getRequest([
            'skypeToken' => $this->skypeToken,
            'regToken'   => $this->regToken,
        ]);

        $res = $this->client->send($Request, $params);
        return $res;
    }

    /**
     * Выполнить реквест по имени endpoint из статического массива
     * и вернуть DOMDocument построенный на body ответа
     *
     * @param string $endpointName
     * @param array $params
     * @return DOMDocument
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
     * Выполнить реквест по имени endpoint из статического массива
     * и преобразовать JSON-ответ в array
     * @param string $endpointName
     * @param array $params
     * @return array
     */
    private function requestJSON($endpointName, $params=[]) {
        return json_decode($this->request($endpointName, $params)->getBody());
    }

    /**
     * Выполняем запрос для входа, ловим из ответа skypeToken
     * Проверяем не спросили ли у нас капчу и не возникло ли другой ошибки
     * Если всё плохо, то бросим исключение, иначе вернём true
     * @return bool
     * @throws Exception
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
	
	public function pingWebHost() {
		if(!empty($this->webSessionId)) {
			$Request = new Endpoint('POST', 'https://web.skype.com/api/v1/session-ping');
			$Request->needSkypeToken();
			$Response = $this->request($Request, ['debug' => false, 'form_params' => ['sessionId' => $this->webSessionId]]);
			return 200 == $Response->getStatusCode();
		}
		return false;
	}

		
	public function skypeTokenAuth() {
		$Response = $this->request('asm', [
				'debug' => false, 
				'form_params' => ['skypetoken' => $this->skypeToken],
				'headers' => ['X-Client-Version' => self::CLIENT_VERSION]
			]);
		return 204 == $Response->getStatusCode();
	}
	
	public function getPeToken() {
		$Request = new Endpoint('GET', 'https://static.asm.skype.com/pes/v1/petoken');
		$Result = $this->request($Request, ['debug' => false, 'headers' => ['Authorization' => 'skype_token '.$this->skypeToken]]);
		$tmp = json_decode($Result->getBody());
		return 200 == $Result->getStatusCode() && is_object($tmp) && isset($tmp->token) ? $tmp : null;
	}
	
    /**
     * Выход
     * @return bool
     */
    public function logout() {
		echo 'logging out', PHP_EOL;
		$this->request('logout');
        return true;
    }
	
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
	
	public function disableMessaging() {
		if($this->probeCurrentEndpoint(array('url' => $this->endpointUrl, 'key' => $this->regToken))) {
			$this->unsubscribeToResources();
			$this->deleteEndpoint();
		}
	}

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
	
	public function unsubscribeToResources() {
		if($this->endpointSubscriptionsUrl) {
			$Request = new Endpoint('DELETE', $this->endpointSubscriptionsUrl);
			$Request->needRegToken();
			$Response = $this->request($Request, ['debug' => false]);
			return 200 == $Response->getStatusCode();
		}
		return true;
	}

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

    public function setStatus($status)
    {
        $Request = new Endpoint('PUT', 'https://%sclient-s.gateway.messenger.live.com/v1/users/ME/presenceDocs/messagingService');
        $Request->needRegToken();

        $this->request($Request, [
			'format' => [$this->cloud ? $this->cloud : ''],
            'json' => [
                'status' => $status
            ]
        ]);
    }

	
    public function loadProfile()
    {
        $Request = new Endpoint('GET', 'https://api.skype.com/users/self/displayname');
        $Request->needSkypeToken();

        $Response = $this->requestJSON($Request);

        return isset($Response->username) ? $Response : null;
    }
	
	public function loadFullProfile() {
        $Request = new Endpoint('GET', 'https://api.skype.com/users/self/profile');
        $Request->needSkypeToken();

        $Response = $this->requestJSON($Request);

        return isset($Response->firstname) ? $Response : null;
	}
	
	public function updateProfile($data) {
		$Request = new Endpoint('POST', 'https://api.skype.com/users/self/profile/partial');
		$Request->needSkypeToken();
		$Result = $this->request($Request, [
            'json' => ['payload' => $data]
            ]);
        return 200 == $Result->getStatusCode();
	}
	
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
     * Скачиваем список всех контактов и информацию о них для залогиненного юзера
     * @param $username
     * @return null
     */
    public function loadContacts() {
        $Response = $this->requestJSON('contacts');
        return isset($Response->contacts) ? $Response->contacts : null;
    }

	public function loadGroups() {
		$Request = new Endpoint('GET', 'https://contacts.skype.com/contacts/v2/users/self/groups');
		$Request->needSkypeToken();
		$Response = $this->requestJSON($Request, []);
		return isset($Response->groups) ? $Response->groups : null;
	}
	
	public function initLoadContacts() {
		$Request = new Endpoint('GET', 'https://contacts.skype.com/contacts/v2/users/self');
		$Request->needSkypeToken();
		$Result = $this->request($Request, ['debug' => false]);
		$ret = json_decode($Result->getBody());
		return 200 == $Result->getStatusCode() && is_object($ret) && isset($ret->contacts) ? $ret : null;
	}
	
	public function sendContactRequest($mri, $greeting) {
		$Request = new Endpoint('POST', 'https://contacts.skype.com/contacts/v2/users/self/contacts');
		$Request->needSkypeToken();
		$Result = $this->request($Request, ['debug' => false, 'json' => ['mri' => $mri, 'greeting' => $greeting]]);
		return 200 == $Result->getStatusCode() ? true : false;
	}
	
	public function searchUserDirectory($searchstring) {
		$Request = new Endpoint('GET', 'https://skypegraph.skype.com/search/v1.1/namesearch/swx/?searchstring=%s&requestId=%s');
		$Request->needSkypeToken();
		$Response = $this->request($Request, ['debug' => false, 'format' => [rawurlencode($searchstring), substr(uniqid(), -6, 6)]]);
		$ret = json_decode($Response->getBody());
		return 200 == $Response->getStatusCode() && is_object($ret) && isset($ret->results) ? $ret->results : null;
	}
	
	public function getInvites() {
		$Request = new Endpoint('GET', 'https://contacts.skype.com/contacts/v2/users/self/invites');
		$Request->needSkypeToken();
		$Result = $this->requestJson($Request, ['debug' => false]);
		return $Result;
	}
	
	public function acceptOrDeclineInvite($mri, $action) {
		$Request = new Endpoint('PUT', 'https://contacts.skype.com/contacts/v2/users/self/invites/%s/%s');
		$Request->needSkypeToken();
		$Result = $this->request($Request, ['debug' => false, 'format' => [$mri, $action]]);
		return 200==$Result->getStatusCode() ? true : false;
	}
	
	public function deleteContact($mri) {
		$Request = new Endpoint('DELETE', 'https://contacts.skype.com/contacts/v2/users/self/contacts/%s');
		$Request->needSkypeToken();
		$Result = $this->request($Request, [
				'debug' => false,
				'format' => [$mri],
				'headers' => [
					'Authority' => 'contacts.skype.com',
					'Method' => 'DELETE',
					'Path' => '/contacts/v2/users/'.$this->username.'/contacts/'.$mri,
					'Scheme' => 'https',
					'Accept' => 'application/json; ver=1.0',
					'Accept-Encoding' => 'gzip, deflate',
					'Accept-Language' => 'fr-FR,fr;q=0.9,en-US;q=0.8,en;q=0.7',
					'Content-Type' => 'application/json',
					'Origin' => 'https://web.skype.com',
					'Referer' => 'https://web.skype.com/fr/',
					'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/66.0.3359.170 Safari/537.36 OPR/53.0.2907.99',
					'Content-Length' => 0,
					'X-Skype-Caller' => 'skype.com',
					'X-Skype-Request-Id' => '9f133492'
				]
			]
		);
		return 200==$Result->getStatusCode() ? true : false;
	}
	
	public function blockContact($mri) {
		$Request = new Endpoint('PUT', 'https://contacts.skype.com/contacts/v2/users/self/contacts/blocklist/%s');
		$Request->needSkypeToken();
		$Result = $this->request($Request, ['debug' => false, 'format' => [$mri], 'json' => ['ui_version' => self::CLIENTINFO_NAME, 'report_abuse' => false]]);
		return 200 == $Result->getStatusCode();
	}
	
	public function unblockContact($mri) {
		$Request = new Endpoint('DELETE', 'https://contacts.skype.com/contacts/v2/users/self/contacts/blocklist/%s');
		$Request->needSkypeToken();
		$Result = $this->request($Request, ['debug' => false, 'format' => [$mri]]);
		return 200 == $Result->getStatusCode();
	}
	
	public function getBlockList() {
		$Request = new Endpoint('GET', 'https://contacts.skype.com/contacts/v2/users/self/blocklist');
		$Request->needSkypeToken();
		$Result = $this->requestJSON($Request);
		return isset($Result->blocklist) ? $Result->blocklist : null;
	}
	
    /**
     * Скачиваем информацию о конкретном контакте, только если его нет в кеше
     * @param $username
     * @return array
     */
    public function loadContact($username) {
        $Request = new Endpoint('POST', 'https://api.skype.com/users/self/contacts/profiles');
        $Request->needSkypeToken();

        $Result = $this->requestJSON($Request, [
            'form_params' => [
                'contacts' => [$username]
            ]
        ]);
        return $Result;
    }
	
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
		
		if(true===$regTokenExpired || !$this->probeCurrentEndpoint($sessionData['regToken'])) {
			if(true == $regTokenExpired) {
				echo 'registration token has expired',PHP_EOL;
			} else {
				echo 'endpoint probing failed', PHP_EOL;
			}
			echo 'fetch a new registration token', PHP_EOL;
			$ts = time();;
			$Response = $this->request('endpoint', [
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
	
	public function probeCurrentEndpoint(array $sessionData) {
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
	
	public function endpointSetSupportMessageProperties() {
		$Request = new Endpoint('PUT', 'https://%sclient-s.gateway.messenger.live.com/v1/users/ME/endpoints/SELF/properties?name=supportsMessageProperties');
		$Request->needRegToken();
		$Response = $this->request($Request, ['debug' => false, 'format' => [$this->cloud ? $this->cloud : ''], 'json' => ['supportsMessageProperties' => true]]);
		return 200 == $Response->getStatusCode();
	}
	
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
	
	public function messagingGetMyProperties() {
		$Request = new Endpoint('GET', 'https://%sclient-s.gateway.messenger.live.com/v1/users/ME/properties');
		$Request->needRegToken();
		$Response = $this->request($Request, ['debug' => false, 'format' => [$this->cloud ? $this->cloud : '']]);
		$ret = json_decode($Response->getBody());
		return 200 == $Response->getStatusCode() && is_object($ret) && isset($ret->lastActivityAt) ? $ret : null;
	}
	
	public function messagingGetMyPresenceDocs() {
		$Request = new Endpoint('GET', 'https://%sclient-s.gateway.messenger.live.com/v1/users/ME/presenceDocs/messagingService?view=expanded');
		$Request->needRegToken();
		$Response = $this->request($Request, ['debug' => false, 'format' => [$this->cloud ? $this->cloud : '']]);
		$ret = json_decode($Response->getBody());
		return 200 == $Response->getStatusCode() && is_object($ret) && isset($ret->type) ? $ret : null;
	}

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
			if(0 == $Response->getStatusCode()) {
				sleep(2);
				$tmp = $this->loadConversations();
				if(is_array($tmp)) {
					return $tmp;
				} else {
					return false;
				}
			}
			echo $Response->getStatusCode(), ' ', $Response->getBody(), PHP_EOL; 
			return null;
		}
	}
	
	public function deleteConversation($mri) {
        $Request = new Endpoint('DELETE', 'https://%sclient-s.gateway.messenger.live.com/v1/users/ME/conversations/%s/messages');
        $Request->needRegToken();
        $Response = $this->request($Request, [
			'debug' => false,
            'format' => [$this->cloud ? $this->cloud : '', $mri]
        ]);
		return 200 == $Response->getStatusCode() ? true : false;
	}
	
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
	
    public function getNewMessages(){
        $Request = new Endpoint('POST', 'https://%sclient-s.gateway.messenger.live.com/v1/users/ME/endpoints/SELF/subscriptions/0/poll');
        $Request->needRegToken();
        $Response = $this->requestJSON($Request, [
            'format' => [$this->cloud ? $this->cloud : '']
        ]);
        return isset($Response->eventMessages) ? $Response->eventMessages : null;
    }
	
	public function threadInfos($id) {
        $Request = new Endpoint('GET', 'https://%sclient-s.gateway.messenger.live.com/v1/threads/%s?view=msnp24Equivalent');
        $Request->needRegToken();
        $Response = $this->requestJson($Request, [
            'format' => [$this->cloud ? $this->cloud : '', $id]
        ]);
		return isset($Response->id) ? $Response : null;
	}
	
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
	
	public function addOrEditThreadMember($id, $addId, $role) {
        $Request = new Endpoint('PUT', 'https://%sclient-s.gateway.messenger.live.com/v1/threads/%s/members/%s');
        $Request->needRegToken();
        $Response = $this->request($Request, [
            'format' => [$this->cloud ? $this->cloud : '', $id, $addId],
			'json' => ['role' => $role]
        ]);
		return 200 == $Response->getStatusCode();
	}
	
	public function removeThreadMember($id, $rmId) {
        $Request = new Endpoint('DELETE', 'https://%sclient-s.gateway.messenger.live.com/v1/threads/%s/members/%s');
        $Request->needRegToken();
        $Response = $this->request($Request, [
            'format' => [$this->cloud ? $this->cloud : '', $id, $rmId]
        ]);
		return 200 == $Response->getStatusCode();
	}
	
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

	
	private static function int32ToHexString($n){
		$hexChars = '0123456789abcdef';
		$hexString = '';
		for($i=0; $i<4; $i++) {
			$hexString .= $hexChars[($n >> ($i * 8 + 4)) & 15];
			$hexString .= $hexChars[($n >> ($i * 8)) & 15];
		}
		return $hexString;
	}
	
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