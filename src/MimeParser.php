<?php

namespace Goetas\Mail\ToSwiftMailParser;

use Goetas\Mail\ToSwiftMailParser\Exception\InvalidMessageFormatException;
use Goetas\Mail\ToSwiftMailParser\Mime\ContentDecoder;
use Goetas\Mail\ToSwiftMailParser\Mime\HeaderDecoder;

class MimeParser
{
    private const SWIFT_CONTAINER_CACHE_KEY = 'cache';
    private const SWIFT_CONTAINER_ID_GENERATOR_KEY = 'mime.idgenerator';

    protected $removeHeaders = array("Received", "From", "X-Original-To", "MIME-Version", "Received-SPF", "Delivered-To");
    protected $allowedHeaders = array("return-path", "subject");
    /**
     * @var ContentDecoder
     */
    private $contentDecoder;

    /**
     * @var HeaderDecoder
     */
    private $headerDecoder;

    /**
     * @var \Swift_DependencyContainer
     */
    private $swiftContainer;

    public function __construct(array $allowedHeaders = array(), array $removeHeaders = array())
    {
        $this->contentDecoder = new ContentDecoder ();
        $this->headerDecoder = new HeaderDecoder ();

        $this->allowedHeaders = array_merge($this->allowedHeaders, $allowedHeaders);
        $this->removeHeaders = array_merge($this->removeHeaders, $removeHeaders);
    }

    public function setSwiftDependencyContainer(\Swift_DependencyContainer $swiftContainer)
    {
        $this->swiftContainer = $swiftContainer;
    }

    private function getSwiftDependencyContainer(): \Swift_DependencyContainer
    {
        if ($this->swiftContainer === null) {
            $this->swiftContainer = \Swift_DependencyContainer::getInstance();
        }
        return $this->swiftContainer;
    }

    private function getIdGenertor(): \Swift_IdGenerator
    {
        return $this->getSwiftDependencyContainer()->lookup(self::SWIFT_CONTAINER_ID_GENERATOR_KEY);
    }

    private function getCache(): \Swift_KeyCache
    {
        return $this->getSwiftDependencyContainer()->lookup(self::SWIFT_CONTAINER_CACHE_KEY);
    }

    public function parseFile(string $path, bool $fillHeaders = false, \Swift_Mime_SimpleMimeEntity $message = null): \Swift_Mime_SimpleMimeEntity
    {
        $fp = fopen($path, "rb");
        $message = $this->parseStream($fp, $fillHeaders, $message);
        fclose($fp);
        return $message;
    }

    public function parseString(string $string, bool $fillHeaders = false, \Swift_Mime_SimpleMimeEntity $message = null): \Swift_Mime_SimpleMimeEntity
    {
        $fp = fopen("php://memory", "wb");
        fwrite($fp, $string);
        rewind($fp);
        $message = $this->parseStream($fp, $fillHeaders, $message);
        fclose($fp);
        return $message;
    }

    /**
     * @param resource $stream
     */
    public function parseStream($stream, bool $fillHeaders = false, \Swift_Mime_SimpleMimeEntity $message = null): \Swift_Mime_SimpleMimeEntity
    {
        $partHeaders = $this->extractHeaders($stream);

        $filteredHeaders = $this->filterHeaders($partHeaders);

        $parts = $this->parseParts($stream, $partHeaders);

        if (!$message) {
            $message = new \Swift_Message ();
        }

        $headers = $this->createHeadersSet($filteredHeaders);

        foreach ($headers->getAll() as $name => $header) {
            if ($fillHeaders || in_array(strtolower($header->getFieldName()), $this->allowedHeaders)) {
                $message->getHeaders()->set($header);
            }
        }
        $this->createMessage($parts, $message);

        return $message;
    }

    protected function extractHeaders($stream): array
    {
        $headers = array();
        $hName = null;
        while (!feof($stream)) {
            $row = fgets($stream);
            if ($row == "\r\n" || $row == "\n" || $row == "\r") {
                break;
            }
            if (preg_match('/^([a-z0-9\-]+)\s*:(.*)/i', $row, $mch)) {
                $hName = strtolower($mch[1]);
                if (!in_array($hName, array("content-type", "content-transfer-encoding"))) {
                    $hName = $mch[1];
                }
                $row = $mch[2];
            }
            if (empty($hName)) {
                continue;
            }
            $headers[$hName][] = trim($row);
        }
        foreach ($headers as $header => $values) {
            $headers[$header] = $this->headerDecoder->decode(trim(implode(" ", $values)));
        }
        return $headers;
    }

    private function filterHeaders(array $headers): array
    {
        foreach ($headers as $header => $values) {
            if (in_array(strtolower($header), $this->removeHeaders) && !in_array(strtolower($header), $this->allowedHeaders)) {
                unset ($headers[$header]);
            }
        }
        return $headers;
    }

    protected function parseParts($stream, array $partHeaders): array
    {
        $parts = array();
        $contentType = $this->extractValueHeader($this->getContentType($partHeaders));

        $boundary = null;
        if (stripos($contentType, 'multipart/') !== false) {
            $headerParts = $this->extractHeaderParts($this->getContentType($partHeaders));
            if (empty($headerParts["boundary"])) {
                throw new InvalidMessageFormatException("The Content-Type header is not well formed, boundary is missing");
            }
            $boundary = $headerParts["boundary"];
        }

        try {
            // body
            $this->extractPart($stream, $boundary, $this->getTransferEncoding($partHeaders));
        } catch (Exception\EndOfPartReachedException $e) {
            $parts = array(
                "type" => $contentType,
                "headers" => $partHeaders,
                "body" => $e->getData(),
                "boundary" => $boundary,
                "parts" => array()
            );
        }

        if ($boundary) {
            $childContentType = null;
            while (!feof($stream)) {
                try {
                    $partHeaders = $this->extractHeaders($stream);
                    $childContentType = $this->extractValueHeader($this->getContentType($partHeaders));

                    if (stripos($childContentType, 'multipart/') !== false) {
                        $parts["parts"][] = $this->parseParts($stream, $partHeaders);
                        try {
                            $this->extractPart($stream, $boundary, $this->getTransferEncoding($partHeaders));
                        } catch (Exception\EndOfPartReachedException $e) {
                        }
                    } else {
                        $this->extractPart($stream, $boundary, $this->getTransferEncoding($partHeaders));
                    }
                } catch (Exception\EndOfPartReachedException $e) {
                    $parts["parts"][] = array(
                        "type" => $childContentType,
                        "parent-type" => $contentType,
                        "headers" => $partHeaders,
                        "body" => $e->getData(),
                        "parts" => array()
                    );

                    if ($e instanceof Exception\EndOfMultiPartReachedException) {
                        break;
                    }
                }
            }
        }
        return $parts;
    }

    private function extractValueHeader($header): string
    {
        $pos = stripos($header, ';');
        if ($pos !== false) {
            return substr($header, 0, $pos);
        } else {
            return $header;
        }
    }

    private function getContentType(array $partHeaders): string
    {
        if (array_key_exists('content-type', $partHeaders)) {
            return $partHeaders['content-type'];
        }

        return '';
    }

    private function extractHeaderParts(string $header): array
    {
        if (stripos($header, ';') !== false) {

            $parts = explode(";", $header);
            array_shift($parts);
            $p = array();
            $part = '';
            foreach ($parts as $pv) {
                if (preg_match('/="[^"]+$/', $pv)) {
                    $part = $pv;
                    continue;
                }
                if ($part !== '') {
                    $part .= ';' . $pv;
                    if (preg_match('/="[^"]+$/', $part)) {
                        continue;
                    } else {
                        $pv = $part;
                    }
                }
                if (strpos($pv, '=') === false) {
                    continue;
                }
                list ($k, $v) = explode("=", trim($pv), 2);
                $p[$k] = trim($v, '"');
            }
            return $p;
        } else {
            return array();
        }
    }

    /**
     * @throws Exception\EndOfMultiPartReachedException
     * @throws Exception\EndOfPartReachedException
     */
    protected function extractPart($stream, ?string $boundary, string $encoding): void
    {
        $rows = array();
        while (!feof($stream)) {
            $row = fgets($stream);

            if ($boundary !== null) {
                if (strpos($row, "--$boundary--") === 0) {
                    throw new Exception\EndOfMultiPartReachedException($this->contentDecoder->decode(implode("", $rows), $encoding));
                }
                if (strpos($row, "--$boundary") === 0) {
                    throw new Exception\EndOfPartReachedException($this->contentDecoder->decode(implode("", $rows), $encoding));
                }
            }
            $rows[] = $row;
        }
        throw new Exception\EndOfMultiPartReachedException($this->contentDecoder->decode(implode("", $rows), $encoding));
    }

    private function getTransferEncoding(array $partHeaders): string
    {
        if (array_key_exists('content-transfer-encoding', $partHeaders)) {
            return $partHeaders['content-transfer-encoding'];
        }

        return '';
    }

    protected function createHeadersSet(array $headersRaw): \Swift_Mime_SimpleHeaderSet
    {
        $headers = \Swift_DependencyContainer::getInstance()->lookup('mime.headerset');

        foreach ($headersRaw as $name => $value) {
            switch (strtolower($name)) {
                case "content-type":
                    $parts = $this->extractHeaderParts($value);
                    unset ($parts["boundary"]);
                    $headers->addParameterizedHeader($name, $this->extractValueHeader($value), $parts);
                    break;
                case "return-path":
                    if (preg_match_all('/([a-z][a-z0-9_\-\.]*@[a-z0-9\.\-]*\.[a-z]{2,5})/i', $value, $mch)) {
                        foreach ($mch[0] as $k => $mails) {
                            $headers->addPathHeader($name, $mch[1][$k]);
                        }
                    }
                    break;
                case "date":
                    $headers->addDateHeader($name, new \DateTime($value));
                    break;
                case "to":
                case "from":
                case "bcc":
                case "reply-to":
                case "cc":
                    $adresses = array();
                    if (preg_match_all('/(.*?)<([a-z][a-z0-9_\-\.]*@[a-z0-9\.\-]*\.[a-z]{2,5})>\s*[;,]*/i', $value, $mch)) {
                        foreach ($mch[0] as $k => $mail) {
                            if (!$mch[1][$k]) {
                                $adresses[$mch[2][$k]] = trim($mch[2][$k]);
                            } else {
                                $adresses[$mch[2][$k]] = trim($mch[1][$k]);
                            }
                        }
                    } elseif (preg_match_all('/([a-z][a-z0-9_\-\.]*@[a-z0-9\.\-]*\.[a-z]{2,5})/i', $value, $mch)) {
                        foreach ($mch[0] as $k => $mails) {
                            $adresses[$mch[1][$k]] = trim($mch[1][$k]);
                        }
                    }
                    $headers->addMailboxHeader($name, $adresses);
                    break;
                default:
                    $headers->addTextHeader($name, $value);
                    break;
            }
        }
        return $headers;
    }

    protected function createMessage(array $message, \Swift_Mime_SimpleMimeEntity $entity): void
    {
        if (stripos($message["type"], 'multipart/') !== false && !empty($message["parts"])) {

            if (strpos($message["type"], '/alternative')) {
                $nestingLevel = \Swift_Mime_SimpleMimeEntity::LEVEL_ALTERNATIVE;
            } elseif (strpos($message["type"], '/related')) {
                $nestingLevel = \Swift_Mime_SimpleMimeEntity::LEVEL_RELATED;
            } elseif (strpos($message["type"], '/mixed')) {
                $nestingLevel = \Swift_Mime_SimpleMimeEntity::LEVEL_MIXED;
            } else {
                $nestingLevel = \Swift_Mime_SimpleMimeEntity::LEVEL_TOP;
            }

            $childs = array();
            foreach ($message["parts"] as $part) {

                $headers = $this->createHeadersSet($part["headers"]);
                $encoder = $this->getEncoder($this->getTransferEncoding($part["headers"]));

                if (stripos($part["type"], 'multipart/') !== false) {
                    $newEntity = new \Swift_Mime_MimePart ($headers, $encoder, $this->getCache(), $this->getIdGenertor());
                } else {
                    $newEntity = new \Swift_Mime_SimpleMimeEntity ($headers, $encoder, $this->getCache(), $this->getIdGenertor());
                }

                $this->createMessage($part, $newEntity);

                $ref = new \ReflectionObject ($newEntity);
                $m = $ref->getMethod('setNestingLevel');
                $m->setAccessible(true);
                $m->invoke($newEntity, $nestingLevel);

                $childs[] = $newEntity;
            }

            $entity->setContentType($part["type"]);
            $entity->setChildren($childs);
        } else {
            $entity->setBody($message["body"], $message["type"]);
        }
    }

    protected function getEncoder(string $type): \Swift_Mime_ContentEncoder
    {
        switch ($type) {
            case "base64":
                return \Swift_DependencyContainer::getInstance()->lookup('mime.base64contentencoder');
            case "8bit":
                return \Swift_DependencyContainer::getInstance()->lookup('mime.8bitcontentencoder');
            case "7bit":
                return \Swift_DependencyContainer::getInstance()->lookup('mime.7bitcontentencoder');
            default:
                return \Swift_DependencyContainer::getInstance()->lookup('mime.qpcontentencoder');
        }
    }
}
