<?php

namespace EWZ\SymfonyAdminBundle\FileUploader;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class FileUploader extends AbstractFileUploader
{
    /** @var KernelInterface */
    private $kernel;

    /** @var int */
    private $folderPermissions;

    /** @var int */
    private $filePermissions;

    /**
     * @param KernelInterface     $kernel
     * @param ValidatorInterface  $validator
     * @param TranslatorInterface $translator
     * @param string              $mimeTypesExtensions
     * @param array               $mimeTypesTypes
     * @param int                 $maxFilesize
     * @param string|null         $imageDriver
     * @param int|null            $folderPermissions
     * @param int|null            $filePermissions
     */
    public function __construct(
        KernelInterface $kernel,
        ValidatorInterface $validator,
        TranslatorInterface $translator,
        string $mimeTypesExtensions,
        array $mimeTypesTypes,
        int $maxFilesize,
        string $imageDriver = null,
        int $folderPermissions = 0755,
        int $filePermissions = 0644
    ) {
        parent::__construct($validator, $translator, $mimeTypesExtensions, $mimeTypesTypes, $maxFilesize, $imageDriver);

        $this->kernel = $kernel;
        $this->folderPermissions = $folderPermissions;
        $this->filePermissions = $filePermissions;
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
        $this->createFolder($directory);

        // generate filename
        $newFileName = sprintf('%s/%s', $directory, basename($fileName));

        // move file to ..
        rename($fileName, $this->getFilePath($newFileName));

        // fix permissions
        $this->setFilePermissions($newFileName);

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
        $this->createFolder($directory);

        if ($prefix) {
            $prefix .= '__';
        }

        // generate filename
        $fileName = sprintf('%s/%s%s.%s', $directory, $prefix, Uuid::v4(), $file->guessExtension());

        // move file to ..
        rename($file->getPathname(), $this->getFilePath($fileName));

        // fix permissions
        $this->setFilePermissions($fileName);

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
        if (!file_exists($orgFilePath = $this->getFilePath($fileName))) {
            return null;
        }

        // create folder if doesn't exists
        $this->createFolder($toDir);

        // generate filename
        $fileName = str_replace($fromDir, $toDir, $fileName);

        // move file to ..
        rename($orgFilePath, $this->getFilePath($fileName));

        // fix permissions
        $this->setFilePermissions($fileName);

        return $fileName;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $fileName): void
    {
        $filePath = $this->getFilePath($fileName);

        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getContent(string $fileName): ?string
    {
        $filePath = $this->getFilePath($fileName);

        return file_exists($filePath)
            ? file_get_contents($filePath)
            : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getMimeType(string $fileName): ?string
    {
        $filePath = $this->getFilePath($fileName);

        return file_exists($filePath)
            ? mime_content_type($filePath)
            : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getFileSize(string $fileName): ?int
    {
        $filePath = $this->getFilePath($fileName);

        return file_exists($filePath)
            ? filesize($filePath)
            : null;
    }

    /**
     * @param string $dirName
     */
    private function createFolder(string $dirName): void
    {
        // create folder if doesn't exists
        if (!is_dir($path = sprintf('%s/public/%s', $this->kernel->getProjectDir(), $dirName))) {
            mkdir($path, $this->folderPermissions, true);
        }
    }

    /**
     * @param string $dirName
     *
     * @return string
     */
    private function getFilePath(string $dirName): string
    {
        return sprintf('%s/public/%s', $this->kernel->getProjectDir(), $dirName);
    }

    /**
     * @param string $fileName
     */
    private function setFilePermissions(string $fileName): void
    {
        try {
            $filePath = $this->getFilePath($fileName);

            $userOwner = null;
            $groupOwner = null;

            $director = str_replace(basename($filePath), null, $filePath);
            if (is_dir($director)) {
                $userOwner = posix_getpwuid(fileowner($director))['name'] ?? null;
                $groupOwner = posix_getgrgid(filegroup($director))['name'] ?? null;
            }

            if (file_exists($filePath) && $userOwner && $groupOwner) {
                chmod($filePath, $this->filePermissions);
                chown($filePath, $userOwner);
                chgrp($filePath, $groupOwner);
            }
        } catch (FileException $e) {
        }
    }
}
