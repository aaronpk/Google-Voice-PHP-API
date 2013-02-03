Google Voice PHP API
====================

An API to interact with Google Voice using PHP.

Currently the API can place calls, send and receive SMS messages, and download
transcriptions of voicemail. Feel free to implement new functionality and send
me your changes so I can incorporate them into this library!

getUnreadSMS, getReadSMS, getUnreadVoicemail, and getReadVoicemail all return
an array of JSON objects. Each object has the following attributes, example
values included:

	$jobject->id = c3716aa447a19c7e2e7347f443dd29091401ae13
	$jobject->phoneNumber = +15555555555
	$jobject->displayNumber = (555) 555-5555
	$jobject->startTime = 1359918736555
	$jobject->displayStartDateTime = 2/3/13 5:55 PM
	$jobject->displayStartTime = 5:55 PM
	$jobject->relativeStartTime = 5 hours ago
	$jobject->note = 
	$jobject->isRead = true
	$jobject->isSpam = false
	$jobject->isTrash = false
	$jobject->star: = alse
	$jobject->messageText = Hello, cellphone.
	$jobject->labels = [sms,all]
	$jobject->type = 11
	$jobject->children = 

Note: Receiving SMSs and voicemails is mostly unnecessary via this API since
Google now allows SMSs to be forwarded to an email address. It is  a better
idea to parse those incoming emails with a script.

SMS and Voice Integration
=========================

For better SMS and voice integration with your web app, check out Tropo
at [tropo.com](http://tropo.com). Tropo is free for development, and you will
get better results than using unsupported Google Voice API calls. 

Check out some [sample apps built with Tropo](https://www.tropo.com/docs/scripting/tutorials.htm)

Disclaimer
==========

This code is provided for educational purposes only. This code may stop
working at any time if Google changes their login mechanism or their web
pages. You agree that by downloading this code you agree to use it solely
at your own risk.

License
=======

Copyright 2009 by Aaron Parecki
[http://aaronparecki.com](http://aaronparecki.com)

See LICENSE

