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
    public function login($username, $password, $skypeToken)
    {
        $this->transport->login($skypeToken);
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
	
	public function getInvites() {
		return $this->transport->getInvites();
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

    /**
     * @param $callback
     */
    public function onMessage($callback)
    {
        while (true) {
            call_user_func_array($callback, [
                $this->transport->getNewMessages($this->profile->username),
                $this
            ]);

            sleep(1);
        }
    }
}