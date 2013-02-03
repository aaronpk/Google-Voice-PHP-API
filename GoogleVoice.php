<?php

class GoogleVoice {
	// Our credentials
	private $_login;
	private $_pass;
	private $_rnr_se; // some crazy google thing that we need later

	// Our private curl handle
	private $_ch;

	// The location of our cookies
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
		curl_setopt($this->_ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 6.0)");
	}
	
	private function _logIn() {
		global $conf;

		if($this->_loggedIn)
			return TRUE;

		// fetch the login page
		curl_setopt($this->_ch, CURLOPT_URL, 'https://accounts.google.com/ServiceLogin?service=grandcentral&passive=1209600&continue=https://www.google.com/voice&followup=https://www.google.com/voice&ltmpl=open');
		$html = curl_exec($this->_ch);

		// Grab GALX string.
		if(preg_match('/name="GALX"\s*value="([^"]+)"/', $html, $match))
			$GALX = $match[1];
		else
			throw new Exception('Could not parse for GALX token');

		curl_setopt($this->_ch, CURLOPT_URL, 'https://accounts.google.com/ServiceLoginAuth');
		curl_setopt($this->_ch, CURLOPT_POST, TRUE);
		curl_setopt($this->_ch, CURLOPT_POSTFIELDS, array(
			'Email' => $this->_login,
			'GALX' => $GALX,
			'Passwd' => $this->_pass,
			'continue' => 'https://www.google.com/voice',
			'followup' => 'https://www.google.com/voice',
			'service' => 'grandcentral',
			'signIn' => 'Sign in'));
		$html = curl_exec($this->_ch);

		// Test if login successful.
		if(preg_match('/name="_rnr_se" (.*) value="(.*)"/', $html, $matches)) {
			$this->_rnr_se = $matches[2];
			$this->_loggedIn = TRUE;
		}
		else {
			throw new Exception("Could not log in to Google Voice with username: " . $this->_login);
		}
	}

	/**
	 * Place a call to $number connecting first to $fromNumber
	 * @param $number The 10-digit phone number to call (formatted with parens and hyphens or none)
	 * @param $fromNumber The 10-digit number on your account to connect the call (no hyphens or spaces)
	 * @param $phoneType (mobile, work, home, gizmo) The type of phone the $fromNumber is. The call will not be connected without this value. 
	 */
	public function callNumber($number, $fromNumber, $phoneType='mobile') {
		$types = array(
			'mobile' => 2,
			'work' => 3,
			'home' => 1,
			'gizmo' => 7
		);
	
		if(!array_key_exists($phoneType, $types))
			throw new Exception('Phone type must be mobile, work, home or gizmo');
		
		$this->_logIn();
		
		curl_setopt($this->_ch, CURLOPT_URL, 'https://www.google.com/voice/call/connect/');
		curl_setopt($this->_ch, CURLOPT_POST, TRUE);
		curl_setopt($this->_ch, CURLOPT_POSTFIELDS, array(
			'_rnr_se' => $this->_rnr_se,
			'forwardingNumber' => '+1'.$fromNumber,
			'outgoingNumber' => $number,
			'phoneType' => $types[$phoneType],
			'remember' => 0,
			'subscriberNumber' => 'undefined'
			));
		curl_exec($this->_ch);
	}

	public function sendSMS($number, $message) {
		$this->_logIn();

		curl_setopt($this->_ch, CURLOPT_URL, 'https://www.google.com/voice/sms/send/');
		curl_setopt($this->_ch, CURLOPT_POST, TRUE);
		curl_setopt($this->_ch, CURLOPT_POSTFIELDS, array(
			'id' => '',
			'_rnr_se' => $this->_rnr_se,
			'phoneNumber'=>'+1' . $number,
			'text'=>$message
			));
		curl_exec($this->_ch);
	}
	
	public function getUnreadSMS() {
		$this->_logIn();
		curl_setopt($this->_ch, CURLOPT_URL, 'https://www.google.com/voice/inbox/recent/sms/');
		curl_setopt($this->_ch, CURLOPT_POST, FALSE);
		curl_setopt($this->_ch, CURLOPT_RETURNTRANSFER, TRUE);
		$xml = curl_exec($this->_ch);

		// load the "wrapper" xml (contains two elements, json and html)
		$dom = new DOMDocument();
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
		foreach($json->messages as $mid=>$convo) {
			$elements = $xpath->query("//div[@id='$mid']//div[@class='gc-message-sms-row']");
			if(!is_null($elements)) {
				if(in_array('unread', $convo->labels)) {
					foreach($elements as $i=>$element) {
						$XMsgFrom = $xpath->query("span[@class='gc-message-sms-from']", $element);
						$msgFrom = '';
						foreach($XMsgFrom as $m)
							$msgFrom = trim($m->nodeValue);

						if($msgFrom != "Me:") {
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
			}
		}
		return $results;
	}

	public function getReadSMS() {
		$this->_logIn();
		curl_setopt($this->_ch, CURLOPT_URL, 'https://www.google.com/voice/inbox/recent/sms/');
		curl_setopt($this->_ch, CURLOPT_POST, FALSE);
		curl_setopt($this->_ch, CURLOPT_RETURNTRANSFER, TRUE);
		$xml = curl_exec($this->_ch);

		// load the "wrapper" xml (contains two elements, json and html)
		$dom = new DOMDocument();
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
		foreach($json->messages as $mid=>$convo) {
			$elements = $xpath->query("//div[@id='$mid']//div[@class='gc-message-sms-row']");
			if(!is_null($elements)) {
				if(!in_array('unread', $convo->labels)) {
					foreach($elements as $i=>$element) {
						$XMsgFrom = $xpath->query("span[@class='gc-message-sms-from']", $element);
						$msgFrom = '';
						foreach($XMsgFrom as $m)
							$msgFrom = trim($m->nodeValue);

						if($msgFrom != "Me:") {
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
			}
		}
		return $results;
	}

	public function getUnreadVoicemail() {
		$this->_logIn();

		curl_setopt($this->_ch, CURLOPT_URL, 'https://www.google.com/voice/inbox/recent/voicemail/');
		curl_setopt($this->_ch, CURLOPT_POST, FALSE);
		curl_setopt($this->_ch, CURLOPT_RETURNTRANSFER, TRUE);
		$xml = curl_exec($this->_ch);

		// load the "wrapper" xml (contains two elements, json and html)
		$dom = new DOMDocument();
		$dom->loadXML($xml);
		$json = $dom->documentElement->getElementsByTagName("json")->item(0)->nodeValue;
		$json = json_decode($json);
		
		$results = array();
		foreach( $json->messages as $mid=>$convo ) {
			if( $convo->isRead == false ) {
				$results[] = array('msgID'=>$mid, 'phoneNumber'=>$convo->phoneNumber, 'message'=>$convo->messageText, 'date'=>date('Y-m-d H:i:s', intval($convo->startTime/1000)));
			}
		}
		return $results;
	}

	public function getReadVoicemail() {
		$this->_logIn();
		curl_setopt($this->_ch, CURLOPT_URL, 'https://www.google.com/voice/inbox/recent/voicemail/');
		curl_setopt($this->_ch, CURLOPT_POST, FALSE);
		curl_setopt($this->_ch, CURLOPT_RETURNTRANSFER, TRUE);
		$xml = curl_exec($this->_ch);

		// load the "wrapper" xml (contains two elements, json and html)
		$dom = new DOMDocument();
		$dom->loadXML($xml);
		$json = $dom->documentElement->getElementsByTagName("json")->item(0)->nodeValue;
		$json = json_decode($json);
		
		$results = array();
		foreach( $json->messages as $mid=>$convo ) {
			if( $convo->isRead == true ) {
				$results[] = array('msgID'=>$mid, 'phoneNumber'=>$convo->phoneNumber, 'message'=>$convo->messageText, 'date'=>date('Y-m-d H:i:s', intval($convo->startTime/1000)));
			}
		}
		return $results;
	}

	public function markMessageRead($msgID) {
		$this->_logIn();

		curl_setopt($this->_ch, CURLOPT_URL, 'https://www.google.com/voice/inbox/mark/');
		curl_setopt($this->_ch, CURLOPT_POST, TRUE);
		curl_setopt($this->_ch, CURLOPT_POSTFIELDS, array(
			'_rnr_se' => $this->_rnr_se,
			'messages' => $msgID,
			'read' => '1'
			));
		curl_exec($this->_ch);
	}

	public function markMessageUnread($msgID) {
		$this->_logIn();

		curl_setopt($this->_ch, CURLOPT_URL, 'https://www.google.com/voice/inbox/mark/');
		curl_setopt($this->_ch, CURLOPT_POST, TRUE);
		curl_setopt($this->_ch, CURLOPT_POSTFIELDS, array(
			'_rnr_se' => $this->_rnr_se,
			'messages' => $msgID,
			'read' => '0'
			));
		curl_exec($this->_ch);
	}
	
	public function dom_dump($obj) {
		if ($classname = get_class($obj)) {
			$retval = "Instance of $classname, node list: \n";
			switch (true) {
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
	
	private function _unhtmlentities($string) {
		// replace numeric entities
		$string = preg_replace('~&#x([0-9a-f]+);~ei', 'chr(hexdec("\\1"))', $string);
		$string = preg_replace('~&#([0-9]+);~e', 'chr("\\1")', $string);
		// replace literal entities
		$string = html_entity_decode($string);
		return $string;
	}
}

?>
