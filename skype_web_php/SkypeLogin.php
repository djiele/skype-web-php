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

require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'json.php';

/**
 * Microsoft oauth authentication for Skype
 *
 * <code>
 * $tokenData = SkypeLogin::getSkypeToken('joe.bloggs', 'password', getcwd().DIRECTORY_SEPARATOR.'app-data'.DIRECTORY_SEPARATOR);
 * </code>
 */
class SkypeLogin
{

    /**
     * @brief LoginUrl
     */
    static protected $loginUrl = 'https://login.skype.com/login?client_id=578134&redirect_uri=https%3A%2F%2Fweb.skype.com';

    /**
     * @brief extract the JSON object ServerData from a response body
     *
     * @param [in] $string Description for $string
     * @return stdClass
     */
    static public function parseServerData($string)
    {
        $srvDataStart = strpos($string, '<script type="text/javascript">var ServerData');
        $srvData = substr($string, $srvDataStart);
        $srvData = substr($srvData, strpos($srvData, '{'));
        $srvDataEnd = strpos($srvData, ';</script>');
        $srvData = substr($srvData, 0, $srvDataEnd);
        //file_put_contents('ServerData.json', $srvData);
        $json = new \Services_JSON();
        $srvData = $json->decode($srvData);
        return $srvData;
    }

    /**
     * @brief load the Skype token from a session or fetch a new one if expired
     *
     * @param string $login user login
     * @param string $passwd user password
     * @param string $dataPath local path where to find session file
     * @param int $expiresTreshold expiry treshold
     * @return array Skype token and expires value
     */
    static public function getSkypeToken($login, $passwd, $dataPath, $client, $expiresTreshold = 3600)
    {
        $sessionData = json_decode(file_get_contents($dataPath . $login . '-session.json'), true);
        $sessionData['skypeToken']['expires_in'] = (int)$sessionData['skypeToken']['expires_in'];
        if ($sessionData['skypeToken']['expires_in'] < time()) {
            $skypeToken = self::fetchSkypeToken($login, $passwd, $dataPath, $client);
            $sessionData['skypeToken']['skypetoken'] = $skypeToken['skypetoken'];
            $sessionData['skypeToken']['expires_in'] = (int)$skypeToken['expires_in'];
            $sessionData['skypeToken']['expires_in'] += (time() - $expiresTreshold);
            if (!file_put_contents($dataPath . $login . '-session.json', json_encode($sessionData, JSON_PRETTY_PRINT))) {
                echo 'session file write error [', $dataPath . $login . '-session.json]', PHP_EOL;
            }
        }
        return $sessionData['skypeToken'];
    }

    /**
     * @brief process oauth login
     *
     * @param string $login user login
     * @param string $passwd user password
     * @param [in] $dataPath local path where to find data and cURL cookie file directory
     * @return mixed array Skype token and expires value or null if error
     */
    static public function fetchSkypeToken($login, $passwd, $dataPath, $client)
    {
        $skypeToken = null;
        $skypeTokenExpires = null;
        $tmp = $client->send('GET', self::$loginUrl, ['debug' => false]);
        $response = $tmp->getBody();
        //file_put_contents('__response-001.html', $response);
        
        $srvData = self::parseServerData($response);
        if (is_object($srvData)) {
            $urlPost = $srvData->urlPost;
            $ppft = $srvData->sFTTag;
            $ppft = substr($ppft, strpos($ppft, 'value="') + 7);
            $ppft = substr($ppft, 0, strpos($ppft, '"'));
            $ppftKey = $srvData->sFTTag;
            $ppftKey = substr($ppftKey, strpos($ppftKey, 'name="') + 6);
            $ppftKey = substr($ppftKey, 0, strpos($ppftKey, '"'));
            $postData = array('login' => $login, 'loginfmt' => $login, 'passwd' => $passwd, $ppftKey => $ppft);
            if (isset($srvData->CU) && !empty($srvData->CU)) {
                $postData['PPSX'] = $srvData->CU;
            }
        } else {
            $postData = null;
        }
        //print_r($srvData);
        $GetCredentialTypeQuerytokens = [];
        parse_str(parse_url($srvData->Bw, PHP_URL_QUERY),$GetCredentialTypeQuerytokens);
        $jsonParams = [
            'checkPhones' => false,
            'federationFlags' => 3,
            'flowToken' => $postData[$ppftKey],
            'forceotclogin' => false,
            'isCookieBannerShown' => false,
            'isExternalFederationDisallowed' => false,
            'isFidoSupported' => true,
            'isOtherIdpSupported' => false,
            'isRemoteConnectSupported' => false,
            'isRemoteNGCSupported' => true,
            'otclogindisallowed' => true,
            'uaid' => $GetCredentialTypeQuerytokens['uaid'],
            'username' => $postData['login'],
        ];
        $tmp = $client->send('POST', $srvData->Bw, ['debug' => false, 'json' => $jsonParams]);
        $GetCredentialTypeInfos = json_decode($tmp->getBody());

        $leftover = $response;
        $buff = '';
        while (is_numeric($posForm = stripos($leftover, '<form '))) {
            $posFormEnd = stripos($leftover, '>', $posForm +1);
            $buff .= substr($leftover, $posForm, $posFormEnd - $posForm +1) . PHP_EOL;
            $leftover = substr($leftover, $posFormEnd +1);
            $formClosed = false;
            while(is_numeric($pos = stripos($leftover, '<input '))) {
                if(is_numeric($posFormClose = stripos($leftover, '</form>')) && $posFormClose < $pos) {
                    $posFormCloseEnd = stripos($leftover, '>', $posFormClose +1);
                    $buff .= substr($leftover, $posFormClose, $posFormCloseEnd - $posFormClose +1) . PHP_EOL;
                    $leftover = substr($leftover, $posFormCloseEnd +1);
                    $formClosed = true;
                    break;
                }
                $posEnd = strpos($leftover, '>', $pos +1);
                $buff .= substr($leftover, $pos, $posEnd - $pos +1) . PHP_EOL;
                $leftover = substr($leftover, $posEnd +1);
            }
        }
        if (0 < strlen($leftover)) {
            if(is_numeric($posFormClose = stripos($leftover, '</form>'))) {
                $posFormCloseEnd = stripos($leftover, '>', $posFormClose +1);
                $buff .= substr($leftover, $posFormClose, $posFormCloseEnd - $posFormClose +1) . PHP_EOL;
                $leftover = substr($leftover, $posFormCloseEnd +1);
                $formClosed = true;
            }
        }
        unset($leftover);
        $response = '<html><head></head><body>' . $buff . '</body></html>';
        
        $doc = new \DOMDocument();
        $doc->$preserveWhiteSpace = true;
        $doc->$formatOutput = true;
        @$doc->loadHTML($response, LIBXML_NOWARNING | LIBXML_NOERROR);

        $forms = $doc->getElementsByTagName('form');
        if (0 < $forms->length) {
            for ($i = 0; $i < $forms->length; $i++) {
                if ($forms[$i]->hasAttribute('name') && 'f1' == $forms[$i]->getAttribute('name')) {
                    $urlPost = $forms[$i]->getAttribute('action');
                    $postData = array();
                    foreach ($forms[$i]->childNodes as $input) {
                        if ('input' == $input->nodeName) {
                            $attributeName = $input->getAttribute('name');
                            if (empty($attributeName)) {
                                continue;
                            }
                            if ('loginfmt' == $attributeName) {
                                $fieldValue = $login;
                                $postData['login'] = $fieldValue;
                            } else if('passwd' == $attributeName) {
                                $fieldValue = $passwd;
                            } else {
                                $fieldValue = $input->getAttribute('value');
                            }
                            $postData[$attributeName] = $fieldValue;
                        }
                    }
                }
            }
        }

        //file_put_contents('__response-001b.html', $doc->saveHTML(), LOCK_EX);
        //file_put_contents('__PPFT.php', '<?php $PPFT = '.var_export($postData, true));
        $doc = null;

        $tmp = $client->send('POST', $urlPost, ['debug' => false, 'form_params' => $postData]);
        $response2 = $tmp->getBody();
        //file_put_contents('__response-002.html', $response2);

        $doc = new \DOMDocument();
        @$doc->loadHTML($response2, LIBXML_NOWARNING | LIBXML_NOERROR);
        $forms = $doc->getElementsByTagName('form');
        if (0 == $forms->length) {
            $srvData = self::parseServerData($response2);
            if (!empty($srvData->sErrTxt)) {
                echo $srvData->sErrTxt, PHP_EOL;
            } else {
                echo 'undefined login error', PHP_EOL;
            }
            return null;
        }
        $urlPost = $forms[0]->getAttribute('action');
        $postData = array();
        foreach ($forms[0]->childNodes as $input) {
            if ('input' == $input->nodeName) {
                $postData[$input->getAttribute('name')] = $input->getAttribute('value');
            }
        }
        $doc = null;

        $tmp = $client->send('POST', $urlPost, ['debug' => false, 'form_params' => $postData]);
        $response3 = $tmp->getBody();
        //file_put_contents('__response-003.html', $response3);

        $doc = new \DOMDocument();
        @$doc->loadHTML($response3, LIBXML_NOWARNING | LIBXML_NOERROR);
        $forms = $doc->getElementsByTagName('form');
        $urlPost = $forms[0]->getAttribute('action');
        $postData = array();
        foreach ($forms[0]->childNodes as $input) {
            if ('input' == $input->nodeName) {
                if ($input->hasAttribute('value')) {
                    if ('skypetoken' == $input->getAttribute('name')) {
                        $skypeToken = $input->getAttribute('value');
                    } else if ('expires_in' == $input->getAttribute('name')) {
                        $skypeTokenExpires = $input->getAttribute('value');
                    }
                    $postData[$input->getAttribute('name')] = $input->getAttribute('value');
                } else {
                    $postData[$input->getAttribute('name')] = '';
                }
            }
        }
        $doc = null;

        if (!$skypeToken) {
            $tmp = $client->send('POST', $urlPost, ['debug' => false, 'form_params' => $postData]);
            $response4 = $tmp->getBody();
            //file_put_contents('__response-004.html', $response4);

            $doc = new \DOMDocument();
            @$doc->loadHTML($response4, LIBXML_NOWARNING | LIBXML_NOERROR);
            $forms = $doc->getElementsByTagName('form');
            $urlPost = $forms[0]->getAttribute('action');
            $postData = array();
            foreach ($forms[0]->childNodes as $input) {
                if ('input' == $input->nodeName) {
                    if ($input->hasAttribute('value')) {
                        if ('skypetoken' == $input->getAttribute('name')) {
                            $skypeToken = $input->getAttribute('value');
                        } else if ('expires_in' == $input->getAttribute('name')) {
                            $skypeTokenExpires = $input->getAttribute('value');
                        }
                        $postData[$input->getAttribute('name')] = $input->getAttribute('value');
                    } else {
                        $postData[$input->getAttribute('name')] = '';
                    }
                }
            }
        }
        if ($skypeToken) {
            $tmp = $client->send('POST', $urlPost, ['debug' => false, 'form_params' => $postData]);
            $response5 = $tmp->getBody();
            //file_put_contents('__response-005.html', $response5);
        }
        return empty($skypeToken) ? null : array('skypetoken' => $skypeToken, 'expires_in' => $skypeTokenExpires);
    }
}
