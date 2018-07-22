<?php
/**
 * High Level Skype web API
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
 * @file Skype.php
 * @brief high Level Skype web API
 * @license https://opensource.org/licenses/MIT
 */
namespace skype_web_php;

/**
 * Skype web public API
 *
 * Brief example of use:
 *
 * <code>
 * // create a new instance of Skype
 * $skype = new Skype($username, $passwd, getcwd().DIRECTORY_SEPARATOR.'app-data'.DIRECTORY_SEPARATOR);
 * $skype->login() or die('Login failed');
 * // messaging environment
 * $skype->enableMessaging(Skype::STATUS_ONLINE);
 * // send a text message
 * $message_id = $skype->sendMessage("Hello: ".date('Y-m-d H:i:s'), $contact_id);
 * $skype->disableMessaging();
 * $skype->logout();
 * </code>
 */
class Skype
{

	/** 
	 * @brief const STATUS_ONLINE
	 */
    const STATUS_ONLINE = 'Online';
	/** 
	 * @brief const STATUS_HIDDEN
	 */
    const STATUS_HIDDEN = 'Hidden';
	/** 
	 * @brief const STATUS_BUSY
	 */
    const STATUS_BUSY = 'Busy';

    /**
     * @brief stdClass user profile
     */
    public $profile;
    /**
     * @brief array list of contacts
     */
    public $contacts;
    /**
     * @brief array list of groups
     */
    public $groups;
    /**
     * @brief array list of blocked users
     */
    public $blocklist;
    /**
     * @brief array list of conversations
     */
    public $conversations;
    /**
     * @brief array list of threads
     */
    public $threads;
	/**
     * @brief Transport API calls
     */
    private $transport;
	/**
     * @brief string username
     */
    private $username;
	/**
     * @brief string path to sessions directory
     */
    private $datapath;
	/**
     * @brief array ISO ALPHA-2 countries code
     */
	private $isoAlpha2 = ['AF', 'AX', 'AL', 'DZ', 'AS', 'AD', 'AO', 'AI', 'AQ', 'AG', 'AR', 'AM', 'AW', 'AU', 'AT', 'AZ', 'BS', 'BH', 'BD', 'BB', 'BY', 'BE', 'BZ', 'BJ', 'BM', 'BT', 'BO', 'BA', 'BW', 'BV', 'BR', 'VG', 'IO', 'BN', 'BG', 'BF', 'BI', 'KH', 'CM', 'CA', 'CV', 'KY', 'CF', 'TD', 'CL', 'CN', 'HK', 'MO', 'CX', 'CC', 'CO', 'KM', 'CG', 'CD', 'CK', 'CR', 'CI', 'HR', 'CU', 'CY', 'CZ', 'DK', 'DJ', 'DM', 'DO', 'EC', 'EG', 'SV', 'GQ', 'ER', 'EE', 'ET', 'FK', 'FO', 'FJ', 'FI', 'FR', 'GF', 'PF', 'TF', 'GA', 'GM', 'GE', 'DE', 'GH', 'GI', 'GR', 'GL', 'GD', 'GP', 'GU', 'GT', 'GG', 'GN', 'GW', 'GY', 'HT', 'HM', 'VA', 'HN', 'HU', 'IS', 'IN', 'ID', 'IR', 'IQ', 'IE', 'IM', 'IL', 'IT', 'JM', 'JP', 'JE', 'JO', 'KZ', 'KE', 'KI', 'KP', 'KR', 'KW', 'KG', 'LA', 'LV', 'LB', 'LS', 'LR', 'LY', 'LI', 'LT', 'LU', 'MK', 'MG', 'MW', 'MY', 'MV', 'ML', 'MT', 'MH', 'MQ', 'MR', 'MU', 'YT', 'MX', 'FM', 'MD', 'MC', 'MN', 'ME', 'MS', 'MA', 'MZ', 'MM', 'NA', 'NR', 'NP', 'NL', 'AN', 'NC', 'NZ', 'NI', 'NE', 'NG', 'NU', 'NF', 'MP', 'NO', 'OM', 'PK', 'PW', 'PS', 'PA', 'PG', 'PY', 'PE', 'PH', 'PN', 'PL', 'PT', 'PR', 'QA', 'RE', 'RO', 'RU', 'RW', 'BL', 'SH', 'KN', 'LC', 'MF', 'PM', 'VC', 'WS', 'SM', 'ST', 'SA', 'SN', 'RS', 'SC', 'SL', 'SG', 'SK', 'SI', 'SB', 'SO', 'ZA', 'GS', 'SS', 'ES', 'LK', 'SD', 'SR', 'SJ', 'SZ', 'SE', 'CH', 'SY', 'TW', 'TJ', 'TZ', 'TH', 'TL', 'TG', 'TK', 'TO', 'TT', 'TN', 'TR', 'TM', 'TC', 'TV', 'UG', 'UA', 'AE', 'GB', 'US', 'UM', 'UY', 'UZ', 'VU', 'VE', 'VN', 'VI', 'WF', 'EH', 'YE', 'ZM', 'ZW'];
	/**
     * @brief array ISO 639-1 languages code
     */
	private $iso639_1 = ['AA', 'AB', 'AE', 'AF', 'AK', 'AM', 'AN', 'AR', 'AS', 'AV', 'AY', 'AZ', 'BA', 'BE', 'BG', 'BH', 'BI', 'BM', 'BN', 'BO', 'BR', 'BS', 'CA', 'CE', 'CH', 'CO', 'CR', 'CS', 'CU', 'CV', 'CY', 'DA', 'DE', 'DV', 'DZ', 'EE', 'EL', 'EN', 'EO', 'ES', 'ET', 'EU', 'FA', 'FF', 'FI', 'FJ', 'FO', 'FR', 'FY', 'GA', 'GD', 'GL', 'GN', 'GU', 'GV', 'HA', 'HE', 'HI', 'HO', 'HR', 'HT', 'HU', 'HY', 'HZ', 'IA', 'ID', 'IE', 'IG', 'II', 'IK', 'IO', 'IS', 'IT', 'IU', 'JA', 'JV', 'KA', 'KG', 'KI', 'KJ', 'KK', 'KL', 'KM', 'KN', 'KO', 'KR', 'KS', 'KU', 'KV', 'KW', 'KY', 'LA', 'LB', 'LG', 'LI', 'LN', 'LO', 'LT', 'LU', 'LV', 'MG', 'MH', 'MI', 'MK', 'ML', 'MN', 'MO', 'MR', 'MS', 'MT', 'MY', 'NA', 'NB', 'ND', 'NE', 'NG', 'NL', 'NN', 'NO', 'NR', 'NV', 'NY', 'OC', 'OJ', 'OM', 'OR', 'OS', 'PA', 'PI', 'PL', 'PS', 'PT', 'QU', 'RC', 'RM', 'RN', 'RO', 'RU', 'RW', 'SA', 'SC', 'SD', 'SE', 'SG', 'SH', 'SI', 'SK', 'SL', 'SM', 'SN', 'SO', 'SQ', 'SR', 'SS', 'ST', 'SU', 'SV', 'SW', 'TA', 'TE', 'TG', 'TH', 'TI', 'TK', 'TL', 'TN', 'TO', 'TR', 'TS', 'TT', 'TW', 'TY', 'UG', 'UK', 'UR', 'UZ', 'VE', 'VI', 'VO', 'WA', 'WO', 'XH', 'YI', 'YO', 'ZA', 'ZH', 'ZU'];
    /**
     *  @brief constructor
     *  
     *  @param string $loginName Skype or live username
     *  @param string $password password
     *  @param string $dataPath path to where should be stored session files and cookieJar
     *  @return void
     */
    public function __construct($loginName, $password, $dataPath)
    {
		$this->username = $loginName;
		$this->dataPath = $dataPath;
        $this->transport = new Transport($loginName, $password, $dataPath);
    }
	
    /**
     *  @brief use MS oauth implemented in SkypeLogin class
     *  
     *  @return boolean
     */
    public function login()
    {
        if(true === $this->transport->login()) {
			$this->profile = $this->transport->loadFullProfile();
			$tmp = $this->transport->initLoadContacts();
			if($tmp) {
				$this->contacts = $tmp->contacts;
				$this->groups = $tmp->groups;
				$this->blocklist = $tmp->blocklist;
			}
			return true;
		} else {
			return false;
		}
    }
	
	/**
	 *  @brief get registration token and mount endpoints
	 *  
	 *  @param string $status  self::STATUS_ONLINE|self::STATUS_HIDDEN|self::STATUS_BUSY
	 *  @return boolean
	 */
	public function enableMessaging($status=null) {
		$ret = false;
		if($this->transport->enableMessaging()) {
			if(null !== $status) {
				$this->transport->setStatus($status);
			}
			$this->conversations = $this->transport->loadConversations();
			$this->threads = $this->loadThreads();
			return true;
		} else {
			return false;
		}
	}

	/**
	 *  @brief free subscriptions and endpoint. force refresh of cached endpoint
	 *  
	 *  @return boolean
	 */
	public function disableMessaging() {
		return $this->transport->disableMessaging();
	}
	
    /**
     *  @brief should free resources
     *  
     *  @return void
     */
    public function logout()
    {
        $this->transport->logout();
    }
	
	/**
	 *  @brief self explanatory
	 *  
	 *  @return boolean
	 */
	public function pingWebHost() {
		return $this->transport->pingWebHost();
	}

	/**
	 *  @brief initiate ASM authorization
	 *  
	 *  @return boolean
	 */
	public function skypeTokenAuth() {
		return $this->transport->skypeTokenAuth();
	}

	/**
	 *  @brief initiate PES authorization
	 *  
	 *  @return boolean
	 */
	public function getPeToken() {
		return $this->transport->getPeToken();
	}

    /**
     *  @brief set user status
     *  
     *  @param string $status a string amongst Online, Busy, Hidden
     *  @return boolean
     */
	public function setStatus($status) {
		if(!in_array($status, [self::STATUS_ONLINE, self::STATUS_BUSY, self::STATUS_HIDDEN])) {
			return false;
		}
		return $this->transport->setStatus($status);
	}
	
	/**
	 *  @brief concat firstanme and lastname of current user
	 *  
	 *  @return string the user displayname
	 */
	public function getMydisplayname() {
		return trim($this->profile->firstname.' '.$this->profile->lastname);
	}

	/**
	 *  @brief attempt to find matching MRI for username
	 *  
	 *  @param string $username the username
	 *  @return string MRI or username if not found
	 */
	public function usernameToMri($username) {
		$match = [];
		if(false === strpos($username, ':') ) {
			if(false !== ($pos=strpos($username, '@'))) {
				$lookUp = substr($username, 0, $pos+1);
			}
			$lookUp = '8:live:'.$username;
		} else {
			$lookUp = $username;
		}
		$lookUpLen = strlen($lookUp);
		foreach($this->contacts as $contact) {
			if($lookUp == substr($contact->mri, -$lookUpLen)) {
				$match[] = $contact->mri;
			}
		}
		$cnt = count($match);
		if(0 == $cnt) {
			foreach($this->conversations as $conversation) {
				if($lookUp == substr($conversation->id, -$lookUpLen)) {
					$match[] = $conversation->id;
				}
			}
		}
		$match = array_unique($match, SORT_STRING);
		$cnt = count($match);
		if(1 == $cnt) {
			return $match[0];
		} else {
			echo "No match for username [$username]", PHP_EOL;
			if(false === strpos($username, ':') ) {
				$username = '8:live:'.$username;
			}
			return $username;
		}
	}

	/**
	 *  @brief update user profile with an array of key, value pairs
	 *  
	 *  @param array $data properties to be serialized in JSON format
	 *  @param boolean $refresh wether to resend profile request
	 *  @return boolean
	 */
	public function updateProfile(array $data, $refresh=false) {
		$possibleKeys = [
			'about', 'birthday', 'city', 'country',
			'firstname', 'gender', 'homepage', 'jobtitle',
			'language', 'lastname', 'mood', 'phonehome', 
			'phonemobile', 'phoneoffice', 'province', 'richmood'
		];
		foreach($data as $k=>$v) {
			if(!in_array($k, $possibleKeys)) {
				echo 'dropped invalid property [', $k, '] property name must be lowercased', PHP_EOL;
				unset($data[$k]);
			}
		}
		if(array_key_exists('birthday', $data) && !empty($data['birthday'])) {
			if(!preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/', $data['birthday'])) {
				echo 'dropped invalid property [birthday] date must be in the form of YYYY-MM-DD', PHP_EOL;
				unset($data['birthday']);
			}
		}
		if(array_key_exists('country', $data) && !empty($data['country'])) {
			$data['country'] = strtoupper($data['country']);
			if(!in_array($data['country'], $this->isoAlpha2)) {
				echo 'dropped invalid property [country] country code not found (two letters code)', PHP_EOL;
				unset($data['country']);
			}
		}
		if(array_key_exists('gender', $data) && !empty($data['gender'])) {
			if(!in_array($data['gender'], [1,2])){
				echo 'dropped invalid property [gender] gender must be 1 for male and 2 for female', PHP_EOL;
				unset($data['gender']);
			}
		}
		if(array_key_exists('language', $data) && !empty($data['language'])) {
			$data['language'] = strtoupper($data['language']);
			if(!in_array($data['language'], $this->iso639_1)) {
				echo 'dropped invalid property [language] language code not found (two letters code)', PHP_EOL;
				unset($data['language']);
			}
		}
		if(array_key_exists('homepage', $data) && !empty($data['homepage'])) {
			$check = file_get_contents($data['homepage'], false, stream_context_create(['http'=>['method'=>'GET']]));
			$tokens = explode(' ', $http_response_header[0]);
			if(200 != $tokens[1]) {
				echo 'dropped invalid property [homepage] check returned error code ', $tokens[1], PHP_EOL;
				unset($data['homepage']);
			}
		}
		if(0 == count($data)) {
			$Result = false;
		} else {
			if(($Result = $this->transport->updateProfile($data))) {
				if(true===$refresh){
					$this->profile = $this->transport->loadFullProfile();
				} else {
					foreach($this->profile as $k=>$v) {
						$kk = strtolower($k);
						if(array_key_exists($kk, $data)) {
							$this->profile->{$k} = $data[$kk];
						}
					}
				}
			}
		}
		return $Result;
	}

	/**
	 *  @brief upload user avatar
	 *  
	 *  @param string $filename path to image file
	 *  @return boolean
	 */
	public function updateAvatar($filename) {
		$Result = $this->transport->updateAvatar($filename);
		return $Result;
	}

	/**
	 *  @brief download avatar file
	 *  
	 *  @param string $targetDir download dirname
	 *  @return mixed filename of downloaded file or null if error
	 */
	public function downloadAvatar($targetDir) {
		return $this->transport->downloadAvatar($this->profile->avatarUrl, $targetDir);
	}

	/**
	 *  @brief retrieve users matching $searchstring
	 *  
	 *  @param string $searchstring the search string
	 *  @return mixed array or null if error
	 */
	public function searchUserDirectory($searchstring) {
		return $this->transport->searchUserDirectory($searchstring);
	}
	
	/**
	 *  @brief send authorization request to given user
	 *  
	 *  @param string $mri target user (MRI)
	 *  @param string $greeting greeting message
	 *  @return boolean
	 */	
	public function sendContactRequest($mri, $greeting='Hello would you please add me to your contact list') {
		$Result = $this->transport->sendContactRequest($mri, $greeting);
		return $Result;
	}

	/**
	 *  @brief get pending authorization requests
	 *  
	 *  @return array MRI list
	 */
	public function getInvites() {
		return $this->transport->getInvites();
	}

	/**
	 *  @brief accept or decline an authorization request
	 *  
	 *  @param string $mri MRI of requesting user
	 *  @param string $action possible values accept|decline
	 *  @return boolean
	 */
	public function acceptOrDeclineInvite($mri, $action='decline') {
		if(!in_array($action, array('accept', 'decline'))) {
			echo $action, ' not found in possible values [accept, decline]', PHP_EOL;
			return false;
		}
		if($this->transport->acceptOrDeclineInvite($mri, $action)) {
			if('accept' == $action) {
				$this->contacts = $this->transport->loadContacts();
				$this->messagingAddContact($mri);
				$this->conversations = $this->transport->loadConversations();
			}
			return true;
		} else {
			return false;
		}
	}
	
	/**
	 *  @brief remove authorization for target user
	 *  
	 *  @param string $mri MRI of target user
	 *  @return boolean
	 */
	public function deleteContact($mri) {
		$mri = $this->usernameToMri($mri);
		if($mri) {
			return $this->transport->deleteContact($mri);
		} else {
			return false;
		}
	}

	/**
	 *  @brief add the target user to the block list
	 *  
	 *  @param string $mri MRI of target user
	 *  @return boolean
	 */
	public function blockContact($mri) {
		$mri = $this->usernameToMri($mri);
		if($mri) {
			return $this->transport->blockContact($mri);
		} else {
			return false;
		}
	}

	/**
	 *  @brief remove target user from the block list
	 *  
	 *  @param string $mri MRI od target user
	 *  @return boolean
	 */
	public function unblockContact($mri) {
		$mri = $this->usernameToMri($mri);
		if($mri) {
			return $this->transport->unblockContact($mri);
		} else {
			return false;
		}
	}

	/**
	 *  @brief retrieve the list of blocked users
	 *  
	 *  @return mixed array list of MRIs or null if error
	 */
	public function getBlockList() {
		$Result = $this->transport->getBlockList();
		return $Result;
	}

    /**
     *  @brief get target user profile in local contacts list
     *  
     *  @param string $mri MRI of target user
     *  @return mixed array or null if not found
     */
    public function getContact($mri)
    {
		$mri = $this->usernameToMri($mri);
		if($mri) {
			$contact = array_filter($this->contacts, function ($current) use ($mri) {
				return $current->mri == $mri;
			});

			return reset($contact);
		} else {
			return null;
		}
    }

	/**
	 *  @brief download avatar file
	 *  
	 *  @param $mri MRI of the target contact
	 *  @param string $targetDir download dirname
	 *  @return mixed filename of downloaded file or null if error
	 */
	public function downloadContactAvatar($mri, $targetDir) {
		$mri = $this->usernameToMri($mri);
		$c = $this->getContact($mri);
		print_r($c);
		if($c) {
			return $this->transport->downloadAvatar($c->profile->avatar_url, $targetDir);
		} else {
			echo 'conversation [', $mri,'] not found', PHP_EOL;
		}
	}

	/**
	 *  @brief set agent attribute to currentenpoint (also used to probe endpoint)
	 *  
	 *  @return boolean
	 */
	public function setEndpointFeaturesAgent() {
		if(($sessiondata = file_get_contents($this->dataPath.$this->username.'-session.json'))) {
			$sessionData = json_decode($sessiondata, true);
			if(is_array($sessionData) && array_key_exists('regToken', $sessionData)) {
				return $this->transport->setEndpointFeaturesAgent($sessionData['regToken']);
			} else {
				return false;
			}
		} else {
			return false;
		}
	}
	
	/**
	 *  @brief undocumented method
	 *  
	 *  @return boolean
	 */
	public function endpointSetSupportMessageProperties() {
		return $this->transport->endpointSetSupportMessageProperties();
	}

	/**
	 *  @brief self explanatory
	 *  
	 *  @return boolean
	 */
	public function pingGateway() {
		return $this->transport->pingGateway();
	}

	/**
	 *  @brief set TTL for current endpoint
	 *  
	 *  @return boolean
	 */
	public function endpointTtl($ttl=12) {
		return $this->transport->endpointTtl($ttl);
	}
	
	/**
	 *  @brief messaging details of current user
	 *  
	 *  @return mixed stdClass or null if error
	 */
	public function messagingGetMyProperties(){
		return $this->transport->messagingGetMyProperties();
	}

	/**
	 *  @brief list of connected endpoints and availability
	 *  
	 *  @return mixed stdClass or null if error
	 */
	public function messagingGetMyPresenceDocs() {
		return $this->transport->messagingGetMyPresenceDocs();
	}
	
	/**
	 *  @brief retrieve status of current user
	 *  
	 *  @return string possible values [Online, Busy, Hidden]
	 */
	public function getStatus(){
		$tmp = $this->messagingGetMyPresenceDocs();
		return is_object($tmp) && isset($tmp->status) ? $tmp->status : 'undefined';
	}
	
	/**
	 *  @brief add a contact to the conversation list
	 *  
	 *  @param string $mri MRI of target user
	 *  @return boolean
	 */
	public function messagingAddContact($mri) {
		$mri = $this->usernameToMri($mri);
		return $this->transport->messagingAddContact($mri);
	}

	/**
	 *  @brief undocumented method
	 *  
	 *  @param array $mriList the list of MRIs to post
	 *  @return boolean
	 */
	public function messagingPostContacts(array $mriList) {
		foreach($mriList as $ndx => $mri) {
			$mriList[$ndx] = $this->usernameToMri($mri);
		}
		return $this->transport->messagingPostContacts($mriList);
	}
	
	/**
	 *  @brief retrieve conversation details from local conversations list
	 *  
	 *  @param string $mri MRI of target conversation
	 *  @return stdClass a reference on the found object or null if error
	 */
	public function &getConversation($mri) {
        foreach($this->conversations as $ndx => &$conversation) {
            if($mri == $conversation->id) {
				return $conversation;
			}
        }
		$ret = null;
		return $ret;
	}

	/**
	 *  @brief empty the target conversation
	 *  
	 *  @param string $mri MRI of target conversation
	 *  @return boolean
	 */
	public function deleteConversation($mri) {
		$mri = $this->usernameToMri($mri);
		if($mri) {
			return $this->transport->deleteConversation($mri);
		} else {
			return false;
		}
	}

    /**
     *  @brief send a richText message to the target conversation
     *  
     *  @param string $text plain or richText contents
     *  @param string $mri MRI of target conversation
     *  @return mixed int message id or false if error
     */
    public function sendMessage($text, $mri) {
		$mri = $this->usernameToMri($mri);
		if($mri) {
			$displayname = trim($this->profile->firstname.' '.$this->profile->lastname);
			return $this->transport->send($mri, $displayname, $text);
		} else {
			return false;
		}
    }

    /**
     *  @brief edit a message from target conversation
     *  
     *  @param string $text plain or richText contents
     *  @param string $mri MRI of target conversation
     *  @param int $message_id id of target message
     *  @return mixed int message id or false if error
     */
    public function editMessage($text, $mri, $message_id)
    {
		$mri = $this->usernameToMri($mri);
		if($mri) {
			$displayname = $this->getMyDisplayname();
			return $this->transport->send($mri, $displayname, $text, $message_id);
		} else {
			return false;
		}
    }

    /**
     *  @brief delete a message from target conversation
     *  
     *  @param string $mri MRI of target conversation
     *  @param int $message_id id of target message
     *  @return mixed int message id or false if error
     */
    public function deleteMessage($mri, $message_id){
		$mri = $this->usernameToMri($mri);
		if($mri) {
			$fromDisplayname = $this->getMyDisplayname();
			return $this->transport->send($mri, $fromDisplayname, '', $message_id);
		} else {
			return false;
		}

    }

	/**
	 *  @brief send a contact card
	 *  
	 *  @param string $mri MRI of target user
	 *  @param string $contactMri MRI of contact to be sent
	 *  @return mixed int ID of sent message or false if error
	 */
	public function sendContact($mri, $contactMri) {
		$mri = $this->usernameToMri($mri);
		$contactMri = $this->usernameToMri($contactMri);
		$fromDisplayname = $this->getMyDisplayname();
		$contact = $this->getContact($contactMri);
		if(is_object($contact) && isset($contact->mri)) {
			$contactDisplayname = $contact->display_name;
			return $this->transport->sendContact($mri, $fromDisplayname, $contactMri, $contactDisplayname);
		} else {
			echo $contactMri,' not found in contacts list', PHP_EOL;
			return false;
		}
	}

	/**
	 *  @brief share an image with recipients in $mrisWithAccessRights
	 *  
	 *  @param array $mrisWithAccessRights recipients list like [8:live:john.doe => [read]]
	 *  @param string $filename path of the target image
	 *  @return mixed stdClass details on uploaded file or false if error
	 */
	public function sendImage(array $mrisWithAccessRights, $filename) {
		$passed = [];
		foreach($mrisWithAccessRights as $mri => $perms) {
			$mri = $this->usernameToMri($mri);
			$passed[$mri] = $perms;
			unset($mrisWithAccessRights[$mri]);
		}
		$fromDisplayname = $this->getMyDisplayname();
		return $this->transport->sendImage($passed, $filename, $fromDisplayname);
	}

	/**
	 *  @brief share file with recipients in $mrisWithAccessRights
	 *  
	 *  @param array $mrisWithAccessRights recipients list like [8:live:john.doe => [read]]
	 *  @param string $filename path of the target file
	 *  @return mixed stdClass details on uploaded file or false if error
	 */
	public function sendFile(array $mrisWithAccessRights, $filename) {
		$passed = [];
		foreach($mrisWithAccessRights as $mri => $perms) {
			$mri = $this->usernameToMri($mri);
			$passed[$mri] = $perms;
			unset($mrisWithAccessRights[$mri]);
		}
		$fromDisplayname = $this->getMyDisplayname();
		return $this->transport->sendFile($passed, $filename, $fromDisplayname);
	}

	/**
	 *  @brief retrieve unread messages
	 *  
	 *  @return array message list
	 */
	public function getNewMessages() {
		return $this->transport->getNewMessages();
	}

    /**
     *  @brief set callback function on message polling
     *  
     *  @param function $callback the function to execute on new messages
     *  @return void
     */
    public function onMessage($callback)
    {
        while (true) {
            call_user_func_array($callback, [
                $this->transport->getNewMessages(),
                $this
            ]);

            sleep(1);
        }
    }
	
	/**
	 *  @brief set conversation's consumption horizon 
	 *  
	 *  @param int $conversationId ID of the conversation
	 *  @param int $messageId ID of message to marked as seen
	 *  @return boolean or false on error
	 */
	public function setConsumptionHorizon($conversationId, $messageId) {
		return $this->transport->setConsumptionHorizon($conversationId, $messageId);
	}

	/**
	 *  @brief inititate the thread list from the conversation list
	 *  
	 *  @return array the thread list
	 */
	public function loadThreads() {
		$ret = [];
		if(is_array($this->conversations) && 0<count($this->conversations)) {
			foreach($this->conversations as $ndx => $conversation) {
				if(isset($conversation->threadProperties)) {
					$tmp = $this->transport->threadInfos($conversation->id);
					if(is_object($tmp) && isset($tmp->id)) {
						$ret[] = $tmp;
					}
				}
			}
		}
		return $ret;
	}

	/**
	 *  @brief retrieve thread details from local thread list
	 *  
	 *  @param string $id target thread id
	 *  @return stdClass reference of found object
	 */
	public function &getThread($id) {
        foreach($this->threads as $ndx => &$thread) {
            if($id == $thread->id) {
				return $thread;
			}
        }
		$ret = null;
		return $ret;
	}

	/**
	 *  @brief get the index of target thread in local thread list
	 *  
	 *  @param string $id id of the target thread
	 *  @return int the found index or -1 if not found
	 */
	public function getThreadIndex($id) {
        foreach($this->threads as $ndx => $thread) {
            if($id == $thread->id) {
				return $ndx;
			}
        }
		return -1;
	}

	/**
	 *  @brief initiate a new thread
	 *  
	 *  @param string $topic topic of the new thread
	 *  @param array $members a list of members like ['id' => '8:live:john.doe', 'role' => 'Admin']
	 *  @param boolean $joiningenabled true if the thread is public and can be joined by url
	 *  @param boolean $historydisclosed true to show thread history to new members
	 *  @return boolean
	 */
	public function createThread($topic, array $members, $joiningenabled=false, $historydisclosed=false) {
		$selfFound = false;
		$myMri = '8:'.$this->username;
		foreach($members as $ndx => $member) {
			if(!is_array($member)) {
				$members[$ndx] = ['id' => $member, 'role' => 'User'];
			}
			$members[$ndx]['id'] = $this->usernameToMri($members[$ndx]['id']);
			if($myMri == $members[$ndx]['id']) {
				$selfFound = true;
				$members[$ndx]['role'] = 'Admin';
			}
		}
		if(false == $selfFound) {
			$members[] = ['id' => $myMri, 'role' => 'Admin'];
		}
		$members = array_values($members);
		if(1 == count($members) && $myMri == $members[0]['id']) {
			return false;
		}
		$threadUrl = $this->transport->initiateThread($members);
		if($threadUrl) {
			$threadId = substr($threadUrl, strrpos($threadUrl, '/')+1);
			$this->setThreadProperty($threadId, 'joiningenabled', $joiningenabled ? 'true' : 'false');
			$this->setThreadProperty($threadId, 'historydisclosed', $historydisclosed ? 'true' : 'false');
			$this->setThreadProperty($threadId, 'topic', $topic);
			$this->conversations = $this->transport->loadConversations();
			$this->threads = $this->loadThreads();
			return true;
		} else {
			return false;
		}
	}

	/**
	 *  @brief set the avatar image of a thread
	 *  
	 *  @param string $id id of the target thread
	 *  @param array $perms permissions 
	 *  @param string $filename path to the image to upload
	 *  @return mixed stdClass details on the uploaded file or false on error
	 */
	public function setThreadAvatar($id, array $perms, $filename) {
		if($uploadInfos=$this->transport->setThreadAvatar($id, $perms, $filename)) {
			if(is_object($t = $this->getThread($id))) {
				if(!isset($t->properties->picture)) {
					$t->properties->picture = '';
				}
				$t->properties->picture = 'URL@'.$uploadInfos->view_location;
				$c = $this->getConversation($id);
				if($c) {
					$c->threadProperties->picture = $t->properties->picture;
				}
			}
			return $uploadInfos;
		} else {
			return false;
		}
	}

	/**
	 *  @brief download avatar file
	 *  
	 *  @param string $id id of the target thread
	 *  @param string $targetDir download dirname
	 *  @return mixed filename of downloaded file or null if error
	 */
	public function downloadThreadAvatar($id, $targetDir) {
		$t = $this->getThread($id);
		print_r($t);
		if($t) {
			$url = substr($t->properties->picture, strpos($t->properties->picture, '@')+1);
			return $this->transport->downloadAvatar($url, $targetDir, $id);
		} else {
			echo 'thread [', $id,'] not found', PHP_EOL;
		}
	}

	/**
	 *  @brief add a new member or modify existing user's role
	 *  
	 *  @param string $id id of the target thread
	 *  @param string $addId MRI of the user to ad or edit
	 *  @param string $role value in list of ['User', 'Admin']
	 *  @return boolean
	 */
	public function addOrEditThreadMember($id, $addId, $role='User') {
		$addId = $this->usernameToMri($addId);
		if(!$addId) {
			echo 'User [', $addId,'] not found', PHP_EOL;
			return false;
		}
		if(-1 == ($threadIndex = $this->getThreadIndex($id))) {
			echo 'Thread [', $id,'] not found', PHP_EOL;
			return false;
		}
		$thread = $this->getThread($id);
		$countAdmins = 0;
		$targetContactIsAdmin = false;
		$targetContactIndex = -1;
		foreach($thread->members as $ndx => $member) {
			if($addId == $member->id) {
				$targetContactIndex = $ndx;
			}
			if('admin' == strtolower($member->role)) {
				$countAdmins++;
				if($addId == $member->id) {
					$targetContactIsAdmin = true;
				}
			}
		}
		if('user' == strtolower($role) && $targetContactIsAdmin && 1 == $countAdmins) {
			echo 'Cancelled. No admins after operation', PHP_EOL;
			return false;
		}
		if($this->transport->addOrEditThreadMember($id, $addId, $role)) {
			if(-1 == $targetContactIndex) {
				$this->threads[$threadIndex] = $this->transport->threadInfos($id);
				$conversation = $this->getConversation($id);
				if($conversation) {
					$conversation->threadProperties->membercount++;
					$conversation->threadProperties->members[] = $addId;
				}
			} else {
				$thread->members[$targetContactIndex]->role = $role;
			}
			return true;
		} else {
			return false;
		}
	}

	/**
	 *  @brief remove user from the members list
	 *  
	 *  @param string $id id of the target thread
	 *  @param string $rmId MRI of the members to remove
	 *  @return boolean
	 */
	public function removeThreadMember($id, $rmId) {
		if(-1 == ($threadIndex = $this->getThreadIndex($id))) {
			echo 'Thread [', $id,'] not found', PHP_EOL;
			return false;
		}
		$thread = $this->getThread($id);
		$countAdmins = 0;
		$targetContactIsAdmin = false;
		$targetContactIndex = -1;
		foreach($thread->members as $ndx => $member) {
			if($rmId == $member->id) {
				$targetContactIndex = $ndx;
			}
			if('admin' == strtolower($member->role)) {
				$countAdmins++;
				if($rmId == $member->id) {
					$targetContactIsAdmin = true;
				}
			}
		}
		if($targetContactIsAdmin && 1 == $countAdmins) {
			echo 'Cancelled. No admins after operation', PHP_EOL;
			return false;
		}
		if($this->transport->removeThreadMember($id, $rmId)) {
			unset($thread->members[$targetContactIndex]);
			$thread->members = array_values($thread->members);
			$conversation = $this->getConversation($id);
			if($conversation) {
				$conversation->threadProperties->membercount--;
				$ndx = array_search($rmId, $conversation->threadProperties->members);
				if(false !== $ndx) {
					unset($conversation->threadProperties->members[$ndx]);
					$conversation->threadProperties->members = array_values($conversation->threadProperties->members);
				}
			}
			return true;
		} else {
			return false;
		}
	}

	/**
	 *  @brief update a thread property
	 *  
	 *  @param string $id id of target thread
	 *  @param string $key name of the property in possible values ['joiningenabled', 'historydisclosed', 'topic']
	 *  @param mixed $value string for topic, boolean for others
	 *  @return boolean
	 */
	public function setThreadProperty($id, $key, $value) {
		if(!in_array($key , ['joiningenabled', 'historydisclosed', 'topic'])) {
			echo 'property [', $key, '] not found in possible values [joiningenabled, historydisclosed, topic]', PHP_EOL;
			return false;
		}
		if($this->transport->setThreadProperty($id, $key, $value)) {
			$key = strtolower($key);
			if('topic' == $key) {
				$thread = $this->getThread($id);
				if($thread) {
					$thread->properties->topic = $value;
				}
				$conversation = $this->getConversation($id);
				if($conversation) {
					$conversation->threadProperties->topic = $value;
				}
			}
			return true;
		} else {
			return false;
		}
	}

	/**
	 *  @brief set topic for a thread
	 *  
	 *  @param string $id id of the target thread
	 *  @param string $newTopic new topic contents
	 *  @return boolean
	 */
	public function threadChangeTopic($id, $newTopic) {
		$thread = $this->getThread($id);
		if($thread && in_array('ChangeTopic', $thread->properties->capabilities)) {
			return $this->setThreadProperty($threadId, 'topic', $newTopic);
		} else {
			echo 'thread [', $id, '] not found or has not permission [ChangeTopic]', PHP_EOL;
			return false;
		}
	}

	/**
	 *  @brief set wether or not the thread is public
	 *  
	 *  @param string $id id of the target thread
	 *  @param boolean $joiningenabled
	 *  @return boolean
	 */
	public function threadJoiningEnabled($id, $joiningenabled=false) {
		$thread = $this->getThread($id);
		if($thread) {
			return $this->setThreadProperty($threadId, 'joiningenabled', $joiningenabled ? 'true' : 'false');
		} else {
			echo 'thread [', $id, '] not found', PHP_EOL;
			return false;
		}
	}

	/**
	 *  @brief set wether or not new members can see the thread history
	 *  
	 *  @param string $id id of the target thread
	 *  @param boolean $historydisclosed
	 *  @return boolean
	 */
	public function threadHistoryDisclosed($id, $historydisclosed=false) {
		$thread = $this->getThread($id);
		if($thread) {
			return $this->setThreadProperty($threadId, 'historydisclosed', $historydisclosed ? 'true' : 'false');
		} else {
			echo 'thread [', $id, '] not found', PHP_EOL;
			return false;
		}
	}

	/**
	 *  @brief empty the target thread (conversation)
	 *  
	 *  @param string $id id of the target thread
	 *  @return boolean
	 */
	public function deleteThread($id) {
		$thread = $this->getThread($id);
		if($thread) {
			return $this->transport->deleteThread($id);
		} else {
			echo 'thread [', $id, '] not found', PHP_EOL;
			return false;
		}
	}
}