<?php
include('GoogleVoice.php');

$gv = new GoogleVoice('your.name', 'password');

// call a phone from one of your forwarding phones
$gv->callNumber('9995551212', '5558675309', 'mobile');

// send an SMS to a phone number
$gv->sendSMS('9995551212', 'Sending a message!');

// fetch new voicemails
$voicemails = $gv->getNewVoicemail();

$msgIDs = array();
foreach( $voicemails as $s )
{
        preg_match('/\+1([0-9]{3})([0-9]{3})([0-9]{4})/', $s['phoneNumber'], $match);
        $phoneNumber = '(' . $match[1] . ') ' . $match[2] . '-'. $match[3];
        echo 'Message from: ' . $phoneNumber . ' on ' . $s['date'] . "\n" . $s['message'] . "\n\n";

        if( !in_array($s['msgID'], $msgIDs) )
        {
                // mark the conversation as "read" in google voice
                $gv->markSMSRead($s['msgID']);
                $msgIDs[] = $s['msgID'];
        }
}

// get all new SMSs
$sms = $gv->getNewSMS();

$msgIDs = array();
foreach( $sms as $s )
{
        preg_match('/\+1([0-9]{3})([0-9]{3})([0-9]{4})/', $s['phoneNumber'], $match);
        $phoneNumber = '(' . $match[1] . ') ' . $match[2] . '-'. $match[3];
        echo 'Message from: ' . $phoneNumber . ' on ' . $s['date'] . ': ' . $s['message'] . "\n";

        if( !in_array($s['msgID'], $msgIDs) )
        {
                // mark the conversation as "read" in google voice
                $gv->markSMSRead($s['msgID']);
                $msgIDs[] = $s['msgID'];
        }
}

?>
