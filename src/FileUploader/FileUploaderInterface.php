<?php

namespace EWZ\SymfonyAdminBundle\FileUploader;

use Symfony\Component\HttpFoundation\File\UploadedFile;

interface FileUploaderInterface
{
    /**
     * @param string $fileName
     * @param string $directory
     *
     * @return string|null
     */
    public function create(string $fileName, string $directory): ?string;

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
    public function upload(UploadedFile $file, string $directory, string $oldFileName = null, array $mimeTypes = [], bool $isPhoto = true, string $prefix = null): string;

    /**
     * @param string $fromDir
     * @param string $toDir
     * @param string $fileName
     *
     * @return string|null
     */
    public function move(string $fromDir, string $toDir, string $fileName): ?string;

    /**
     * @param string $fileName
     */
    public function delete(string $fileName): void;

    /**
     * @param string $fileName
     *
     * @return string|null
     */
    public function getMimeType(string $fileName): ?string;

    /**
     * @param string $fileName
     *
     * @return int|null
     */
    public function getFileSize(string $fileName): ?int;
}
