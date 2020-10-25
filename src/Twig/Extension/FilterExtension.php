<?php

namespace EWZ\SymfonyAdminBundle\Twig\Extension;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class FilterExtension extends AbstractExtension
{
    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return [
            new TwigFunction('filter_preload_json', [$this, 'preloadJson']),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getFilters()
    {
        return [
            new TwigFilter('filter_json_encode', [$this, 'jsonEncode']),
        ];
    }

    /**
     * @param string|array $json
     *
     * @return string
     */
    public function preloadJson($json): string
    {
        if (is_array($json)) {
            return $this->jsonEncode($json);
        }

        try {
            $array = json_decode($json, true);
        } catch (\Exception $e) {
            $array = [];
        }

        return $this->jsonEncode($array);
    }

    /**
     * @param array $array
     *
     * @return string
     */
    public function jsonEncode(array $array): string
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $k => $v) {
                    $array[$key][$k] = (string) $v;
                }
            } else {
                $array[$key] = (string) $value;
            }
        }

        return json_encode($array);
    }
}
