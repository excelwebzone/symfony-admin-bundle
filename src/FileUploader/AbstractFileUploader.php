<?php

namespace EWZ\SymfonyAdminBundle\FileUploader;

use Intervention\Image\ImageManagerStatic;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class AbstractFileUploader implements FileUploaderInterface
{
    public const IMAGE_DRIVER_GD = 'gd';
    public const IMAGE_DRIVER_IMAGICK = 'imagick';

    /** @var ValidatorInterface */
    protected $validator;

    /** @var TranslatorInterface */
    protected $translator;

    /** @var string */
    protected $mimeTypesExtensions;

    /** @var array */
    protected $mimeTypesTypes;

    /** @var int */
    protected $maxFilesize;

    /**
     * @var string
     *
     * Intervention Image supports "GD Library" and "Imagick" to process images
     * internally. You may choose one of them according to your PHP
     * configuration. By default PHP's "GD Library" implementation is used.
     *
     * Supported: "gd", "imagick"
     */
    protected $imageDriver;

    /**
     * @param ValidatorInterface  $validator
     * @param TranslatorInterface $translator
     * @param string              $mimeTypesExtensions
     * @param array               $mimeTypesTypes
     * @param int                 $maxFilesize
     * @param string|null         $imageDriver
     */
    public function __construct(
        ValidatorInterface $validator,
        TranslatorInterface $translator,
        string $mimeTypesExtensions,
        array $mimeTypesTypes,
        int $maxFilesize,
        string $imageDriver = null
    ) {
        $this->validator = $validator;
        $this->translator = $translator;
        $this->mimeTypesExtensions = $mimeTypesExtensions;
        $this->mimeTypesTypes = $mimeTypesTypes;
        $this->maxFilesize = $maxFilesize;
        $this->imageDriver = $imageDriver;

        if (!\in_array($this->imageDriver, [self::IMAGE_DRIVER_GD, self::IMAGE_DRIVER_IMAGICK])) {
            $this->imageDriver = self::IMAGE_DRIVER_GD;
        }
    }

    /**
     * @param UploadedFile $file
     * @param array        $mimeTypes
     * @param bool         $isPhoto
     *
     * @return string|null
     */
    public function validate(UploadedFile $file, array $mimeTypes = [], bool $isPhoto = false): ?string
    {
        $mimeTypesString = $mimeTypes['extensions'] ?? $this->mimeTypesExtensions;
        $mimeTypes = $mimeTypes['types'] ?? $this->mimeTypesTypes;

        if (true === $isPhoto) {
            $mimeTypesString = 'PNG, GIF, or JPG';
            $mimeTypes = [
                'image/png',
                'image/jpeg',
                'image/jpg',
                'image/gif',
            ];
        }

        $constraints = [
            new Assert\NotBlank(),
            new Assert\File([
                'maxSize' => sprintf('%dM', $this->maxFilesize),
                'mimeTypes' => $mimeTypes,
                'disallowEmptyMessage' => $this->translator->trans('error.file.file_empty'),
                'maxSizeMessage' => $this->translator->trans('error.file.too_big', ['%size%' => $this->maxFilesize]),
                'mimeTypesMessage' => $this->translator->trans('error.file.bad_file', ['%mimeTypes%' => $mimeTypesString]),
                'uploadErrorMessage' => $this->translator->trans('error.file.failed_request'),
            ]),
        ];

        $errors = $this->validator->validate($file, $constraints);

        if (\count($errors) > 0) {
            return $errors[0]->getMessage();
        }

        if ($isPhoto) {
            list($width, $height) = @getimagesize($file->getPathName());
            if ($width > 10000 || $height > 10000) {
                return $this->translator->trans('error.file.bad_dimensions', ['%dimensions%' => '10,000x10,000']);
            }
        }

        return null;
    }

    /**
     * @param UploadedFile $file
     */
    public function fixOrientate(UploadedFile $file): void
    {
        if ('image/' !== substr($file->getMimeType(), 0, 6)) {
            return;
        }

        try {
            // configure with favored image driver (by default uses GD image driver)
            if (self::IMAGE_DRIVER_GD !== $this->imageDriver) {
                ImageManagerStatic::configure([
                    'driver' => $this->imageDriver,
                ]);
            }

            // create a new image resource
            $image = ImageManagerStatic::make($file->getPathname());

            // adjusts image orientation automatically
            $image->orientate();

            $image->save($file->getPathname());
        } catch (\Exception $e) {
            // do nothing
        }
    }
}
