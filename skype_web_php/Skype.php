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
     * @var Transport
     */
    private $transport;

    /**
     *
     */
    public function __construct()
    {
        $this->transport = new Transport();
    }

    /**
     * @param $username
     * @param $password
     * @throws \Exception
     */
    public function login($username, $dataPath)
    {
        $this->transport->login($username, $dataPath);
        $this->profile = $this->transport->loadFullProfile();
        $this->contacts = $this->transport->loadContacts();
        $this->transport->createStatusEndpoint();
        $this->transport->subscribeToResources();
        $this->transport->setStatus(self::STATUS_ONLINE);
    }

    /**
     *
     */
    public function logout()
    {
        $this->transport->logout();
    }
	
	public function updateProfile($data) {
		$Result = $this->transport->updateProfile($data);
		return $Result;
	}
	
	public function updateAvatar($loginName, $filename) {
		$Result = $this->transport->updateAvatar($loginName, $filename);
		return $Result;
	}
	
	public function sendContactRequest($username, $greeting='Hello would you please add me to your contact list') {
		$Result = $this->transport->sendContactRequest($username, $greeting);
		return $Result;
	}
	
	public function getInvites() {
		return $this->transport->getInvites();
	}
	
	public function acceptOrDeclineInvite($mri, $action='decline') {
		return $this->transport->acceptOrDeclineInvite($mri, $action='decline');
	}
	
	public function deleteContact($mri) {
		return $this->transport->deleteContact($mri);
	}
	
	public function blockContact($mri) {
		$Result = $this->transport->blockContact($mri);
		return $Result;
	}
	
	public function unblockContact($mri) {
		$Result = $this->transport->unblockContact($mri);
		return $Result;
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
	
	public function deleteConversation($mri) {
		return $this->transport->deleteConversation($mri);
	}

    /**
     * @param $text
     * @param $contact
     * @return bool|float
     */
    public function sendMessage($text, $contact)
    {
        return $this->transport->send($contact, $text);
    }

    /**
     * @param $text
     * @param $contact
     * @param $message_id
     * @return bool|float
     */
    public function editMessage($text, $contact, $message_id)
    {
        return $this->transport->send($contact, $text, $message_id);
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
}