<?php

namespace SMPP3\Util;

/**
 *
 */
class GSMEncoder
{
    /**
     * @var string[]
     */
    public static $dict = [
        '@' => "\x00", '£' => "\x01", '$' => "\x02", '¥' => "\x03", 'è' => "\x04", 'é' => "\x05", 'ù' => "\x06", 'ì' => "\x07", 'ò' => "\x08", 'Ç' => "\x09", 'Ø' => "\x0B", 'ø' => "\x0C", 'Å' => "\x0E", 'å' => "\x0F",
        'Δ' => "\x10", '_' => "\x11", 'Φ' => "\x12", 'Γ' => "\x13", 'Λ' => "\x14", 'Ω' => "\x15", 'Π' => "\x16", 'Ψ' => "\x17", 'Σ' => "\x18", 'Θ' => "\x19", 'Ξ' => "\x1A", 'Æ' => "\x1C", 'æ' => "\x1D", 'ß' => "\x1E", 'É' => "\x1F",
        '¡' => "\x40",
        'Ä' => "\x5B", 'Ö' => "\x5C", 'Ñ' => "\x5D", 'Ü' => "\x5E", '§' => "\x5F",
        '¿' => "\x60",
        'ä' => "\x7B", 'ö' => "\x7C", 'ñ' => "\x7D", 'ü' => "\x7E", 'à' => "\x7F",
        '^' => "\x1B\x14", '{' => "\x1B\x28", '}' => "\x1B\x29", '\\' => "\x1B\x2F", '[' => "\x1B\x3C", '~' => "\x1B\x3D", ']' => "\x1B\x3E", '|' => "\x1B\x40", '€' => "\x1B\x65",
    ];

    /**
     * @param $string
     * @return string
     */
    public static function utf8ToGsm0338($string)
    {
        return strtr($string, self::$dict);
    }

    /**
     * @param $utf8_string
     * @return bool
     */
    public static function isGsm0338($utf8_string)
    {
        for ($i = 0; $i < mb_strlen($utf8_string); $i++) {
            $char = mb_substr($utf8_string, $i, 1);
            if (ord($char) > 0x7F && !isset(self::$dict[$char])) {
                return false;
            }
        }
        return true;
    }
}