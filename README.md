[![No Maintenance Intended](http://unmaintained.tech/badge.svg)](http://unmaintained.tech/)

Google Voice PHP API
====================

An API to interact with Google Voice using PHP.

Currently the API can place calls, cancel previously placed calls, send and
receive SMS messages, add a note to or remove a note from a message or voicemail,
mark a message or voicemail as read or unread, and download transcriptions and/or
MP3 files of voicemail. Feel free to implement new functionality and send me your
changes so I can incorporate them into this library!

getUnreadSMS, getReadSMS, getUnreadVoicemail, and getReadVoicemail all return
an array of JSON objects. Each object has the following attributes, example
values included:

	$msg->id = c3716aa447a19c7e2e7347f443dd29091401ae13
	$msg->phoneNumber = +15555555555
	$msg->displayNumber = (555) 555-5555
	$msg->startTime = 1359918736555
	$msg->displayStartDateTime = 2/3/13 5:55 PM
	$msg->displayStartTime = 5:55 PM
	$msg->relativeStartTime = 5 hours ago
	$msg->note = 
	$msg->isRead = true
	$msg->isSpam = false
	$msg->isTrash = false
	$msg->star: = alse
	$msg->messageText = Hello, cellphone.
	$msg->labels = [sms,all]
	$msg->type = 11
	$msg->children = 

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

