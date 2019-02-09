<?php

namespace EWZ\SymfonyAdminBundle\Service;

use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class FileUploader
{
    /** @var ValidatorInterface */
    private $validator;

    /** @var TranslatorInterface */
    private $translator;

    /** @var KernelInterface */
    private $kernel;

    /** @var string */
    private $mimeTypesExtensions;

    /** @var array */
    private $mimeTypesTypes;

    /**
     * @param KernelInterface     $kernel
     * @param ValidatorInterface  $validator
     * @param TranslatorInterface $translator
     */
    public function __construct(KernelInterface $kernel, ValidatorInterface $validator, TranslatorInterface $translator)
    {
        $this->kernel = $kernel;
        $this->validator = $validator;
        $this->translator = $translator;
    }

    /*
     * @param string $extensions
     */
    public function setMimeTypesExtensions(string $extensions)
    {
        $this->mimeTypesExtensions = $extensions;
    }

    /*
     * @param array $types
     */
    public function setMimeTypesTypes(array $types)
    {
        $this->mimeTypesTypes = $types;
    }

    /**
     * @param UploadedFile $file
     * @param string       $directory
     * @param string       $oldFileName
     * @param array        $mimeTypes
     * @param bool         $isPhoto
     * @param string|null  $prefix
     *
     * @return string
     */
    public function upload(
        UploadedFile $file,
        string $directory,
        string $oldFileName = null,
        array $mimeTypes = [],
        bool $isPhoto = true,
        string $prefix = null
    ): string {
        if ($error = $this->validate($file, $mimeTypes, $isPhoto)) {
            throw new \Exception($error);
        }

        // create folder if doesn't exists
        if (!is_dir($filePath = sprintf('%s/%s', $this->kernel->getPublicDir(), $directory))) {
            mkdir($filePath);
        }

        if ($prefix) {
            $prefix .= '__';
        }

        // generate filename
        $fileName = sprintf('%s/%s%s.%s', $directory, $prefix, Uuid::uuid4(), $file->guessExtension());

        // move file to ..
        rename($file->getPathname(), sprintf('%s/%s', $this->kernel->getPublicDir(), $fileName));

        // delete old file (if exists)
        $this->delete($oldFileName);

        return $fileName;
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

    /**
     * @param string $fromDir
     * @param string $toDir
     * @param string $fileName
     *
     * @return string|null
     */
    public function move(string $fromDir, string $toDir, string $fileName): ?string
    {
        if (!file_exists($orgFilePath = sprintf('%s/%s', $this->kernel->getPublicDir(), $fileName))) {
            return null;
        }

        // create folder if doesn't exists
        if (!is_dir($filePath = sprintf('%s/%s', $this->kernel->getPublicDir(), $toDir))) {
            mkdir($filePath);
        }

        // generate filename
        $fileName = str_replace($fromDir, $toDir, $fileName);

        // move file to ..
        rename($orgFilePath, sprintf('%s/%s', $this->kernel->getPublicDir(), $fileName));

        return $fileName;
    }

    /**
     * @param string|null $fileName
     */
    public function delete(string $fileName = null): void
    {
        $filePath = sprintf('%s/%s', $this->kernel->getPublicDir(), $fileName);
        if ($fileName && file_exists($filePath)) {
            unlink($filePath);
        }
    }

    /**
     * @param string|null $fileName
     *
     * @return string|null
     */
    public function getMimeType(string $fileName = null): ?string
    {
        $filePath = sprintf('%s/%s', $this->kernel->getPublicDir(), $fileName);
        if ($fileName && file_exists($filePath)) {
            return mime_content_type($filePath);
        }

        return null;
    }
}
