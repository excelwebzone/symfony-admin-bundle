<?php

namespace EWZ\SymfonyAdminBundle\Twig\Extension;

use Html2Text\Html2Text;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
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
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getFilters()
    {
        return [
            new TwigFilter('html2text', [$this, 'html2Text']),
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
     * @param string $string
     *
     * @return mixed
     */
    public function html2Text(string $string)
    {
        return (new Html2Text($string))->getText();
    }
}
