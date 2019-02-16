<?php

namespace EWZ\SymfonyAdminBundle\Twig\Extension;

use EWZ\SymfonyAdminBundle\Modal\User;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Google\GoogleAuthenticatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class AppExtension extends AbstractExtension
{
    /** @var \Twig_Environment */
    protected $twig;

    /** @var string */
    private $publicDir;

    /** @var GoogleAuthenticatorInterface */
    private $twoFactor;

    /**
     * @param \Twig_Environment                 $twig
     * @param string                            $publicDir
     * @param GoogleAuthenticatorInterface|null $twoFactor
     */
    public function __construct(\Twig_Environment $twig, string $publicDir, GoogleAuthenticatorInterface $twoFactor = null)
    {
        $this->twig = $twig;
        $this->publicDir = $publicDir;
        $this->twoFactor = $twoFactor;
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return [
            new TwigFunction('time', [$this, 'getTime']),
            new TwigFunction('qrcode_url', [$this, 'getQRCodeUrl']),
            new TwigFunction('contrast_color', [$this, 'getContrastColor']),
            new TwigFunction('mime_content_type', [$this, 'getMimeContentType']),
            new TwigFunction('filesize', [$this, 'getFilesize']),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getFilters()
    {
        return [
            new TwigFilter('rating', [$this, 'displayRating']),
        ];
    }

    /**
     * @param int $hours
     * @param int $minutes
     *
     * @return string|null
     */
    public function getTime(int $hours, int $minutes): ?string
    {
        $minutes += $hours * 60;
        $hours = floor($minutes / 60);
        $minutes = $minutes % 60;

        return sprintf('%02d:%02d', $hours, $minutes);
    }

    /**
     * @param User $user
     *
     * @return string
     */
    public function getQRCodeUrl(User $user): string
    {
        return $this->twoFactor ? $this->twoFactor->getUrl($user) : null;
    }

    /**
     * Calculating Color Contrast.
     *
     * @param string $hex Colour as hexadecimal (with or without hash);
     *
     * @return string
     */
    public function getContrastColor($hex)
    {
        if ('#' != substr($hex, 0, 1)) {
            $hex = '#'.$hex;
        }

        list($red, $green, $blue) = sscanf($hex, '#%02x%02x%02x');

        $luma = ($red + $green + $blue) / 3;

        return $luma < 128 ? 'fff' : '000';
    }

    /**
     * @param string|null $file
     *
     * @return string|null
     */
    public function getMimeContentType(string $file = null): ?string
    {
        if (!$file || !file_exists($file = sprintf('%s%s', $this->publicDir, $file))) {
            return null;
        }

        return mime_content_type($file);
    }

    /**
     * @param string|null $file
     *
     * @return ?string|null
     */
    public function getFilesize(string $file = null): ?string
    {
        if (!$file || !file_exists($file = sprintf('%s%s', $this->publicDir, $file))) {
            return null;
        }

        $size = filesize($file);
        $base = log($size) / log(1024);
        $suffix = array('', 'KB', 'MB', 'GB', 'TB');
        $f_base = floor($base);

        return sprintf('%s%s', round(pow(1024, $base - floor($base)), 1), $suffix[$f_base]);
    }

    /**
     * @param int         $rating
     * @param int         $max
     * @param string|null $class
     *
     * @return string
     */
    public function displayRating(int $rating, int $max = 5, string $class = null)
    {
        return $this->twig->load('form/rating.html.twig')->render([
            'rating' => $rating,
            'max' => $max,
            'class' => $class,
        ]);
    }
}
