<?php

namespace EWZ\SymfonyAdminBundle\Twig\Extension;

use EWZ\SymfonyAdminBundle\FileUploader\FileUploaderInterface;
use EWZ\SymfonyAdminBundle\Util\StringUtil;
use Twig\Environment as TwigEnvironment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class AppExtension extends AbstractExtension
{
    /** @var TwigEnvironment */
    protected $twig;

    /** @var FileUploaderInterface */
    protected $fileUploader;

    /**
     * @param TwigEnvironment       $twig
     * @param FileUploaderInterface $fileUploader
     */
    public function __construct(TwigEnvironment $twig, FileUploaderInterface $fileUploader)
    {
        $this->twig = $twig;
        $this->fileUploader = $fileUploader;
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('time', [$this, 'getTime']),
            new TwigFunction('contrast_color', [$this, 'getContrastColor']),
            new TwigFunction('mime_content_type', [$this, 'getMimeContentType']),
            new TwigFunction('filesize', [$this, 'getFilesize']),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('rating', [$this, 'displayRating']),
        ];
    }

    /**
     * @param int $hours
     * @param int $minutes
     * @param int $seconds
     *
     * @return string|null
     */
    public function getTime(int $hours, int $minutes, int $seconds = 0): ?string
    {
        $seconds = $seconds + $minutes * 60 + $hours * 3600;

        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds / 60) % 60);
        $seconds = $seconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }

    /**
     * @param string $hex
     *
     * @return string
     */
    public function getContrastColor($hex)
    {
        return StringUtil::getContrastColor($hex);
    }

    /**
     * @param string|null $file
     *
     * @return string|null
     */
    public function getMimeContentType(string $file = null): ?string
    {
        return $file ? $this->fileUploader->getMimeType($file) : null;
    }

    /**
     * @param string|null $file
     *
     * @return ?string|null
     */
    public function getFilesize(string $file = null): ?string
    {
        if (!$file) {
            return null;
        }

        $size = $this->fileUploader->getFileSize($file);
        $base = log($size) / log(1024);
        $suffix = ['', 'KB', 'MB', 'GB', 'TB'];
        $f_base = floor($base);

        return sprintf('%s%s', round(1024 ** ($base - floor($base)), 1), $suffix[$f_base]);
    }

    /**
     * @param int         $rating
     * @param int         $stars
     * @param string|null $class
     * @param bool        $readonly
     *
     * @return string
     */
    public function displayRating(int $rating, int $stars = 5, string $class = null, bool $readonly = true)
    {
        return $this->twig->load('@SymfonyAdmin/form/rating.html.twig')->render([
            'rating' => $rating,
            'stars' => $stars,
            'class' => $class,
            'readonly' => $readonly,
        ]);
    }
}
