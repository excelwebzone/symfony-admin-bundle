<?php

namespace EWZ\SymfonyAdminBundle\Twig\Extension;

use EWZ\SymfonyAdminBundle\Util\StringUtil;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class StringExtension extends AbstractExtension
{
    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return [
            new TwigFunction('preg_match', [$this, 'pregMatch']),
            new TwigFunction('preg_replace', [$this, 'pregReplace']),
            new TwigFunction('serialize', [$this, 'serialize']),
        ];
    }

    /**
     * @param string $pattern
     * @param string $subject
     *
     * @return int
     */
    public function pregMatch(string $pattern, string $subject): int
    {
        return preg_match($pattern, $subject);
    }

    /**
     * @param mixed $pattern
     * @param mixed $replacement
     * @param mixed $subject
     *
     * @return mixed
     */
    public function pregReplace($pattern, $replacement, $subject, int $limit = -1)
    {
        return preg_replace($pattern, $replacement, $subject, $limit);
    }

    /**
     * @param string      $string
     * @param string|null $enumClass
     *
     * @return array|null
     */
    public function serialize(string $string, string $enumClass = null): ?array
    {
        try {
            $data = unserialize($string);
            if (!is_array($data)) {
                $data = [$data];
            }

            if ($enumClass) {
                foreach ($data as $key => $value) {
                    if ($enumClass::isValueExist($value)) {
                        $data[$key] = $enumClass::getReadableValue($value);
                    }
                }
            } else {
                foreach ($data as $key => $value) {
                    $data[$key] = StringUtil::ucwords($value);
                }
            }

            return $data;
        } catch (\Exception $e) {
            return null;
        }
    }
}
