<?php
/**
 * Created by IntelliJ IDEA.
 * User: jaco
 * Date: 2018/10/26
 * Time: 9:06 AM
 */

namespace Goetas\Mail\ToSwiftMailParser\Tests;
use Goetas\Mail\ToSwiftMailParser\MimeParser;
use PHPUnit\Framework\TestCase;

class MimParserBug14Test extends TestCase
{
    /** @var MimeParser */
    protected $parser;

    public function setUp()
    {
        error_reporting(E_ALL);
        $this->parser = new MimeParser();
        \Swift_Preferences::getInstance()->setCharset('utf-8');
    }

    public function testEncodingAttachmentWhenTextandHtmlWithEmbeddedAttachment(){
        $swift_message = new \Swift_Message();
        $swift_message->setCharset('utf-8');
        $swift_message->setSubject('test subject');
        $swift_message->addPart('plain part', 'text/plain');

        $image = new \Swift_Image('<image data>', 'image.gif', 'image/gif');
        $cid = $swift_message->embed($image);
        $swift_message->setBody('<img src="'.$cid.'" />', 'text/html');
        $swift_message->setTo(['user@domain.tld' => 'User']);
        $swift_message->setFrom(['other@domain.tld' => 'Other']);
        $swift_message->setSender(['other@domain.tld' => 'Other']);

        $swift_message->attach(
            new \Swift_Attachment('sample text','attachment.txt','text/plain')
        );

        $parsed_message=$this->parser->parseString($swift_message->toString(),true);

        $this->assertEquals("multipart/mixed", $parsed_message->getContentType());
        $parsed_children = $parsed_message->getChildren();
        $this->assertCount(2, $parsed_children);
        $this->assertEquals("multipart/alternative", $parsed_children[0]->getContentType());
        $this->assertEquals("text/plain", $parsed_children[1]->getContentType());
        $this->assertEquals('sample text', $parsed_children[1]->getBody());

        $child1_children=$parsed_children[0]->getChildren();
        $this->assertCount(2, $child1_children);
        $this->assertEquals("text/plain", $child1_children[0]->getContentType());
        $this->assertEquals('plain part', $child1_children[0]->getBody());

        $this->assertEquals("multipart/related", $child1_children[1]->getContentType());
        $child1_child1_children= $child1_children[1]->getChildren();
        $this->assertCount(2, $child1_child1_children);
        $this->assertEquals("text/html", $child1_child1_children[0]->getContentType());
        $this->assertRegExp('<img.*?/>', $child1_child1_children[0]->getBody());
        $this->assertEquals("image/gif", $child1_child1_children[1]->getContentType());

    }

}