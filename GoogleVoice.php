<?php

class GoogleVoice
{
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

	public function __construct($login, $pass)
	{
		$this->_login = $login;
		$this->_pass = $pass;

		$this->_cookieFile = '/tmp/gvCookies.txt';

		$this->_ch = curl_init();
		curl_setopt($this->_ch, CURLOPT_COOKIEJAR, $this->_cookieFile);
		curl_setopt($this->_ch, CURLOPT_FOLLOWLOCATION, TRUE);
		curl_setopt($this->_ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($this->_ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 6.0)");
	}
	
	private function _logIn()
	{
		global $conf;

		if( $this->_loggedIn )
			return TRUE;
		
		curl_setopt($this->_ch, CURLOPT_URL, 'https://www.google.com/accounts/ServiceLoginAuth');
		curl_setopt($this->_ch, CURLOPT_POST, TRUE);
		curl_setopt($this->_ch, CURLOPT_POSTFIELDS, array(
			'Email'=>$this->_login,
			'Passwd'=>$this->_pass,
			'continue'=>'https://www.google.com/voice/account/signin'
			));
	
		$html = curl_exec($this->_ch);
		if( preg_match('/name="_rnr_se".*?value="(.*?)"/', $html, $match) )
		{
			$this->_rnr_se = $match[1];
		}
		else
		{
			throw new Exception("Could not log in to Google Voice");
		}
	}

	/**
	 * Place a call to $number connecting first to $fromNumber
	 */
	public function callNumber($number, $fromNumber)
	{
		$this->_logIn();
		
		curl_setopt($this->_ch, CURLOPT_URL, 'https://www.google.com/voice/call/connect/');
		curl_setopt($this->_ch, CURLOPT_POST, TRUE);
		curl_setopt($this->_ch, CURLOPT_POSTFIELDS, array(
			'_rnr_se'=>$this->_rnr_se,
			'forwardingNumber'=>$fromNumber,
			'outgoingNumber'=>$number,
			'remember'=>0,
			'subscriberNumber'=>'undefined'
			));
		curl_exec($this->_ch);
	}

	public function sendSMS($number, $message)
	{
		$this->_logIn();

		curl_setopt($this->_ch, CURLOPT_URL, 'https://www.google.com/voice/sms/send/');
		curl_setopt($this->_ch, CURLOPT_POST, TRUE);
		curl_setopt($this->_ch, CURLOPT_POSTFIELDS, array(
			'_rnr_se'=>$this->_rnr_se,
			'phoneNumber'=>$number,
			'text'=>$message
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
					//$retval .= $obj->ownerDocument->saveXML($obj);
					break;
				case ($obj instanceof DOMNodeList):
					for ($i = 0; $i < $obj->length; $i++) {
						$retval .= "Item #$i, XPath: {$obj->item($i)->getNodePath()}\n"."{$obj->item($i)->ownerDocument->saveXML($obj->item($i))}\n";
					}
					break;
				default:
					return "Instance of unknown class";
			}
		} else {
			return 'no elements...';
		}
		return htmlspecialchars($retval);
	}
}

?>
