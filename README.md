to-swift-mime-parser
====================

Parse a generic mail stream, and convert it to a SwiftMailer Message

Installing (composer)
--------------------

Add following lines on your **composer.json**
```
"requre":{
    "goetas/to-swift-mime-parser": "1.0.*@dev"
}
```


Usage
--------------------

```php
$parser = new \Goetas\Mail\ToSwiftMailParser\MimeParser\MimeParser();

// read a mail message saved into eml format (or similar)
$inputStream = fopen('mail.eml', 'rb');

$mail = $parser->parseStream($inputStream); // now $mail is a \Swift_Message  object

// edit the email
$mail->setFrom("me@you.it");
$mail->setTo("me@you.it");
$mail->setSubject("New Subject");


// optionaly loop through mail parts (and edit it!)
// $mail->getChildren();

// send a new mail
$mailer->send($mail);

```