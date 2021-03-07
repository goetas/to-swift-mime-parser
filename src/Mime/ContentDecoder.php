<?php

namespace Goetas\Mail\ToSwiftMailParser\Mime;

class ContentDecoder
{
    public function decode(string $string, string $from): string
    {
        if ($from === "base64") {
            return base64_decode($string);
        }

        if ($from === "7bit") {
            return quoted_printable_decode($string);
        }

        if ($from === "quoted-printable") {
            return quoted_printable_decode($string);
        }

        return $string;
    }
}
