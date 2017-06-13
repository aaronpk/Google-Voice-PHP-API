<?php

class GoogleVoice {
	// Google account credentials.
	private $_login;
	private $_pass;

	// Special string that Google Voice requires in our POST requests.
	private $_rnr_se;

	// File handle for PHP-Curl.
	private $_ch;

	// The location of our cookies.
	private $_cookieFile;

	// Are we logged in already?
	private $_loggedIn = FALSE;



	public function __construct($login, $pass) {
		$this->_login = $login;
		$this->_pass = $pass;
		$this->_cookieFile = '/tmp/gvCookies.txt';

		$this->_ch = curl_init();
		curl_setopt($this->_ch, CURLOPT_COOKIEJAR, $this->_cookieFile);
		curl_setopt($this->_ch, CURLOPT_FOLLOWLOCATION, TRUE);
		curl_setopt($this->_ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($this->_ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 6.0)");  //was "Mozilla/5.0 (Windows NT 6.1; WOW64; Trident/7.0; rv:11.0) like Gecko"
	}



	private function _logIn() {
		global $conf;

		if($this->_loggedIn)
			return TRUE;

		// Fetch the Google Voice login page input fields
		$URL='https://accounts.google.com/ServiceLogin?service=grandcentral&passive=1209600&continue=https://www.google.com/voice/b/0/redirection/voice&followup=https://www.google.com/voice/b/0/redirection/voice#inbox';  //adding login to GET prefills with username "&Email=$this->_login"
		curl_setopt($this->_ch, CURLOPT_URL, $URL);
		$html = curl_exec($this->_ch);

		// Send HTTP POST service login request using captured input information.
		$URL='https://accounts.google.com/signin/challenge/sl/password';  // This is the second page of the two page signin
		curl_setopt($this->_ch, CURLOPT_URL, $URL);
		$postarray = $this->dom_get_input_tags($html);  // Using DOM keeps the order of the name/value from breaking the code.

		// Parse the returned webpage for the "GALX" token, needed for POST requests.
		if(!isset($postarray['GALX']) || $postarray['GALX']==''){
			$pi1 = var_export($postarray, TRUE);
			error_log("Could not parse for GALX token. Inputs from page:\n" . $pi1 . "\n\nHTML from page:" . $html);
			throw new Exception("Could not parse for GALX token. Inputs from page:\n" . $pi1);
		}

		$postarray['Email'] = $this->_login;  //Add login to POST array
		$postarray['Passwd'] = $this->_pass;  //Add password to POST array
		curl_setopt($this->_ch, CURLOPT_POST, TRUE);
		curl_setopt($this->_ch, CURLOPT_POSTFIELDS, $postarray);
		$html = curl_exec($this->_ch);

		// Test if the service login was successful.
		$postarray = $this->dom_get_input_tags($html);  // Using DOM keeps the order of the name/value from breaking the code.
		if(isset($postarray['_rnr_se']) && $postarray['_rnr_se']!='') {
			$this->_rnr_se = $postarray['_rnr_se'];
			$this->_loggedIn = TRUE;
		} else {
			$pi2 = var_export($postarray, TRUE);
			error_log("Could not log in to Google Voice with username: " . $this->_login .
					  "\n\nMay need to change scraping.  Here are the inputs from the page:\n". $pi2
					 );  //add POST action information from DOM.  May help hunt down single or dual sign on page changes.
			throw new Exception("Could not log in to Google Voice with username: " . $this->_login . "\nLook at error log for detailed input information.\n");
		}
	}



	/**
	 * Place a call to $number connecting first to $fromNumber.
	 * @param $number The 10-digit phone number to call (formatted with parens and hyphens or none).
	 * @param $fromNumber The 10-digit number on your account to connect the call (no hyphens or spaces).
	 * @param $phoneType (mobile, work, home) The type of phone the $fromNumber is. The call will not be connected without this value.
	 */
	public function callNumber($number, $from_number, $phone_type = 'mobile') {
		$types = array(
			'mobile' => 2,
			'work' => 3,
			'home' => 1
		);

		// Make sure phone type is set properly.
		if(!array_key_exists($phone_type, $types))
			throw new Exception('Phone type must be mobile, work, or home');

		// Login to the service if not already done.
		$this->_logIn();

		// Send HTTP POST request.
		curl_setopt($this->_ch, CURLOPT_URL, 'https://www.google.com/voice/call/connect/');
		curl_setopt($this->_ch, CURLOPT_POST, TRUE);
		curl_setopt($this->_ch, CURLOPT_POSTFIELDS, array(
			'_rnr_se' => $this->_rnr_se,
			'forwardingNumber' => '+1'.$from_number,
			'outgoingNumber' => $number,
			'phoneType' => $types[$phone_type],
			'remember' => 0,
			'subscriberNumber' => 'undefined'
			));
		curl_exec($this->_ch);
	}



	/**
	 * Cancel a call to $number connecting first to $fromNumber.
	 * @param $number The 10-digit phone number to call (formatted with parens and hyphens or none).
	 * @param $fromNumber The 10-digit number on your account to connect the call (no hyphens or spaces).
	 * @param $phoneType (mobile, work, home) The type of phone the $fromNumber is. The call will not be connected without this value.
	 */
	public function cancelCall($number, $from_number, $phone_type = 'mobile') {
		$types = array(
			'mobile' => 2,
			'work' => 3,
			'home' => 1
		);

		// Make sure phone type is set properly.
		if(!array_key_exists($phone_type, $types))
			throw new Exception('Phone type must be mobile, work, or home');

		// Login to the service if not already done.
		$this->_logIn();

		// Send HTTP POST request.
		curl_setopt($this->_ch, CURLOPT_URL, 'https://www.google.com/voice/call/cancel/');
		curl_setopt($this->_ch, CURLOPT_POST, TRUE);
		curl_setopt($this->_ch, CURLOPT_POSTFIELDS, array(
			'_rnr_se' => $this->_rnr_se,
			'forwardingNumber' => '+1'.$from_number,
			'outgoingNumber' => $number,
			'phoneType' => $types[$phone_type],
			'remember' => 0,
			'subscriberNumber' => 'undefined'
			));
		curl_exec($this->_ch);
	}



	/**
	 * Send an SMS to $number containing $message.
	 * @param $number The 10-digit phone number to send the message to (formatted with parens and hyphens or none).
	 * @param $message The message to send within the SMS.
	 */
	public function sendSMS($number, $message) {
		// Login to the service if not already done.
		$this->_logIn();

		// Send HTTP POST request.
		curl_setopt($this->_ch, CURLOPT_URL, 'https://www.google.com/voice/sms/send/');
		curl_setopt($this->_ch, CURLOPT_POST, TRUE);
		curl_setopt($this->_ch, CURLOPT_POSTFIELDS, array(
			'_rnr_se' => $this->_rnr_se,
			'phoneNumber' => '+1'.$number,
			'text' => $message
			));
		curl_exec($this->_ch);
	}



	public function getNewSMS()
	{
		$this->_logIn();
		curl_setopt($this->_ch, CURLOPT_URL, 'https://www.google.com/voice/inbox/recent/sms/');
		curl_setopt($this->_ch, CURLOPT_POST, FALSE);
		curl_setopt($this->_ch, CURLOPT_RETURNTRANSFER, TRUE);
		$xml = curl_exec($this->_ch);

		$dom = new DOMDocument();

		// load the "wrapper" xml (contains two elements, json and html)
		$dom->loadXML($xml);
		$json = $dom->documentElement->getElementsByTagName("json")->item(0)->nodeValue;
		$json = json_decode($json);

		// now make a dom parser which can parse the contents of the HTML tag
		$html = $dom->documentElement->getElementsByTagName("html")->item(0)->nodeValue;
		// replace all "&" with "&amp;" so it can be parsed
		$html = str_replace("&", "&amp;", $html);
		$dom->loadHTML($html);
		$xpath = new DOMXPath($dom);

		$results = array();

		foreach( $json->messages as $mid=>$convo )
		{
			$elements = $xpath->query("//div[@id='$mid']//div[@class='gc-message-sms-row']");
			if(!is_null($elements))
			{
				if( in_array('unread', $convo->labels) )
				{
					foreach($elements as $i=>$element)
					{
						$XMsgFrom = $xpath->query("span[@class='gc-message-sms-from']", $element);
						$msgFrom = '';
						foreach($XMsgFrom as $m)
							$msgFrom = trim($m->nodeValue);

						if( $msgFrom != "Me:" )
						{
							$XMsgText = $xpath->query("span[@class='gc-message-sms-text']", $element);
							$msgText = '';
							foreach($XMsgText as $m)
								$msgText = trim($m->nodeValue);

							$XMsgTime = $xpath->query("span[@class='gc-message-sms-time']", $element);
							$msgTime = '';
							foreach($XMsgTime as $m)
								$msgTime = trim($m->nodeValue);

							$results[] = array('msgID'=>$mid, 'phoneNumber'=>$convo->phoneNumber, 'message'=>$msgText, 'date'=>date('Y-m-d H:i:s', strtotime(date('m/d/Y ',intval($convo->startTime/1000)).$msgTime)));
						}
					}
				}
				else
				{
					//echo "This message is not unread\n";
				}
			}
			else
			{
				//echo "gc-message-sms-row query failed\n";
			}
		}

		return $results;
	}



	public function markSMSRead($msgID)
	{
		$this->_logIn();

		curl_setopt($this->_ch, CURLOPT_URL, 'https://www.google.com/voice/inbox/mark/');
		curl_setopt($this->_ch, CURLOPT_POST, TRUE);
		curl_setopt($this->_ch, CURLOPT_POSTFIELDS, array(
			'_rnr_se'=>$this->_rnr_se,
			'messages'=>$msgID,
			'read'=>1
			));
		curl_exec($this->_ch);
	}



	public function markSMSDeleted($msgID)
	{
		$this->_logIn();

		curl_setopt($this->_ch, CURLOPT_URL, 'https://www.google.com/voice/inbox/deleteMessages/');
		curl_setopt($this->_ch, CURLOPT_POST, TRUE);
		curl_setopt($this->_ch, CURLOPT_POSTFIELDS, array(
                        '_rnr_se'=>$this->_rnr_se,
                        'messages'=>$msgID,
                        'trash'=>1
                        ));
		curl_exec($this->_ch);
	}



	/**
	 * Add a note to a message in a Google Voice Inbox or Voicemail.
	 * @param $message_id The id of the message to update.
	 * @param $note The message to send within the SMS.
	 */
	public function addNote($message_id, $note) {
		// Login to the service if not already done.
		$this->_logIn();

		// Send HTTP POST request.
		curl_setopt($this->_ch, CURLOPT_URL, 'https://www.google.com/voice/inbox/savenote/');
		curl_setopt($this->_ch, CURLOPT_POST, TRUE);
		curl_setopt($this->_ch, CURLOPT_POSTFIELDS, array(
			'_rnr_se' => $this->_rnr_se,
			'id' => $message_id,
			'note' => $note
			));
		curl_exec($this->_ch);
	}



	/**
	 * Removes a note from a message in a Google Voice Inbox or Voicemail.
	 * @param $message_id The id of the message to update.
	 */
	public function removeNote($message_id, $note) {
		// Login to the service if not already done.
		$this->_logIn();

		// Send HTTP POST request.
		curl_setopt($this->_ch, CURLOPT_URL, 'https://www.google.com/voice/inbox/deletenote/');
		curl_setopt($this->_ch, CURLOPT_POST, TRUE);
		curl_setopt($this->_ch, CURLOPT_POSTFIELDS, array(
			'_rnr_se' => $this->_rnr_se,
			'id' => $message_id,
			));
		curl_exec($this->_ch);
	}



	/**
	 * Get all of the unread SMS messages in a Google Voice inbox.
	 */
	public function getUnreadSMS() {
		// Login to the service if not already done.
		$this->_logIn();

		// Send HTTP POST request.
		curl_setopt($this->_ch, CURLOPT_URL, 'https://www.google.com/voice/inbox/recent/sms/');
		curl_setopt($this->_ch, CURLOPT_POST, FALSE);
		curl_setopt($this->_ch, CURLOPT_RETURNTRANSFER, TRUE);
		$xml = curl_exec($this->_ch);

		// Load the "wrapper" xml (contains two elements, json and html).
		$dom = new DOMDocument();
		$dom->loadXML($xml);
		$json = $dom->documentElement->getElementsByTagName("json")->item(0)->nodeValue;
		$json = json_decode($json);

		// Loop through all of the messages.
		$results = array();
		foreach($json->messages as $mid=>$convo) {
			if($convo->isRead == FALSE) {
				$results[] = $convo;
			}
		}
		return $results;
	}



	/**
	 * Get all of the read SMS messages in a Google Voice inbox.
	 */
	public function getReadSMS() {
		// Login to the service if not already done.
		$this->_logIn();

		// Send HTTP POST request.
		curl_setopt($this->_ch, CURLOPT_URL, 'https://www.google.com/voice/inbox/recent/sms/');
		curl_setopt($this->_ch, CURLOPT_POST, FALSE);
		curl_setopt($this->_ch, CURLOPT_RETURNTRANSFER, TRUE);
		$xml = curl_exec($this->_ch);

		// Load the "wrapper" xml (contains two elements, json and html).
		$dom = new DOMDocument();
		$dom->loadXML($xml);
		$json = $dom->documentElement->getElementsByTagName("json")->item(0)->nodeValue;
		$json = json_decode($json);

		// Loop through all of the messages.
		$results = array();
		foreach($json->messages as $mid=>$convo) {
			if($convo->isRead == TRUE) {
				$results[] = $convo;
			}
		}
		return $results;
	}



	/**
	 * Get all of the unread SMS messages from a Google Voice Voicemail.
	 */
	public function getUnreadVoicemail() {
		// Login to the service if not already done.
		$this->_logIn();

		// Send HTTP POST request.
		curl_setopt($this->_ch, CURLOPT_URL, 'https://www.google.com/voice/inbox/recent/voicemail/');
		curl_setopt($this->_ch, CURLOPT_POST, FALSE);
		curl_setopt($this->_ch, CURLOPT_RETURNTRANSFER, TRUE);
		$xml = curl_exec($this->_ch);

		// Load the "wrapper" xml (contains two elements, json and html)
		$dom = new DOMDocument();
		$dom->loadXML($xml);
		$json = $dom->documentElement->getElementsByTagName("json")->item(0)->nodeValue;
		$json = json_decode($json);

		// Loop through all of the messages.
		$results = array();
		foreach($json->messages as $mid=>$convo) {
			if($convo->isRead == FALSE) {
				$results[] = $convo;
			}
		}
		return $results;
	}



	/**
	 * Get all of the unread SMS messages from a Google Voice Voicemail.
	 */
	public function getReadVoicemail() {
		// Login to the service if not already done.
		$this->_logIn();

		// Send HTTP POST request.
		curl_setopt($this->_ch, CURLOPT_URL, 'https://www.google.com/voice/inbox/recent/voicemail/');
		curl_setopt($this->_ch, CURLOPT_POST, FALSE);
		curl_setopt($this->_ch, CURLOPT_RETURNTRANSFER, TRUE);
		$xml = curl_exec($this->_ch);

		// load the "wrapper" xml (contains two elements, json and html)
		$dom = new DOMDocument();
		$dom->loadXML($xml);
		$json = $dom->documentElement->getElementsByTagName("json")->item(0)->nodeValue;
		$json = json_decode($json);

		// Loop through all of the messages.
		$results = array();
		foreach( $json->messages as $mid=>$convo ) {
			if( $convo->isRead == TRUE ) {
				$results[] = $convo;
			}
		}
		return $results;
	}



	/**
	 * Get MP3 of a Google Voice Voicemail.
	 */
	public function getVoicemailMP3($message_id) {
		// Login to the service if not already done.
		$this->_logIn();

		// Send HTTP POST request.
		curl_setopt($this->_ch, CURLOPT_URL, "https://www.google.com/voice/media/send_voicemail/$message_id/");
		curl_setopt($this->_ch, CURLOPT_POST, FALSE);
		curl_setopt($this->_ch, CURLOPT_RETURNTRANSFER, TRUE);
		$results = curl_exec($this->_ch);

		return $results;
	}



	/**
	 * Mark a message in a Google Voice Inbox or Voicemail as read.
	 * @param $message_id The id of the message to update.
	 * @param $note The message to send within the SMS.
	 */
	public function markMessageRead($message_id) {
		// Login to the service if not already done.
		$this->_logIn();

		// Send HTTP POST request.
		curl_setopt($this->_ch, CURLOPT_URL, 'https://www.google.com/voice/inbox/mark/');
		curl_setopt($this->_ch, CURLOPT_POST, TRUE);
		curl_setopt($this->_ch, CURLOPT_POSTFIELDS, array(
			'_rnr_se' => $this->_rnr_se,
			'messages' => $message_id,
			'read' => '1'
			));
		curl_exec($this->_ch);
	}



	/**
	 * Mark a message in a Google Voice Inbox or Voicemail as unread.
	 * @param $message_id The id of the message to update.
	 * @param $note The message to send within the SMS.
	 */
	public function markMessageUnread($message_id) {
		// Login to the service if not already done.
		$this->_logIn();

		// Send HTTP POST request.
		curl_setopt($this->_ch, CURLOPT_URL, 'https://www.google.com/voice/inbox/mark/');
		curl_setopt($this->_ch, CURLOPT_POST, TRUE);
		curl_setopt($this->_ch, CURLOPT_POSTFIELDS, array(
			'_rnr_se' => $this->_rnr_se,
			'messages' => $message_id,
			'read' => '0'
			));
		curl_exec($this->_ch);
	}



	/**
	 * Delete a message or conversation.
	 * @param $message_id The ID of the conversation to delete.
	 */
	public function deleteMessage($message_id) {
		$this->_logIn();

		curl_setopt($this->_ch, CURLOPT_URL, 'https://www.google.com/voice/inbox/deleteMessages/');
		curl_setopt($this->_ch, CURLOPT_POST, TRUE);
		curl_setopt($this->_ch, CURLOPT_POSTFIELDS, array(
			'_rnr_se' => $this->_rnr_se,
			'messages' => $message_id,
			'trash' => 1
		));

		curl_exec($this->_ch);
	}



	public function dom_dump($obj) {
		if ($classname = get_class($obj)) {
			$retval = "Instance of $classname, node list: \n";
			switch (TRUE) {
				case ($obj instanceof DOMDocument):
					$retval .= "XPath: {$obj->getNodePath()}\n".$obj->saveXML($obj);
					break;
				case ($obj instanceof DOMElement):
					$retval .= "XPath: {$obj->getNodePath()}\n".$obj->ownerDocument->saveXML($obj);
					break;
				case ($obj instanceof DOMAttr):
					$retval .= "XPath: {$obj->getNodePath()}\n".$obj->ownerDocument->saveXML($obj);
					break;
				case ($obj instanceof DOMNodeList):
					for ($i = 0; $i < $obj->length; $i++) {
						$retval .= "Item #$i, XPath: {$obj->item($i)->getNodePath()}\n"."{$obj->item($i)->ownerDocument->saveXML($obj->item($i))}\n";
					}
					break;
				default:
					return "Instance of unknown class";
			}
		}
		else {
			return 'no elements...';
		}
		return htmlspecialchars($retval);
	}

	/**
	 * Source from http://www.binarytides.com/php-get-name-and-value-of-all-input-tags-on-a-page-with-domdocument/
	 * Generic function to fetch all input tags (name and value) on a page
	 * Useful when writing automatic login bots/scrapers
	 */
	private function dom_get_input_tags($html)
	{
	    $post_data = array();

	    // a new dom object
	    $dom = new DomDocument;

	    //load the html into the object
	    @$dom->loadHTML($html);  //@suppresses warnings
	    //discard white space
	    $dom->preserveWhiteSpace = FALSE;

	    //all input tags as a list
	    $input_tags = $dom->getElementsByTagName('input');

	    //get all rows from the table
	    for ($i = 0; $i < $input_tags->length; $i++)
	    {
	        if( is_object($input_tags->item($i)) )
	        {
	            $name = $value = '';
	            $name_o = $input_tags->item($i)->attributes->getNamedItem('name');
	            if(is_object($name_o))
	            {
	                $name = $name_o->value;

	                $value_o = $input_tags->item($i)->attributes->getNamedItem('value');
	                if(is_object($value_o))
	                {
	                    $value = $input_tags->item($i)->attributes->getNamedItem('value')->value;
	                }

	                $post_data[$name] = $value;
	            }
	        }
	    }

	    return $post_data;
	}

}

?>
