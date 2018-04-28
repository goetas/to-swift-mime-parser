<?php

namespace Goetas\Mail\ToSwiftMailParser\Tests;

use Goetas\Mail\ToSwiftMailParser\MimeParser;

class BasicTest extends \PHPUnit_Framework_TestCase
{
    protected $parser;
    public function setUp()
    {
        error_reporting(E_ALL);
        $this->parser = new MimeParser();
    }
    protected function assertionsTest1($mail)
    {
        $this->assertEquals(array('john@example.com' => 'John Smith'), $mail->getFrom());
        $this->assertEquals(array('mark@example.com' => 'Mark Smith'), $mail->getTo());
        $this->assertEquals(array('anna@example.com' => 'Anna Smith'), $mail->getBcc());
        $this->assertEquals(array('luis@example.com' => 'Luis Smith'), $mail->getCc());
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
        // read a mail message saved into eml format (or similar)
        $inputStream = file_get_contents(__DIR__ . '/res/test1.txt');

        $mail = $this->parser->parseString($inputStream, true); // now $mail is a \Swift_Message  object

        $this->assertionsTest1($mail);
    }

    public function testParseStream()
    {
        // read a mail message saved into eml format (or similar)
        $inputStream = fopen(__DIR__ . '/res/test1.txt', 'rb');

        $mail = $this->parser->parseStream($inputStream, true); // now $mail is a \Swift_Message  object

        $this->assertionsTest1($mail);
    }

    public function testParseFile()
    {
        // read a mail message saved into eml format (or similar)
        $mail = $this->parser->parseFile(__DIR__ . '/res/test1.txt', true); // now $mail is a \Swift_Message  object

        $this->assertionsTest1($mail);
    }

    public function testParse2()
    {
        // read a mail message saved into eml format (or similar)
        $inputStream = fopen(__DIR__ . '/res/test2.txt', 'rb');

        $mail = $this->parser->parseStream($inputStream, true); // now $mail is a \Swift_Message  object

        $this->assertEquals(array('john@example.com' => 'John Smith'), $mail->getFrom());
        $this->assertEquals("My simple message", $mail->getSubject());
        $this->assertEquals("text/plain", $mail->getContentType());
        $this->assertEquals("this is the body text\n", $mail->getBody());
        $this->assertEquals("john@example.com", $mail->getReturnPath());

        $this->assertCount(0, $mail->getChildren());
    }

    public function testParse3()
    {
        // read a mail message saved into eml format (or similar)
        $inputStream = fopen(__DIR__ . '/res/test3.txt', 'rb');

        $mail = $this->parser->parseStream($inputStream, true); // now $mail is a \Swift_Message  object

        $this->assertEquals(array('john@example.com' => 'John Smith'), $mail->getFrom());
        $this->assertEquals("My simple message", $mail->getSubject());
        $this->assertEquals("text/plain", $mail->getContentType());
        $this->assertEquals("this is the body text", $mail->getBody());

        $this->assertCount(0, $mail->getChildren());
    }

    /**
     * @expectedException \Goetas\Mail\ToSwiftMailParser\Exception\InvalidMessageFormatException
     * @expectedExceptionMessage The Content-Type header is not well formed, boundary is missing
     */
    public function testParseWrongBoundary()
    {
        // read a mail message saved into eml format (or similar)
        $inputStream = fopen(__DIR__ . '/res/test-wrong-boundary.txt', 'rb');

        $this->parser->parseStream($inputStream, true); // now $mail is a \Swift_Message  object
    }

    public function testParseWrongContent()
    {
        // read a mail message saved into eml format (or similar)
        $inputStream = fopen(__DIR__ . '/res/wrong-boundary.txt', 'rb');

        /**
         * @var $mail  \Swift_Message
         */
        $mail = $this->parser->parseStream($inputStream, true); // now $mail is a \Swift_Message  object

        $this->assertEquals("multipart/mixed", $mail->getContentType());
        $this->assertEquals("My simple message", $mail->getSubject());
        $this->assertEmpty($mail->getChildren());
    }

    public function testPartiallyNotWellFormedContentType()
    {
        // read a mail message saved into eml format (or similar)
        $inputStream = fopen(__DIR__ . '/res/wrong-content-type.txt', 'rb');

        /**
         * @var $mail  \Swift_Message
         */
        $mail = $this->parser->parseStream($inputStream, true); // now $mail is a \Swift_Message  object

        $this->assertionsTest1($mail);
    }

    public function testDifferentHeadersEncoding()
    {
        // read a mail message saved into eml format (or similar)
        $inputStream = fopen(__DIR__ . '/res/test-different-headers-encoding.txt', 'rb');

        $mail = $this->parser->parseStream($inputStream, true); // now $mail is a \Swift_Message  object

        $this->assertEquals(['test@example.com' => 'Táste'], $mail->getTo());
        $this->assertEquals('Táste', $mail->getSubject());
    }

    public function testHeaderWithSemicolon()
    {
        $inputStream = fopen(__DIR__ . '/res/test-header-with-semicolon.txt', 'rb');

        /** @var \Swift_Message $mail */
        $mail = $this->parser->parseStream($inputStream, true);
        $children = $mail->getChildren();
        $this->assertCount(2, $children);
        $this->assertEquals('attachment; filename="test;.txt"', $children[1]->getHeaders()->get('content-disposition')->getFieldBody());
    }
}
