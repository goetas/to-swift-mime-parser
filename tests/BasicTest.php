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

        $mail = $parser->parseStream($inputStream, true); // now $mail is a \Swift_Message  object

        $this->assertEquals(['john@example.com' => 'John Smith'], $mail->getFrom());
        $this->assertEquals(['mark@example.com' => 'Mark Smith'], $mail->getTo());
        $this->assertEquals(['anna@example.com' => 'Anna Smith'], $mail->getBcc());
        $this->assertEquals(['luis@example.com' => 'Luis Smith'], $mail->getCc());
        $this->assertEquals("My first message", $mail->getSubject());

        $this->assertEquals("multipart/mixed", $mail->getContentType());

        $children = $mail->getChildren();
        $this->assertCount(2, $children);

        $this->assertEquals("this is the body text\n\n", $children[0]->getBody());
        $this->assertEquals("this is the attachment text\n\n", $children[1]->getBody());

        $this->assertEquals("text/plain", $children[0]->getContentType());
        $this->assertEquals("text/plain", $children[1]->getContentType());
    }
}


