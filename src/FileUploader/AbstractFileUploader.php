<?php

namespace EWZ\SymfonyAdminBundle\FileUploader;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

abstract class AbstractFileUploader implements FileUploaderInterface
{
    /** @var ValidatorInterface */
    protected $validator;

    /** @var TranslatorInterface */
    protected $translator;

    /** @var string */
    protected $mimeTypesExtensions;

    /** @var array */
    protected $mimeTypesTypes;

    /**
     * @param ValidatorInterface  $validator
     * @param TranslatorInterface $translator
     * @param string              $mimeTypesExtensions
     * @param array               $mimeTypesTypes
     */
    public function __construct(
        ValidatorInterface $validator,
        TranslatorInterface $translator,
        string $mimeTypesExtensions,
        array $mimeTypesTypes
    ) {
        $this->validator = $validator;
        $this->translator = $translator;
        $this->mimeTypesExtensions = $mimeTypesExtensions;
        $this->mimeTypesTypes = $mimeTypesTypes;
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

        // @todo: set global parameter
        $maxSize = 5;

        if (true === $isPhoto) {
            $mimeTypesString = 'PNG, GIF, or JPG';
            $mimeTypes = [
                'image/png',
                'image/jpeg',
                'image/jpg',
                'image/gif',
            ];

            $maxSize = 1;
        }

        $constraints = [
            new Assert\NotBlank(),
            new Assert\File([
                'maxSize' => sprintf('%dM', $maxSize),
                'mimeTypes' => $mimeTypes,
                'disallowEmptyMessage' => $this->translator->trans('error.file.file_empty'),
                'maxSizeMessage' => $this->translator->trans('error.file.too_big', ['%size%' => $maxSize]),
                'mimeTypesMessage' => $this->translator->trans('error.file.bad_file', ['%mimeTypes%' => $mimeTypesString]),
                'uploadErrorMessage' => $this->translator->trans('error.file.failed_request'),
            ]),
        ];

        $errors = $this->validator->validate($file, $constraints);

        if (count($errors) > 0) {
            return $errors[0]->getMessage();
        }

        if ($isPhoto) {
            list($width, $height) = @getimagesize($file->getPathName());
            if ($width > 10000 || $height > 10000) {
                return $this->translator->trans('error.photo.bad_dimensions', ['%dimensions%' => '10,000x10,000']);
            }
        }

        return null;
    }
}
