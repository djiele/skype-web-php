# skype-web-php
PHP client for Skype Web API

## Installation

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
$ php composer.phar require djiele/skype-web-php "dev-master"
```

or add

```
"djiele/skype-web-php": "dev-master"
```

to the `require` section of your `composer.json` file.

## Usage

#### get Skype object

```
use skype_web_php\Skype;
$appDataPath = getcwd().DIRECTORY_SEPARATOR.'app-data'.DIRECTORY_SEPARATOR;
$skype = new Skype($username, $passwd, $appDataPath);
```

where $username is your skype login (no phone number support yet),

$password is self explanatory

$appDataPath is the path to the app-data folder where are cached skype current connection (one day expiry)

#### Do the connection process

use cached connection if not expired or do the full login process
```
$skype->login() or die('Login failed');
echo 'Connected as ', $skype->getMyDisplayname(), PHP_EOL;
```

#### Update profile
```
if($skype->updateProfile(["richmood" => "i am very happy <ss type=\"laugh\">:D</ss>", "mood" => " i am very happy" , "firstName" => "John", "lastName" => "Doe"])) {`
	echo 'profile updated', PHP_EOL;
}
if($skype->updateAvatar("/path/to/image")) {`
	echo 'avatar updated', PHP_EOL;`
	$skype->downloadAvatar(/path/to/folder/);`
}
```

#### Messaging
```
$skype->enableMessaging(Skype::STATUS_HIDDEN);
```
##### Send / edit / delete a text message

```
$contact_id = '8:live:username';
$message_id = $skype->sendMessage("Hello: ".date('Y-m-d H:i:s'), $contact_id);
$skype->editMessage("Hello: ".date('Y-m-d H:i:s'), $contact_id, $message_id);
$skype->deleteMessage($contact_id', $message_id);
```
##### Send file, image or skype contact

```
$fileInfos = $skype->sendFile([$contact_id=>['read', 'write']], /path/to/file);
$imgInfos=$skype->sendImage([$contact_id=>['read', 'write']], /path/to/image))
$message_id = $skype->sendContact($contact_id,  $contact_id_to_be_sent);
```
##### Retrieve new messages
```
$messages = $skype->getNewMessages();
```
#### Free resources, close the connection

```
$skype->disableMessaging();
$skype->logout();
```

#### Others

skype-web can also

- send, accept or decline invitations
- manage groupchat
- search for contact
