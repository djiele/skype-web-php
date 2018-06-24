<?php
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
     *
     */
    public function __construct($loginName, $dataPath)
    {
        $this->transport = new Transport($loginName, $dataPath);
    }

    /**
     * @param $username
     * @param $password
     * @throws \Exception
     */
    public function login()
    {
        if(true === $this->transport->login()) {
			$this->profile = $this->transport->loadFullProfile();
			$this->contacts = $this->transport->loadContacts();
			$this->groups = $this->transport->loadGroups();
			return true;
		} else {
			return false;
		}
    }
	
	public function enableMessaging($status) {
		$ret = false;
		if($this->transport->setRegistrationToken()) {
			$this->transport->createStatusEndpoint();
			$this->transport->subscribeToResources();
			$this->transport->setStatus($status);
			$this->conversations = $this->transport->loadConversations();
			$this->threads = $this->loadThreads();
			$ret = true;
		} else {
			$ret = false;
		}
		return $ret;
	}

    /**
     *
     */
    public function logout()
    {
        $this->transport->logout();
    }
	
	public function pingWebHost() {
		return $this->transport->pingWebHost();
	}
	
	public function skypeTokenAuth() {
		return $this->transport->skypeTokenAuth();
	}
	
	public function getPeToken() {
		return $this->transport->getPeToken();
	}
	
	public function getMydisplayname() {
		return trim($this->profile->firstname.' '.$this->profile->lastname);
	}

	public function updateProfile($data) {
		$Result = $this->transport->updateProfile($data);
		return $Result;
	}
	
	public function updateAvatar($filename) {
		$Result = $this->transport->updateAvatar($filename);
		return $Result;
	}
	
	public function sendContactRequest($mri, $greeting='Hello would you please add me to your contact list') {
		$Result = $this->transport->sendContactRequest($mri, $greeting);
		return $Result;
	}
	
	public function getInvites() {
		return $this->transport->getInvites();
	}
	
	public function acceptOrDeclineInvite($mri, $action='decline') {
		return $this->transport->acceptOrDeclineInvite($mri, $action='decline');
	}
	
	public function usernameToMri($username) {
		$match = [];
		if(false !== ($pos=strpos($username, '@'))) {
			$username = substr($username, $pos+1);
		}
		if(false === strpos($username, ':') ) {
			$lookUp = ':'.$username;
		} else {
			$lookUp = $username;
		}
		$lookUpLen = strlen($lookUp);
		foreach($this->contacts as $contact) {
			if($lookUp == substr($contact->mri, -$lookUpLen, $lookUpLen)) {
				$match[] = $contact->mri;
			}
		}
		$cnt = count($match);
		if(0 == $cnt) {
			echo "no match username \"$username\"", PHP_EOL;
			return null;
		} else if(1 < $cnt) {
			echo "username \"$username\" is ambiguous", PHP_EOL;
			return null;
		} else {
			return $match[0];
		}
	}
	
	public function deleteContact($mri) {
		$mri = $this->usernameToMri($mri);
		if($mri) {
			return $this->transport->deleteContact($mri);
		} else {
			return false;
		}
	}
	
	public function blockContact($mri) {
		$mri = $this->usernameToMri($mri);
		if($mri) {
			return $this->transport->blockContact($mri);
		} else {
			return false;
		}
	}
	
	public function unblockContact($mri) {
		$mri = $this->usernameToMri($mri);
		if($mri) {
			return $this->transport->unblockContact($mri);
		} else {
			return false;
		}
	}
	
	public function getBlockList() {
		$Result = $this->transport->getBlockList();
		return $Result;
	}

    /**
     * @param $mri
     * @return mixed
     */
    public function getContact($mri)
    {
		
        $contact = array_filter($this->contacts, function ($current) use ($mri) {
            return $current->mri == $mri;
        });

        return reset($contact);
    }
	
	public function endpointSetProperties() {
		return $this->transport->endpointSetProperties();
	}
	
	public function pingGateway() {
		return $this->transport->pingGateway();
	}
	
	public function messagingGetMyProperties(){
		return $this->transport->messagingGetMyProperties();
	}
	
	public function messagingGetMyPresenceDocs() {
		return $this->transport->messagingGetMyPresenceDocs();
	}
	
	public function getStatus(){
		$tmp = $this->messagingGetMyPresenceDocs();
		return isset($tmp->status) ? $tmp->status : 'undefined';
	}
	
	public function deleteConversation($mri) {
		$mri = $this->usernameToMri($mri);
		if($mri) {
			return $this->transport->deleteConversation($mri);
		} else {
			return false;
		}
	}

    /**
     * @param $text
     * @param $contact
     * @return bool|float
     */
    public function sendMessage($text, $contact) {
		$mri = $this->usernameToMri($mri);
		if($mri) {
			$displayname = trim($this->profile->firstname.' '.$this->profile->lastname);
			return $this->transport->send($mri, $displayname, $text);
		} else {
			return false;
		}
    }

    /**
     * @param $text
     * @param $contact
     * @param $message_id
     * @return bool|float
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
	
    public function deleteMessage($mri, $message_id){
		$mri = $this->usernameToMri($mri);
		if($mri) {
			$fromDisplayname = $this->getMyDisplayname();
			return $this->transport->send($mri, $fromDisplayname, '', $message_id);
		} else {
			return false;
		}

    }
	
	public function sendImage($mrisWithAccessRights, $filename) {
		$skipped = [];
		$passed = [];
		foreach($mrisWithAccessRights as $mri => $perms) {
			$lookup = $this->usernameToMri($mri);
			if($lookUp) {
				$passed[$mri] = $perms;
			} else {
				$skipped[$mri] = $perms;
			}
			unset($mrisWithAccessRights[$mri]);
		}
		if(0<count($passed)) {
			$fromDisplayname = $this->getMyDisplayname();
			return $this->transport->sendImage($passed, $filename, $fromDisplayname);
		} else {
			echo 'recipients were skipped ', json_encode($skipped), PHP_EOL;
			return false;
		}
	}
	
	public function sendFile($mrisWithAccessRights, $filename) {
		$skipped = [];
		$passed = [];
		foreach($mrisWithAccessRights as $mri => $perms) {
			$lookup = $this->usernameToMri($mri);
			if($lookUp) {
				$passed[$mri] = $perms;
			} else {
				$skipped[$mri] = $perms;
			}
			unset($mrisWithAccessRights[$mri]);
		}
		if(0<count($passed)) {
			$fromDisplayname = $this->getMyDisplayname();
			return $this->transport->sendFile($passed, $filename, $fromDisplayname);
		} else {
			echo 'recipients were skipped ', json_encode($skipped), PHP_EOL;
			return false;
		}
	}
	
	public function sendContact($mri, $contactMri) {
		$mri = $this->usernameToMri($mri);
		$contactMri = $this->usernameToMri($contactMri);
		if($mri && $contactMri) {
			$fromDisplayname = $this->getMyDisplayname();
			$contact = $this->getContact($contactMri);
			if(is_object($contact) && isset($contact->mri)) {
				$contactDisplayname = $contact->display_name;
			} else {
				echo $contactMri,' not found in contacts list', PHP_EOL;
				return false;
			}
			return $this->transport->sendContact($mri, $fromDisplayname, $contactMri, $contactDisplayname);
		} else {
			echo 'invalid recipient or contact', PHP_EOL;
			return false;
		}
	}
	
	public function getNewMessages() {
		return $this->transport->getNewMessages();
	}

    /**
     * @param $callback
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
	
	public function loadThreads() {
		$ret = [];
		foreach($this->conversations as $ndx => $conversation) {
			if(isset($conversation->threadProperties)) {
				$ret[] = $this->transport->threadInfos($conversation->id);
			}
		}
		return $ret;
	}
	
	public function &getThread($id) {
        foreach($this->threads as $ndx=>&$thread) {
            if($id == $thread->id) {
				return $thread;
			}
        }
		return null;
	}
	
	public function getThreadIndex($id) {
        foreach($this->threads as $ndx=>$thread) {
            if($id == $thread->id) {
				return $ndx;
			}
        }
		return -1;
	}
	
	public function createThread($topic, array $members, $joiningenabled=false, $historydisclosed=false) {
		$ret = false;
		$threadUrl = $this->transport->initiateThread($members);
		if($threadUrl) {
			$threadId = substr($threadUrl, strrpos($threadUrl, '/')+1);
			echo $threadId, PHP_EOL;
			$this->setThreadProperty($threadId, 'joiningenabled', $joiningenabled ? 'true' : 'false');
			$this->setThreadProperty($threadId, 'historydisclosed', $historydisclosed ? 'true' : 'false');
			$this->setThreadProperty($threadId, 'topic', $topic);
			return true;
		} else {
			return false;
		}
	}
	
	public function setThreadAvatar($id, array $perms, $filename) {
		return $this->transport->setThreadAvatar($id, $perms, $filename);
	}
	
	public function addOrEditThreadMember($id, $addId, $role='User') {
		if(-1 == ($threadIndex = $this->getThreadIndex($id))) {
			echo 'Thread [', $id,'] not found', PHP_EOL;
			return false;
		}
		$thread = $this->getThread($id);
		$countAdmins = 0;
		$targetContactIsAdmin = false;
		$targetContactIndex = -1;
		foreach($thread->members as $ndx => $member) {
			if('admin' == strtolower($member->role)) {
				$countAdmins++;
				if($addId == $member->id) {
					$targetContactIsAdmin = true;
					$targetContactIndex = $ndx;
				}
			}
		}
		if('admin' == strtolower($role) && $targetContactIsAdmin && 1 == $countAdmins) {
			echo 'Cancelled. No admins after operation', PHP_EOL;
			return false;
		}
		if($this->transport->addOrEditThreadMember($id, $addId, $role)) {
			if(-1 == $targetContactIndex) {
				$this->threads[$threadIndex] = $this->transport->threadInfos($id);
			} else {
				$thread->members[$targetContactIndex]->role = $role;
			}
			return true;
		} else {
			return false;
		}
	}
	
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
			if('admin' == strtolower($member->role)) {
				$countAdmins++;
				if($addId == $member->id) {
					$targetContactIsAdmin = true;
					$targetContactIndex = $ndx;
				}
			}
		}
		if('admin' == strtolower($role) && $targetContactIsAdmin && 1 == $countAdmins) {
			echo 'Cancelled. No admins after operation', PHP_EOL;
			return false;
		}
		if($this->transport->removeThreadMember($id, $rmId)) {
			unset($thread->members[$targetContactIndex]);
			return true;
		} else {
			return false;
		}
	}
	
	public function setThreadProperty($id, $key, $value) {
		$this->transport->setThreadProperty($id, $key, $value);
	}
	
	public function deleteThread($id) {
		return $this->transport->deleteThread($id);
	}
}