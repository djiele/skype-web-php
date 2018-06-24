<?php
namespace skype_web_php;
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'json.php';
use \Curl\Curl;

class SkypeLogin {

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
	
	static public function getSkypeToken($login, $dataPath, $expiresTreshold=3600) {
		$sessionData = json_decode(file_get_contents($dataPath.$login.'-session.json'), true);
		$sessionData['skypeToken']['expires_in'] = (int)$sessionData['skypeToken']['expires_in'];
		if($sessionData['skypeToken']['expires_in']<time()) {
			$skypeToken = self::fetchSkypeToken($login, $sessionData['passwd'], $dataPath);
			$sessionData['skypeToken']['skypetoken'] = $skypeToken['skypetoken'];
			$sessionData['skypeToken']['expires_in'] = (int)$skypeToken['expires_in'];
			$sessionData['skypeToken']['expires_in'] += (time()-$expiresTreshold);
			if(!file_put_contents($dataPath.$login.'-session.json', json_encode($sessionData, JSON_PRETTY_PRINT))) {
				echo 'session file write error [', $dataPath.$login.'-session.json]', PHP_EOL;
			}
		}
		return $sessionData['skypeToken'];
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
		return empty($skypeToken) ? null : array('skypetoken' => $skypeToken, 'expires_in' => $skypeTokenExpires);
	}
}