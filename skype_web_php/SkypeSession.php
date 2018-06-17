<?php
namespace skype_web_php;
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'json.php';
use \Curl\Curl;

class SkypeSession {

	static public function parseServerData($string) {
		$srvDataStart = strpos($string, 'ServerData');
		$srvData = substr($string, $srvDataStart);
		$srvData = substr($srvData, strpos($srvData, '{'));
		$srvDataEnd = strpos($srvData, ';</script>');
		$srvData = substr($srvData, 0, $srvDataEnd);
		$json = new \Services_JSON();
		$srvData = $json->decode($srvData);
		return $srvData;
	}
	
	static public function getOrSetSkypeToken($login, $sessionData, $expiresTreshold=3600) {
		$dataPath = $sessionData['@dataPath'];
		$sessionData['skypeToken']['expires_in'] = (int)$sessionData['skypeToken']['expires_in'];
		if($sessionData['skypeToken']['expires_in']<time()) {
			$skypeToken = self::fetchSkypeToken($login, $sessionData['passwd'], $dataPath);
			$sessionData['skypeToken']['skypetoken'] = $skypeToken['skypetoken'];
			$sessionData['skypeToken']['expires_in'] = (int)$skypeToken['expires_in'];
			$sessionData['skypeToken']['expires_in'] += (time()-$expiresTreshold);
			unset($sessionData['@dataPath']);
			if(!file_put_contents($dataPath.$login.'-session.json', json_encode($sessionData, JSON_PRETTY_PRINT))) {
				echo 'session file write error' . PHP_EOL;
			}
		}
		return $sessionData['skypeToken'];
	}
	
	static public function loadRegistrationToken($login, $sessionData, $expiresTreshold=60) {
		$dataPath = $sessionData['@dataPath'];
		$sessionData['regToken']['expires'] = (int)$sessionData['regToken']['expires'];
		if(empty($sessionData['regToken']['key']) || $sessionData['regToken']['expires']<time()) {
			$sessionData['regToken']['url'] = "";
			$sessionData['regToken']['key'] = "";
			$sessionData['regToken']['expires'] = 0;
			$sessionData['regToken']['endpointId'] = "";
			$sessionData['regToken']['cloudPrefix'] = "";
		}
		return $sessionData['regToken'];
	}
	
	static public function getOrSetTokenFromResponse($username, array $sessionData, $response, $expiresTreshold=600) {
		if(201==$response->getStatusCode()) {
			$dataPath = $sessionData['@dataPath'];
			$header = $response->getHeader("Set-RegistrationToken");
			if (count($header) > 0) {
				$parts = explode(';', $header[0]);
				$sessionData['regToken']['key'] = trim($parts[0]);
				$sessionData['regToken']['expires'] = trim($parts[1]);
				$sessionData['regToken']['expires'] = (int)substr($sessionData['regToken']['expires'], strpos($sessionData['regToken']['expires'], '=')+1)-$expiresTreshold;
				$sessionData['regToken']['endpointId'] = trim($parts[2]);
				$sessionData['regToken']['endpointId'] = substr($sessionData['regToken']['endpointId'], strpos($sessionData['regToken']['endpointId'], '=')+1);
				$header = $response->getHeader("Location");
				if (count($header) > 0) {
					$sessionData['regToken']['url'] = $header[0];
					$matches = array();
					preg_match('#https?://([^-]*-)client\-s#', $sessionData['regToken']['url'], $matches);
					if (array_key_exists(1, $matches)) {
						$sessionData['regToken']['cloudPrefix'] = $matches[1];
					}
				}
				unset($sessionData['@dataPath']);
				if(!file_put_contents($dataPath.$username.'-session.json', json_encode($sessionData, JSON_PRETTY_PRINT))) {
					echo 'session file write error' . PHP_EOL;
				}
			}
		}
		return $sessionData['regToken'];
		
	}

	static public function fetchSkypeToken($login, $passwd, $dataPath) {
		$skypeToken = null;
		$skypeTokenExpires = null;
		$curl = new Curl();
		$curl->setOpt(CURLOPT_FOLLOWLOCATION, true);
		$curl->setCookieFile($dataPath.'curl/cookie-file.txt');
		$curl->setCookieJar($dataPath.'curl/cookie.jar');

		$curl->get('https://login.skype.com/login?client_id=578134&redirect_uri=https%3A%2F%2Fweb.skype.com');
		$response = $curl->response;
		$srvData = self::parseServerData($response);
		$urlPost = $srvData->urlPost;
		$ppft = $srvData->sFTTag;
		$ppft = substr($ppft, strpos($ppft,'value="')+7);
		$ppft = substr($ppft, 0,strpos($ppft, '"'));
		$ppsx = @$srvData->bF ? $srvData->bF : null;
		$postData = array('login' => $login, 'passwd' => $passwd, 'PPFT' => $ppft);
		if(isset($srvData->bF) && !empty($srvData->bF)) {
			$postData['PPSX'] = $srvData->bF;
		}
		$curl->post($urlPost, $postData);

		$response2 = $curl->response;
		$doc =  new \DOMDocument();
		@$doc->loadHTML($response2, LIBXML_NOWARNING | LIBXML_NOERROR);
		$forms = $doc->getElementsByTagName('form');
		$urlPost = $forms[0]->getAttribute('action');
		$postData = array();
		foreach($forms[0]->childNodes as $input) {
			if('input' == $input->nodeName) {
				$postData[$input->getAttribute('name')] = $input->getAttribute('value');
			}
		}
		$doc = null;
		$curl->post($urlPost, $postData);

		$response3 = $curl->response;
		$doc =  new \DOMDocument();
		@$doc->loadHTML($response3, LIBXML_NOWARNING | LIBXML_NOERROR);
		$forms = $doc->getElementsByTagName('form');
		$urlPost = $forms[0]->getAttribute('action');
		$postData = array();
		foreach($forms[0]->childNodes as $input) {
			if('input' == $input->nodeName) {
				if($input->hasAttribute('value')){
					$postData[$input->getAttribute('name')] = $input->getAttribute('value');
				} else {
					$postData[$input->getAttribute('name')] = '';
				}
			}
		}
		$doc = null;
		$curl->post($urlPost, $postData);

		$response4 = $curl->response;
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
		return array('skypetoken' => $skypeToken, 'expires_in' => $skypeTokenExpires);
	}
}