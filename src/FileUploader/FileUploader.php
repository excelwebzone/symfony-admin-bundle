<?php

namespace EWZ\SymfonyAdminBundle\FileUploader;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class FileUploader extends AbstractFileUploader
{
    /** @var KernelInterface */
    private $kernel;

    /**
     * @param KernelInterface     $kernel
     * @param ValidatorInterface  $validator
     * @param TranslatorInterface $translator
     * @param string              $mimeTypesExtensions
     * @param array               $mimeTypesTypes
     * @param int                 $maxFilesize
     * @param string|null         $imageDriver
     */
    public function __construct(
        KernelInterface $kernel,
        ValidatorInterface $validator,
        TranslatorInterface $translator,
        string $mimeTypesExtensions,
        array $mimeTypesTypes,
        int $maxFilesize,
        string $imageDriver = null
    ) {
        $this->kernel = $kernel;

        parent::__construct($validator, $translator, $mimeTypesExtensions, $mimeTypesTypes, $maxFilesize, $imageDriver);
    }

    /**
     * {@inheritdoc}
     */
    public function create(string $fileName, string $directory, string $fileContent = null): ?string
    {
        // local file missing and no content provided
        if (!file_exists($fileName) && empty($fileContent)) {
            return null;
        }

        // write content to local file (override if exists)
        if ($fileContent) {
            file_put_contents($fileName, $fileContent);
        }

        // create folder if doesn't exists
        if (!is_dir($filePath = sprintf('%s/public/%s', $this->kernel->getProjectDir(), $directory))) {
            mkdir($filePath, 0777, true);
        }

        // generate filename
        $newFileName = sprintf('%s/%s', $directory, basename($fileName));

        // move file to ..
        rename($fileName, sprintf('%s/public/%s', $this->kernel->getProjectDir(), $newFileName));

        return $newFileName;
    }

    /**
     * {@inheritdoc}
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

        // fix image orientation
        $this->fixOrientate($file);

        // create folder if doesn't exists
        if (!is_dir($filePath = sprintf('%s/public/%s', $this->kernel->getProjectDir(), $directory))) {
            mkdir($filePath, 0777, true);
        }

        if ($prefix) {
            $prefix .= '__';
        }

        // generate filename
        $fileName = sprintf('%s/%s%s.%s', $directory, $prefix, Uuid::v4(), $file->guessExtension());

        // move file to ..
        rename($file->getPathname(), sprintf('%s/public/%s', $this->kernel->getProjectDir(), $fileName));

        // delete old file (if exists)
        if ($oldFileName) {
            $this->delete($oldFileName);
        }

        return $fileName;
    }

    /**
     * {@inheritdoc}
     */
    public function move(string $fromDir, string $toDir, string $fileName): ?string
    {
        if (!file_exists($orgFilePath = sprintf('%s/public/%s', $this->kernel->getProjectDir(), $fileName))) {
            return null;
        }

        // create folder if doesn't exists
        if (!is_dir($filePath = sprintf('%s/public/%s', $this->kernel->getProjectDir(), $toDir))) {
            mkdir($filePath, 0777, true);
        }

        // generate filename
        $fileName = str_replace($fromDir, $toDir, $fileName);

        // move file to ..
        rename($orgFilePath, sprintf('%s/public/%s', $this->kernel->getProjectDir(), $fileName));

        return $fileName;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $fileName): void
    {
        $filePath = sprintf('%s/public/%s', $this->kernel->getProjectDir(), $fileName);

        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getContent(string $fileName): ?string
    {
        $filePath = sprintf('%s/public/%s', $this->kernel->getProjectDir(), $fileName);

        return file_exists($filePath)
            ? file_get_contents($filePath)
            : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getMimeType(string $fileName): ?string
    {
        $filePath = sprintf('%s/public/%s', $this->kernel->getProjectDir(), $fileName);

        return file_exists($filePath)
            ? mime_content_type($filePath)
            : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getFileSize(string $fileName): ?int
    {
        $filePath = sprintf('%s/public/%s', $this->kernel->getProjectDir(), $fileName);

        return file_exists($filePath)
            ? filesize($filePath)
            : null;
    }
}
