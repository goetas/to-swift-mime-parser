<?php

namespace Goetas\Mail\ToSwiftMailParser\Mime;

class ContentDecoder {
    public function decode($string, $from) {
        if ($from == "base64") {
            return base64_decode ( $string );
        } elseif ($from == "7bit") {
            return quoted_printable_decode ( $string );
        } elseif ($from == "quoted-printable") {
            return quoted_printable_decode ( $string );
        }
        return $string;
    }
}
