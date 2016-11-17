<?php
namespace Goetas\Mail\ToSwiftMailParser\Tests;

use Goetas\Mail\ToSwiftMailParser\MimeParser;

class BasicTest extends \PHPUnit_Framework_TestCase
{

    protected function assertionsTest1($mail)
    {
        $this->assertEquals(['john@example.com' => 'John Smith'], $mail->getFrom());
        $this->assertEquals(['mark@example.com' => 'Mark Smith'], $mail->getTo());
        $this->assertEquals(['anna@example.com' => 'Anna Smith'], $mail->getBcc());
        $this->assertEquals(['luis@example.com' => 'Luis Smith'], $mail->getCc());
        $this->assertEquals("Проверка", $mail->getSubject());

        $this->assertEquals("multipart/mixed", $mail->getContentType());

        $children = $mail->getChildren();
        $this->assertCount(2, $children);

        $this->assertEquals("this is the body text\n\n", $children[0]->getBody());
        $this->assertEquals("this is the attachment text\n\n", $children[1]->getBody());

        $this->assertEquals("text/plain", $children[0]->getContentType());
        $this->assertEquals("text/plain", $children[1]->getContentType());
    }

    public function testParseString()
    {
        $parser = new MimeParser();

        // read a mail message saved into eml format (or similar)
        $inputStream = file_get_contents(__DIR__ . '/res/test1.txt');

        $mail = $parser->parseString($inputStream, true); // now $mail is a \Swift_Message  object

        $this->assertionsTest1($mail);

    }

    public function testParseStream()
    {
        $parser = new MimeParser();

        // read a mail message saved into eml format (or similar)
        $inputStream = fopen(__DIR__ . '/res/test1.txt', 'rb');

        $mail = $parser->parseStream($inputStream, true); // now $mail is a \Swift_Message  object

        $this->assertionsTest1($mail);
    }

    public function testParseFile()
    {
        $parser = new MimeParser();

        // read a mail message saved into eml format (or similar)
        $mail = $parser->parseFile(__DIR__ . '/res/test1.txt', true); // now $mail is a \Swift_Message  object

        $this->assertionsTest1($mail);
    }

    public function testParse2()
    {
        $parser = new MimeParser();

        // read a mail message saved into eml format (or similar)
        $inputStream = fopen(__DIR__ . '/res/test2.txt', 'rb');

        $mail = $parser->parseStream($inputStream, true); // now $mail is a \Swift_Message  object

        $this->assertEquals(['john@example.com' => 'John Smith'], $mail->getFrom());
        $this->assertEquals("My simple message", $mail->getSubject());
        $this->assertEquals("text/plain", $mail->getContentType());
        $this->assertEquals("this is the body text\n", $mail->getBody());
        $this->assertEquals("john@example.com", $mail->getReturnPath());

        $this->assertCount(0, $mail->getChildren());
    }

    public function testParse3()
    {
        $parser = new MimeParser();

        // read a mail message saved into eml format (or similar)
        $inputStream = fopen(__DIR__ . '/res/test3.txt', 'rb');

        $mail = $parser->parseStream($inputStream, true); // now $mail is a \Swift_Message  object

        $this->assertEquals(['john@example.com' => 'John Smith'], $mail->getFrom());
        $this->assertEquals("My simple message", $mail->getSubject());
        $this->assertEquals("text/plain", $mail->getContentType());
        $this->assertEquals("this is the body text", $mail->getBody());

        $this->assertCount(0, $mail->getChildren());
    }


}


