<?php
/**
 *  @file Skype.php
 *  @brief Skype web public API
 */
namespace skype_web_php;

/**
 * Class Skype
 * @package skype_web_php
 */
class Skype
{

    /**
     *
     */
    const STATUS_ONLINE = 'Online';
    /**
     *
     */
    const STATUS_HIDDEN = 'Hidden';
    /**
     *
     */
    const STATUS_BUSY = 'Busy';
    /**
     * @var
     */
    public $profile;
    /**
     * @var
     */
    public $contacts;
    /**
     * @var
     */
    public $groups;
    /**
     * @var
     */
    public $blocklist;
    /**
     * @var
     */
    public $conversations;
    /**
     * @var
     */
    public $threads;
	/**
     * @var Transport
     */
    private $transport;
	/**
     * @var Username
     */
    private $username;
	/**
     * @var Datapath
     */
    private $datapath;
	
    /**
     *  @brief constructor
     *  
     *  @param string $loginName Skype or live username
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
				$lookUp = substr($username, $pos+1);
			}
			$lookUp = ':'.$username;
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
			echo "No match or ambiguous username [$username]. Returned as is.", PHP_EOL;
			return $username;
		}
	}

	/**
	 *  @brief update user profile with an array of key, value pairs
	 *  
	 *  @param array $data properties to be serialized in JSON format
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
				echo 'dropped invalid property [', $k, ']', PHP_EOL;
				unset($data[$k]);
			}
		}
		if(0 == count($data)) {
			$Result = false;
		} else {
			if(($Result = $this->transport->updateProfile($data))) {
				if(true===$refresh){
					$this->profile = $this->transport->loadFullProfile();
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
		return $this->transport->acceptOrDeclineInvite($mri, $action='decline');
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
	 *  @param array $sessionData an array containing endpoint URL and registration token
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
		return null;
	}

	/**
	 *  @brief empty the target conversation
	 *  
	 *  @param string MRI of target conversation
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
		return null;
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
		foreach($members as $ndx => $member) {
			$member['id'] = $this->usernameToMri($member['id']);
			if(!$member['id']) {
				unset($members[$ndx]);
				continue;
			}
			if($this->profile->mri == $member['id']) {
				$selfFound = true;
				$members[$ndx]['role'] = 'Admin';
			}
		}
		if(false == $selfFound) {
			$members[] = ['id' => $this->profile->mri, 'role' => 'Admin'];
		}
		$members = array_values($members);
		if(1 == count($members) && $this->profile->mri == $members[0]['id']) {
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