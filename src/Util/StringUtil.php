<?php

namespace EWZ\SymfonyAdminBundle\Util;

final class StringUtil
{
    public const PASSWORD_UPPER_CASE = 'UPPERCASE';
    public const PASSWORD_LOWER_CASE = 'LOWERCASE';
    public const PASSWORD_NUMBERS = 'NUMBERS';
    public const PASSWORD_SYMBOLS = 'SYMBOLS';

    /**
     * @see DoctrineInflector::tableize
     */
    public static function tableize(string $word): string
    {
        return Inflector::getInstance()->tableize($word);
    }

    /**
     * @see DoctrineInflector::classify
     */
    public static function classify(string $word): string
    {
        return Inflector::getInstance()->classify($word);
    }

    /**
     * @see DoctrineInflector::camelize
     */
    public static function camelize(string $word): string
    {
        return Inflector::getInstance()->camelize($word);
    }

    /**
     * @see DoctrineInflector::capitalize
     */
    public static function capitalize(string $string, string $delimiters = " \n\t\r\0\x0B-"): string
    {
        return Inflector::getInstance()->capitalize($word);
    }

    /**
     * @see DoctrineInflector::seemsUtf8
     */
    public static function seemsUtf8(string $string): bool
    {
        return Inflector::getInstance()->seemsUtf8($word);
    }

    /**
     * @see DoctrineInflector::unaccent
     */
    public static function unaccent(string $string): string
    {
        return Inflector::getInstance()->unaccent($word);
    }

    /**
     * @see DoctrineInflector::urlize
     */
    public static function urlize(string $string): string
    {
        return Inflector::getInstance()->urlize($word);
    }

    /**
     * @see DoctrineInflector::singularize
     */
    public static function singularize(string $word): string
    {
        return Inflector::getInstance()->singularize($word);
    }

    /**
     * @see DoctrineInflector::pluralize
     */
    public static function pluralize(string $word): string
    {
        return Inflector::getInstance()->pluralize($word);
    }

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
            ? mb_convert_case($string, \MB_CASE_LOWER, $encoding)
            : mb_convert_case($string, \MB_CASE_LOWER);

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

        if (\in_array(self::PASSWORD_UPPER_CASE, $options)) {
            $characters .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        }

        if (\in_array(self::PASSWORD_LOWER_CASE, $options)) {
            $characters .= 'abcdefghijklmnopqrstuvwxyz';
        }

        if (\in_array(self::PASSWORD_NUMBERS, $options)) {
            $characters .= '0123456789';
        }

        if (\in_array(self::PASSWORD_SYMBOLS, $options)) {
            $characters .= '!@$%^&*()<>,.?/[]{}-=_+';
        }

        if (!$characters) {
            throw new \Exception('No character sets selected.');
        }

        $characterList = $characters;
        $characters = \strlen($characterList);
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
