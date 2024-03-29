<?php

namespace EWZ\SymfonyAdminBundle\FileUploader;

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class S3Uploader extends AbstractFileUploader
{
    /** @var S3Client */
    private $s3Client;

    /** @var string */
    private $s3Bucket;

    /** @var string */
    private $baseUrl;

    /**
     * @param S3Client            $s3Client
     * @param string              $s3Bucket
     * @param ValidatorInterface  $validator
     * @param TranslatorInterface $translator
     * @param string              $mimeTypesExtensions
     * @param array               $mimeTypesTypes
     * @param int                 $maxFilesize
     * @param string|null         $imageDriver
     * @param string|null         $baseUrl
     */
    public function __construct(
        S3Client $s3Client,
        string $s3Bucket,
        ValidatorInterface $validator,
        TranslatorInterface $translator,
        string $mimeTypesExtensions,
        array $mimeTypesTypes,
        int $maxFilesize,
        string $imageDriver = null,
        string $baseUrl = null
    ) {
        parent::__construct($validator, $translator, $mimeTypesExtensions, $mimeTypesTypes, $maxFilesize, $imageDriver);

        $this->s3Client = $s3Client;
        $this->s3Bucket = $s3Bucket;
        $this->baseUrl = $baseUrl;
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

        // generate filename
        $newFileName = sprintf('%s/%s', $directory, basename($fileName));

        // upload file to S3
        $result = $this->s3Client->putObject([
            'ACL' => 'public-read',
            'StorageClass' => 'REDUCED_REDUNDANCY',
            'Body' => file_get_contents($fileName),
            'Bucket' => $this->s3Bucket,
            'Key' => $newFileName,
            'ContentType' => mime_content_type($fileName),
        ]);

        return $this->baseUrl
            ? sprintf('%s/%s', $this->baseUrl, $this->cleanFileName($newFileName))
            : $result['ObjectURL'];
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

        if ($prefix) {
            $prefix .= '__';
        }

        // generate filename
        $fileName = sprintf('%s/%s%s.%s', $directory, $prefix, Uuid::v4(), $file->guessExtension());

        // upload file to S3
        $result = $this->s3Client->putObject([
            'ACL' => 'public-read',
            'StorageClass' => 'REDUCED_REDUNDANCY',
            'SourceFile' => $file->getPathname(),
            'Bucket' => $this->s3Bucket,
            'Key' => $fileName,
            'ContentType' => $file->getMimeType(),
        ]);

        // delete old file (if exists)
        if ($oldFileName) {
            $this->delete($oldFileName);
        }

        return $this->baseUrl
            ? sprintf('%s/%s', $this->baseUrl, $this->cleanFileName($fileName))
            : $result['ObjectURL'];
    }

    /**
     * {@inheritdoc}
     */
    public function move(string $fromDir, string $toDir, string $fileName): ?string
    {
        $fileName = $this->cleanFileName($fileName);

        // set key path
        $sourceKey = $fileName;

        // generate source
        $source = sprintf('%s/%s', $this->s3Bucket, $sourceKey);

        // generate filename
        $fileName = str_replace($fromDir, $toDir, $fileName);

        $result = $this->s3Client->copyObject([
            'ACL' => 'public-read',
            'StorageClass' => 'REDUCED_REDUNDANCY',
            'CopySource' => $source,
            'Bucket' => $this->s3Bucket,
            'Key' => $fileName,
        ]);

        // delete old file (if exists)
        $this->delete($sourceKey);

        return $this->baseUrl
            ? sprintf('%s/%s', $this->baseUrl, $this->cleanFileName($fileName))
            : $result['ObjectURL'];
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $fileName): void
    {
        $fileName = $this->cleanFileName($fileName);

        $this->s3Client->deleteObject([
            'Bucket' => $this->s3Bucket,
            'Key' => $fileName,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getMimeType(string $fileName): ?string
    {
        $fileName = $this->cleanFileName($fileName);

        try {
            $result = $this->s3Client->getObject([
                'Bucket' => $this->s3Bucket,
                'Key' => $fileName,
            ]);
        } catch (S3Exception $e) {
            return null;
        }

        if (isset($result['NoSuchKey'])) {
            return null;
        }

        return $result['ContentType'];
    }

    /**
     * {@inheritdoc}
     */
    public function getContent(string $fileName): ?string
    {
        $fileName = $this->cleanFileName($fileName);

        try {
            $result = $this->s3Client->getObject([
                'Bucket' => $this->s3Bucket,
                'Key' => $fileName,
            ]);
        } catch (S3Exception $e) {
            return null;
        }

        if (isset($result['NoSuchKey'])) {
            return null;
        }

        return $result['Body'];
    }

    /**
     * {@inheritdoc}
     */
    public function getFileSize(string $fileName): ?int
    {
        $fileName = $this->cleanFileName($fileName);

        try {
            $result = $this->s3Client->getObject([
                'Bucket' => $this->s3Bucket,
                'Key' => $fileName,
            ]);
        } catch (S3Exception $e) {
            return null;
        }

        if (isset($result['NoSuchKey'])) {
            return null;
        }

        return $result['ContentLength'];
    }

    /**
     * @return string
     */
    private function cleanFileName(string $fileName): string
    {
        $fileName = parse_url($fileName, \PHP_URL_PATH);

        if ('/' === substr($fileName, 0, 1)) {
            $fileName = substr($fileName, 1);
        }

        return $fileName;
    }
}
