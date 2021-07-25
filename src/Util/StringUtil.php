<?php

namespace EWZ\SymfonyAdminBundle\Util;

use Doctrine\Common\Inflector\Inflector;

final class StringUtil extends Inflector
{
    const PASSWORD_UPPER_CASE = 'UPPERCASE';
    const PASSWORD_LOWER_CASE = 'LOWERCASE';
    const PASSWORD_NUMBERS = 'NUMBERS';
    const PASSWORD_SYMBOLS = 'SYMBOLS';

    /**
     * @param string $string
     *
     * @return string|null
     */
    public static function canonicalize(string $string): ?string
    {
        if (null === $string) {
            return null;
        }

        $encoding = mb_detect_encoding($string);
        $result = $encoding
            ? mb_convert_case($string, MB_CASE_LOWER, $encoding)
            : mb_convert_case($string, MB_CASE_LOWER);

        return $result;
    }

    /**
     * @param int $length
     *
     * @return string
     */
    public static function generateToken(int $length = 32): string
    {
        return rtrim(strtr(base64_encode(random_bytes($length)), '+/', '-_'), '=');
    }

    /**
     * Generate one password based on options.
     *
     * @param int        $length
     * @param array|null $options
     *
     * @return string
     */
    public static function generatePassword(int $length = 10, array $options = null): string
    {
        if (empty($options)) {
            $options = [
                self::PASSWORD_UPPER_CASE,
                self::PASSWORD_LOWER_CASE,
                self::PASSWORD_NUMBERS,
                self::PASSWORD_SYMBOLS,
            ];
        }

        $characters = '';

        if (in_array(self::PASSWORD_UPPER_CASE, $options)) {
            $characters .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        }

        if (in_array(self::PASSWORD_LOWER_CASE, $options)) {
            $characters .= 'abcdefghijklmnopqrstuvwxyz';
        }

        if (in_array(self::PASSWORD_NUMBERS, $options)) {
            $characters .= '0123456789';
        }

        if (in_array(self::PASSWORD_SYMBOLS, $options)) {
            $characters .= '!@$%^&*()<>,.?/[]{}-=_+';
        }

        if (!$characters) {
            throw new \Exception('No character sets selected.');
        }

        $characterList = $characters;
        $characters = strlen($characterList);
        $password = '';

        for ($i = 0; $i < $length; ++$i) {
            $password .= $characterList[mt_rand(0, $characters - 1)];
        }

        return $password;
    }

    /**
     * Calculating Color Contrast.
     *
     * @param string|null $hex Colour as hexadecimal (with or without hash);
     *
     * @return string
     */
    public static function getContrastColor(string $hex = null)
    {
        if ('#' != substr($hex, 0, 1)) {
            $hex = '#'.$hex;
        }

        if (empty($hex) || '#' === $hex) {
            return '000';
        }

        list($red, $green, $blue) = sscanf($hex, '#%02x%02x%02x');

        $luma = ($red + $green + $blue) / 3;

        return $luma < 128 ? 'fff' : '000';
    }
}
