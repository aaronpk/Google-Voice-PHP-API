<?php
/*
	As of February 3rd, 2013 all functionality
	is once again working.
*/

include('GoogleVoice.php');

// NOTE: Full email address required.
$gv = new GoogleVoice('your.email@domain.com', 'password');

// Call a phone from one of your forwarding phones.
$gv->callNumber('9995551212', '5558675309', 'mobile');

// Send an SMS to a phone number.
$gv->sendSMS('9995551212', 'Sending a message!');

// Get all unread SMSs from your Google Voice Inbox.
$sms = $gv->getUnreadSMS();
$msgIDs = array();
foreach($sms as $s) {
	echo 'Message from: ' . $s['phoneNumber'] . ' on ' . $s['date'] . ': ' . $s['message'] . "<br><br>\n";
	if(!in_array($s['msgID'], $msgIDs)) {
		// Mark the message as read in your Google Voice Inbox.
		$gv->markMessageRead($s['msgID']);
		$msgIDs[] = $s['msgID'];
	}
}

// Get all unread messages from your Google Voice Voicemail.
$voice_mails = $gv->getUnreadVoicemail();
$msgIDs = array();
foreach($voice_mails as $v) {
	echo 'Message from: ' . $v['phoneNumber'] . ' on ' . $v['date'] . ': ' . $v['message'] . "<br><br>\n";
	if(!in_array($v['msgID'], $msgIDs)) {
		// Mark this message as read in your Google Voice Voicemail.
		$gv->markMessageRead($v['msgID']);
		$msgIDs[] = $v['msgID'];
	}
}

?>
