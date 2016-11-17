<?php

namespace Goetas\Mail\ToSwiftMailParser;

use Goetas\Mail\ToSwiftMailParser\Mime\HeaderDecoder;
use Goetas\Mail\ToSwiftMailParser\Mime\ContentDecoder;

class MimeParser {
    private $cache;
    private $grammar;
    private $contentDecoder;
    private $headerDecoder;
    protected $removeHeaders = array ("Received","From","X-Original-To","MIME-Version","Received-SPF","Delivered-To");
    protected $allowedHeaders = array ("return-path","subject");
    public function __construct(array $allowedHeaders = array(), array $removeHeaders = array()) {
        $this->cache = \Swift_DependencyContainer::getInstance ()->lookup ( 'cache' );
        $this->grammar = \Swift_DependencyContainer::getInstance ()->lookup ( 'mime.grammar' );
        $this->contentDecoder = new ContentDecoder ();
        $this->headerDecoder = new HeaderDecoder ();

        $this->allowedHeaders = array_merge ( $this->allowedHeaders, $allowedHeaders );
        $this->removeHeaders = array_merge ( $this->removeHeaders, $removeHeaders );
    }
    /**
     *
     * @param stream $stream
     * @param boolean $fillHeaders (default to false)
     * @param \Swift_Mime_MimeEntity $message (default to null)
     * @return Swift_Mime_MimeEntity|\Swift_Message
     */
    public function parseStream($stream, $fillHeaders = false,  \Swift_Mime_MimeEntity $message = null) {
        $partHeaders = $this->extractHeaders ( $stream );

        $filteredHeaders = $this->filterHeaders ( $partHeaders );

        $parts = $this->parseParts ( $stream, $partHeaders );

        if(!$message){
            $message = new \Swift_Message ();
        }

        $headers = $this->createHeadersSet ( $filteredHeaders );

        foreach ( $headers->getAll () as $name => $header ) {
            if ($fillHeaders || in_array ( strtolower ( $header->getFieldName () ), $this->allowedHeaders )) {
                $message->getHeaders ()->set ( $header );
            }
        }
        $this->createMessage ( $parts, $message );

        return $message;
    }
    /**
     *
     * @param string $string
     *            The message
     * @return \Swift_Message
     */
    public function parseString($string) {
        $fp = tmpfile ();
        fwrite ( $fp, $string );
        rewind ( $fp );
        $message = $this->parseStream ( $fp );
        fclose ( $fp );
        return $message;
    }
    /**
     *
     * @param string $path
     *            The file containg a MIME message
     * @return \Swift_Message
     */
    public function parseFile($path) {
        $fp = fopen ( $path, "rb" );
        $message = $this->parseStream ( $fp );
        fclose ( $fp );
        return $message;
    }

    protected function parseParts($stream, $partHeaders) {
        $parts = array ();
        $part = 0;
        $contentTypeHeader = array_key_exists( 'content-type', $partHeaders ) ? $partHeaders ['content-type']  : '';
        $contentType =  $this->extractValueHeader( $contentTypeHeader );

        if (stripos ( $contentType, 'multipart/' ) !== false) {
            $headerParts = $this->extractHeaderParts ( $contentTypeHeader );
            $boundary = $headerParts ["boundary"];
        } else {
            $boundary = null;
        }

        try {
            // body
            $this->extractPart ( $stream, $boundary, $this->getTransferEncoding($partHeaders) );
        } catch ( Exception\EndOfPartReachedException $e ) {
            $parts = array ("type" => $contentType,"headers" => $partHeaders,"body" => $e->getData (),"boundary" => $boundary,"parts" => array ());
        }

        if ($boundary) {
            while ( ! feof ( $stream ) ) {
                try {
                    $partHeaders = $this->extractHeaders ( $stream );
                    $childContentType = $this->extractValueHeader ( $partHeaders ["content-type"] );

                    if (stripos ( $childContentType, 'multipart/' ) !== false) {
                        $parts ["parts"] [] = $this->parseParts ( $stream, $partHeaders );
                        try {
                            $this->extractPart ( $stream, $boundary, $this->getTransferEncoding( $partHeaders ));
                        } catch ( Exception\EndOfPartReachedException $e ) {}
                    } else {
                        $this->extractPart ( $stream, $boundary, $this->getTransferEncoding( $partHeaders ));
                    }
                } catch ( Exception\EndOfPartReachedException $e ) {
                    $parts ["parts"] [] = array ("type" => $childContentType,"parent-type" => $contentType,"headers" => $partHeaders,"body" => $e->getData (),"parts" => array ());

                    if ($e instanceof Exception\EndOfMultiPartReachedException) {
                        break;
                    }
                }
            }
        }
        return $parts;
    }

    private function getTransferEncoding(array $partHeaders)
    {
       if (array_key_exists( 'content-transfer-encoding',  $partHeaders )) {
           return $partHeaders ['content-transfer-encoding'];
       }

       return '';
    }

    /**
     *
     * @param string $type
     * @return \Swift_Mime_ContentEncoder
     */
    protected function getEncoder($type) {
        switch ($type) {
            case "base64" :
                return \Swift_DependencyContainer::getInstance ()->lookup ( 'mime.base64contentencoder' );
                break;
            case "8bit" :
                return \Swift_DependencyContainer::getInstance ()->lookup ( 'mime.8bitcontentencoder' );
                break;
            case "7bit" :
                return \Swift_DependencyContainer::getInstance ()->lookup ( 'mime.7bitcontentencoder' );
                break;
            default :
                return \Swift_DependencyContainer::getInstance ()->lookup ( 'mime.qpcontentencoder' );
                break;
        }
    }
    /**
     *
     * @param array $headersRaw
     * @return \Swift_Mime_HeaderSet
     */
    protected function createHeadersSet(array $headersRaw) {
        $headers = \Swift_DependencyContainer::getInstance ()->lookup ( 'mime.headerset' );

        foreach ( $headersRaw as $name => $value ) {
            switch (strtolower ( $name )) {
                case "content-type" :
                    $parts = $this->extractHeaderParts ( $value );
                    unset ( $parts ["boundary"] );
                    $headers->addParameterizedHeader ( $name, $this->extractValueHeader ( $value ), $parts );
                    break;
                case "return-path" :
                    if (preg_match_all ( '/([a-z][a-z0-9_\-\.]*@[a-z0-9\.\-]*\.[a-z]{2,5})/i', $value, $mch )) {
                        foreach ( $mch [0] as $k => $mails ) {
                            $headers->addPathHeader ( $name,  $mch [1] [$k] );
                        }
                    }
                break;
                case "date":
                    $headers->addDateHeader ( $name,  strtotime($value) );
                case "to":
                case "from" :
                case "bcc" :
                case "reply-to" :
                case "cc" :
                    $adresses = array ();
                    if (preg_match_all ( '/(.*?)<([a-z][a-z0-9_\-\.]*@[a-z0-9\.\-]*\.[a-z]{2,5})>\s*[;,]*/i', $value, $mch )) {
                        foreach ( $mch [0] as $k => $mail ) {
                            if (! $mch [1] [$k]) {
                                $adresses [$mch [2] [$k]] = $mch [2] [$k];
                            } else {
                                $adresses [$mch [2] [$k]] = $mch [1] [$k];
                            }
                        }
                    } elseif (preg_match_all ( '/([a-z][a-z0-9_\-\.]*@[a-z0-9\.\-]*\.[a-z]{2,5})/i', $value, $mch )) {
                        foreach ( $mch [0] as $k => $mails ) {
                            $adresses [$mch [1] [$k]] = $mch [1] [$k];
                        }
                    }
                    $headers->addMailboxHeader ( $name, $adresses );
                    break;
                default :
                    $headers->addTextHeader ( $name, $value );
                    break;
            }
        }
        return $headers;
    }
    protected function createMessage(array $message, \Swift_Mime_MimeEntity $entity) {
        if (stripos ( $message ["type"], 'multipart/' ) !== false) {

            if (strpos ( $message ["type"], '/alternative' )) {
                $nestingLevel = \Swift_Mime_MimeEntity::LEVEL_ALTERNATIVE;
            } elseif (strpos ( $message ["type"], '/related' )) {
                $nestingLevel = \Swift_Mime_MimeEntity::LEVEL_RELATED;
            } elseif (strpos ( $message ["type"], '/mixed' )) {
                $nestingLevel = \Swift_Mime_MimeEntity::LEVEL_MIXED;
            }

            $childrens = array ();
            foreach ( $message ["parts"] as $part ) {

                $headers = $this->createHeadersSet ( $part ["headers"] );
                $encoder = $this->getEncoder ( $this->getTransferEncoding($part ["headers"] ) );

                if (stripos ( $part ["type"], 'multipart/' ) !== false) {
                    $newEntity = new \Swift_Mime_MimePart ( $headers, $encoder, $this->cache, $this->grammar );
                } else {
                    $newEntity = new \Swift_Mime_SimpleMimeEntity ( $headers, $encoder, $this->cache, $this->grammar );
                }

                $this->createMessage ( $part, $newEntity );

                $ref = new \ReflectionObject ( $newEntity );
                $m = $ref->getMethod ( '_setNestingLevel' );
                $m->setAccessible ( true );
                $m->invoke ( $newEntity, $nestingLevel );

                $childrens [] = $newEntity;
            }

            $entity->setContentType ( $part ["type"] );
            $entity->setChildren ( $childrens );
        } else {
            $entity->setBody ( $message ["body"], $message ["type"] );
        }
    }
    private function extractValueHeader($header) {
        $pos = stripos ( $header, ';' );
        if ($pos !== false) {
            return substr ( $header, 0, $pos );
        } else {
            return $header;
        }
    }
    private function extractHeaderParts($header) {
        $pos = stripos ( $header, ';' );
        if ($pos !== false) {

            $parts = explode ( ";", $header );
            array_shift ( $parts );

            $p = array ();
            foreach ( $parts as $pv ) {
                list ( $k, $v ) = explode ( "=", trim ( $pv ), 2 );
                $p [$k] = trim ( $v, '"' );
            }
            return $p;
        } else {
            return array ();
        }
    }
    protected function extractPart($stream, $boundary, $encoding) {
        $rows = array ();
        while ( ! feof ( $stream ) ) {
            $row = fgets ( $stream );

            if ($boundary !== null) {
                if(strpos($row, "--$boundary--")===0){
                	throw new Exception\EndOfMultiPartReachedException ( $this->contentDecoder->decode ( implode ( "", $rows ), $encoding ) );
                }
                if(strpos($row, "--$boundary")===0){
                	throw new Exception\EndOfPartReachedException ( $this->contentDecoder->decode ( implode ( "", $rows ), $encoding ) );
                }
            }
            $rows [] = $row;
        }
        throw new Exception\EndOfMultiPartReachedException ( $this->contentDecoder->decode ( implode ( "", $rows ), $encoding ) );
    }
    protected function extractHeaders($stream) {
        $headers = array ();
        $hName = null;
        while ( ! feof ( $stream ) ) {
            $row = fgets ( $stream );
            if ($row == "\r\n" || $row == "\n" || $row == "\r") {
                break;
            }
            if (preg_match ( '/^([a-z0-9\-]+)\s*:(.*)/i', $row, $mch )) {
                $hName = strtolower ( $mch [1] );
                if (! in_array ( $hName, array ("content-type","content-transfer-encoding") )) {
                    $hName = $mch [1];
                }
                $row = $mch [2];
            }
            if (! $hName) {
                continue;
            }
            $headers [$hName] [] = trim ( $row );
        }
        foreach ( $headers as $header => $values ) {
            $headers [$header] = $this->headerDecoder->decode ( trim ( implode ( " ", $values ) ) );
        }
        return $headers;
    }
    private function filterHeaders(array $headers) {
        foreach ( $headers as $header => $values ) {
            if(in_array(strtolower ( $header ), $this->removeHeaders) && !in_array(strtolower ( $header ), $this->allowedHeaders)){
                unset ( $headers [$header] );
            }
        }
        return $headers;
    }
}
