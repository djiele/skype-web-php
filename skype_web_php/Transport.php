<?php

namespace skype_web_php;

use Exception;
use DOMDocument;
use DOMXPath;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Promise;
use Psr\Http\Message\ResponseInterface;

class Transport {

    /**
     * @var Client
     */
	private $dataPath;
    private $client;
    private $skypeToken;
	private $skypeTokenExpires;
    private $regToken;
	private $regTokenExpires;
	private $endpointUrl;
	private $endpointId;
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
                'https://contacts.skype.com/contacts/v2/users/self/contacts'))
                ->needSkypeToken(),

            'send_message' => (new Endpoint('POST',
                'https://%sclient-s.gateway.messenger.live.com/v1/users/ME/conversations/%s/messages'))
                ->needRegToken(),

            'logout'  => (new Endpoint('Get',
                'https://login.skype.com/logout?client_id=578134&redirect_uri=https%3A%2F%2Fweb.skype.com&intsrc=client-_-webapp-_-production-_-go-signin')),
        ];
    }

    public function __construct() {
        static::init();

        $Stack = new HandlerStack();
        $Stack->setHandler(new CurlHandler());

        /**
         * Здесь ставим ловушку, чтобы с помощью редиректов
         *   определить адрес сервера, который сможет отсылать сообщения
         */
        $Stack->push(Middleware::mapResponse(function (ResponseInterface $Response) {
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
        }));


        //$cookieJar = new FileCookieJar('cookie.txt', true);

        $this->client = new Client([
            'handler' => $Stack,
            'cookies' => true
        ]);

    }

    /**
     * Выполнить реквест по имени endpoint из статического массива
     *
     * @param string $endpointName
     * @param array $params
     * @return ResponseInterface
     */
    private function request($endpointName, $params=[]) {
        if ($endpointName instanceof Endpoint){
            $Endpoint = $endpointName;
        }else{
            $Endpoint = static::$Endpoints[$endpointName];
        }

        if (array_key_exists("format", $params)) {
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
     * @param $username
     * @param $password
     * @param null $captchaData
     * @return bool
     * @throws Exception
     */
    public function login($username, $dataPath) {
		$this->dataPath = $dataPath;
		$sessionData = json_decode(file_get_contents($this->dataPath.$username.'-session.json'), true);
		$sessionData['@dataPath'] = $this->dataPath; 
		$tmp = SkypeSession::getOrSetSkypeToken($username, $sessionData);
		$this->skypeToken = $tmp['skypetoken'];
		$this->skypeTokenExpires = (int)$tmp['expires_in'];
		$sessionData['skypeToken']['skypetoken'] = $this->skypeToken;
		$sessionData['skypeToken']['expires_in'] = $this->skypeTokenExpires;
		
		$this->request('asm', [
			'form_params' => [
				'skypetoken' => $this->skypeToken,
			],
		]);
		
		$tmp = SkypeSession::loadRegistrationToken($username, $sessionData);
		if(empty($tmp['key']) || (int)$tmp['expires']<time()) {
			echo 'fetch a new registration token', PHP_EOL;
			$Response = $this->request('endpoint', [
				'headers' => ['Authentication' => "skypetoken=$this->skypeToken"],
				'json' => ['skypetoken' => $this->skypeToken]
			]);
			$tmp = SkypeSession::getOrSetTokenFromResponse($username, $sessionData, $Response);
			$this->endpointUrl = $tmp['url'];
			$this->endpointId = $tmp['endpointId'];
			$this->regToken = $tmp['key'];
			$this->regTokenExpires = $tmp['expires'];
			$this->cloud = $tmp['cloudPrefix'];
		} else {
			$this->endpointUrl = $sessionData['regToken']['url'];
			$this->endpointId = $sessionData['regToken']['endpointId'];
			$this->regToken = $sessionData['regToken']['key'];
			$this->regTokenExpires = $sessionData['regToken']['expires'];
			$this->cloud = $sessionData['regToken']['cloudPrefix'];
		}
		return true;
    }

    /**
     * Выход
     * @return bool
     */
    public function logout() {
        $this->request('logout');
        return true;
    }

    public function send($username, $text, $edit_id = false) {
        $milliseconds = round(microtime(true) * 1000);

        $message_json = [
            'content' => $text,
            'messagetype' => 'RichText',
            'contenttype' => 'text',
            'clientmessageid' => "$milliseconds",
        ];

        if ($edit_id){
            $message_json['skypeeditedid'] = $edit_id;
            unset($message_json['clientmessageid']);
        }

        $Response = $this->requestJSON('send_message', [
            'json' => $message_json,
            'format' => [$this->cloud, "8:$username"]
        ]);

        if (array_key_exists("OriginalArrivalTime", $Response)){//if successful sended
            return $milliseconds;//message ID
        }else{
            return false;
        }
    }

    /**
     * Скачиваем список всех контактов и информацию о них для залогиненного юзера
     * @param $username
     * @return null
     */
    public function loadContacts() {
        $response = $this->requestJSON('contacts');
        return isset($response->contacts) ? $response->contacts : null;
    }

    public function loadProfile()
    {
        $request = new Endpoint('GET', 'https://api.skype.com/users/self/displayname');
        $request->needSkypeToken();

        $response = $this->requestJSON($request);

        return isset($response->username) ? $response : null;
    }
	
	public function loadFullProfile() {
        $request = new Endpoint('GET', 'https://api.skype.com/users/self/profile');
        $request->needSkypeToken();

        $response = $this->requestJSON($request);

        return isset($response->firstname) ? $response : null;
	}
	
	public function updateProfile($data) {
		$request = new Endpoint('POST', 'https://api.skype.com/users/self/profile/partial');
		$request->needSkypeToken();
		$Result = $this->request($request, [
            'json' => ["payload" => $data]
            ]);
        return $Result;
	}

	
	public function updateAvatar($loginName,  $filename) {
		$request = new Endpoint('PUT', 'https://avatar.skype.com/v1/avatars/%s');
		$request->needSkypeToken();
		$filePointer = fopen($filename, 'rb');
		$Result = $this->request($request, [
			'debug' => false,
			'format' => [$loginName],
			'headers' => [
				'Content-Length' => filesize($filename),
				'Accept' => 'application/json, text/javascript',
				'Accept-Encoding' => 'gzip, deflat',
				'Content-Type' => mime_content_type($filename)
			],
            'body' => $filePointer
            ]);
        return $Result;
	}
	
	public function getInvites() {
		$request = new Endpoint('GET', 'https://contacts.skype.com/contacts/v2/users/self/invites');
		$request->needSkypeToken();
		$Result = $this->requestJson($request, ['debug' => false]);
		return $Result;
	}
	
	public function blockContact($mri) {
		$request = new Endpoint('PUT', 'https://contacts.skype.com/contacts/v2/users/self/contacts/blocklist/%s');
		$request->needSkypeToken();
		$Result = $this->request($request, ['debug' => false, 'format' => [$mri], 'json' => ['ui_version' => 'skype.com', 'report_abuse' => false]]);
		return $Result;
	}
	
	public function unblockContact($mri) {
		$request = new Endpoint('DELETE', 'https://contacts.skype.com/contacts/v2/users/self/contacts/blocklist/%s');
		$request->needSkypeToken();
		$Result = $this->request($request, ['debug' => false, 'format' => [$mri]]);
		return $Result;
	}
	
	public function getBlockList() {
		$request = new Endpoint('GET', 'https://contacts.skype.com/contacts/v2/users/self/blocklist');
		$request->needSkypeToken();
		$Result = $this->requestJSON($request);
		return $Result;
	}
	
    /**
     * Скачиваем информацию о конкретном контакте, только если его нет в кеше
     * @param $username
     * @return array
     */
    public function loadContact($username) {
        $request = new Endpoint('POST', 'https://api.skype.com/users/self/contacts/profiles');
        $request->needSkypeToken();

        $Result = $this->requestJSON($request, [
            'form_params' => [
                'contacts' => [$username]
            ]
        ]);
        return $Result;
    }


    public function getNewMessages($username){
        $request = new Endpoint('POST', 'https://%sclient-s.gateway.messenger.live.com/v1/users/ME/endpoints/SELF/subscriptions/0/poll');
        $request->needRegToken();

        $response = $this->requestJSON($request, [
            'format' => [$this->cloud, "8:$username"]
        ]);

        return isset($response->eventMessages) ? $response->eventMessages : null;
    }

    public function subscribeToResources()
    {
        $request = new Endpoint('POST', 'https://%sclient-s.gateway.messenger.live.com/v1/users/ME/endpoints/SELF/subscriptions');
        $request->needRegToken();

        return $this->requestJSON($request, [
			'format' => [$this->cloud?$this->cloud:''],
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
    }

    public function createStatusEndpoint()
    {
        $request = new Endpoint('PUT', 'https://client-s.gateway.messenger.live.com/v1/users/ME/endpoints/SELF/presenceDocs/messagingService');
        $request->needRegToken();

        $this->request($request, [
			'debug' => false,
            'json' => [
                'id' => 'messagingService',
                'type' => 'EndpointPresenceDoc',
                'selfLink' => 'uri',
                'privateInfo' =>  ["epname" => "skype"],
                'publicInfo' =>  [
                    "capabilities" => "video|audio",
                    "type" => 1,
                    "skypeNameVersion" => 'skype.com',
                    "nodeInfo" => 'xx',
                    "version" => '908/1.30.0.128//skype.com',
                ],
            ]
        ]);
    }

    public function setStatus($status)
    {
        $request = new Endpoint('PUT', 'https://%sclient-s.gateway.messenger.live.com/v1/users/ME/presenceDocs/messagingService');
        $request->needRegToken();

        $this->request($request, [
			'format' => [$this->cloud?$this->cloud : ''],
            'json' => [
                'status' => $status
            ]
        ]);
    }
}