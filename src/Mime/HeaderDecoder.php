<?php

namespace Goetas\Mail\ToSwiftMailParser\Mime;

class HeaderDecoder
{
    private $decodeWindows1252;

    public function __construct(bool $decodeWindows1252 = false)
    {
        $this->decodeWindows1252 = $decodeWindows1252;
    }

    public function decode(string $string): string
    {
        /* Take out any spaces between multiple encoded words. */
        $string = preg_replace('|\?=\s+=\?|', '?==?', $string);

        $out = '';
        $old_pos = 0;

        while (($pos = strpos($string, '=?', $old_pos)) !== false) {
            /* Save any preceding text. */
            $out .= substr($string, $old_pos, $pos - $old_pos);

            /* Search for first delimiting question mark (charset). */
            if (($d1 = strpos($string, '?', $pos + 2)) === false) {
                break;
            }

            $orig_charset = substr($string, $pos + 2, $d1 - $pos - 2);
            if ($this->decodeWindows1252 && mb_strtolower($orig_charset) == 'iso-8859-1') {
                $orig_charset = 'windows-1252';
            }

            /* Search for second delimiting question mark (encoding). */
            if (($d2 = strpos($string, '?', $d1 + 1)) === false) {
                break;
            }

            $encoding = substr($string, $d1 + 1, $d2 - $d1 - 1);

            /* Search for end of encoded data. */
            if (($end = strpos($string, '?=', $d2 + 1)) === false) {
                break;
            }

            $encoded_text = substr($string, $d2 + 1, $end - $d2 - 1);

            switch ($encoding) {
                case 'Q':
                case 'q':
                    $out .= self::convertCharset(preg_replace_callback('/=([0-9a-f]{2})/i', function ($ord) {
                        return chr(hexdec($ord [1]));
                    }, str_replace('_', ' ', $encoded_text)), $orig_charset, mb_internal_encoding());
                    break;

                case 'B':
                case 'b':
                    $out .= self::convertCharset(base64_decode($encoded_text), $orig_charset, mb_internal_encoding());
                    break;

                default:
                    // Ignore unknown encoding.
                    break;
            }

            $old_pos = $end + 2;
        }

        return $out . substr($string, $old_pos);
    }

    private static function convertCharset(string $str, string $orig, string $to)
    {
        return mb_convert_encoding($str, $to, $orig);
    }
}
