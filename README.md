to-swift-mime-parser
====================

Parse a generic mail stream, and convert it to a [SwiftMailer](https://github.com/swiftmailer/swiftmailer) Message object


[![Build Status](https://travis-ci.org/goetas/to-swift-mime-parser.svg?branch=master)](https://travis-ci.org/goetas/to-swift-mime-parser)
[![Code Coverage](https://scrutinizer-ci.com/g/goetas/to-swift-mime-parser/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/goetas/to-swift-mime-parser/?branch=master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/goetas/to-swift-mime-parser/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/goetas/to-swift-mime-parser/?branch=master)


Installing (composer)
--------------------

```bash
composer requre goetas/to-swift-mime-parser
```


Usage
--------------------

```php
<?php

$parser = new \Goetas\Mail\ToSwiftMailParser\MimeParser();

// read a mail message saved into eml format (or similar)
$inputStream = fopen('mail.eml', 'rb');

$mail = $parser->parseStream($inputStream); // now $mail is a \Swift_Message  object

// edit the email
$mail->setFrom("me@you.it");
$mail->setTo("me@you.it");
$mail->setSubject("New Subject");


// optionally loop through mail parts (and edit it!)
// $mail->getChildren();

// send a new mail
$mailer->send($mail);





```
