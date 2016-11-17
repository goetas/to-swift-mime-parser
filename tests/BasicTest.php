<?php
namespace Goetas\Mail\ToSwiftMailParser\Tests;

use Goetas\Mail\ToSwiftMailParser\MimeParser;

class BasicTest extends \PHPUnit_Framework_TestCase
{
    public function testParse()
    {
        $parser = new MimeParser();

        // read a mail message saved into eml format (or similar)
        $inputStream = fopen(__DIR__ . '/res/test1.txt', 'rb');

        $mail = $parser->parseStream($inputStream); // now $mail is a \Swift_Message  object

        // edit the email
        //$mail->setFrom("me@you.it");
        //$mail->setTo("me@you.it");
        //$mail->setSubject("New Subject");
    }
}
