<?php
include('GoogleVoice.php');

$gv = new GoogleVoice('your.name', 'password');

// send an SMS to a phone number
$gv->sendSMS('+15035551212', 'Sending a message!');

// get all SMSs marked as "unread"
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
