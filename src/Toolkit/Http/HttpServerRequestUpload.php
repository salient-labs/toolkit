<?php declare(strict_types=1);

namespace Salient\Http;

use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use Salient\Http\Exception\UploadedFileException;
use Salient\Utility\Exception\InvalidArgumentTypeException;
use Salient\Utility\File;

/**
 * A PSR-7 uploaded file (incoming, server-side)
 *
 * @api
 */
class HttpServerRequestUpload implements UploadedFileInterface
{
    protected const ERROR_MESSAGE = [
        \UPLOAD_ERR_OK => 'There is no error, the file uploaded with success',
        \UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
        \UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
        \UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
        \UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        \UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
        \UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        \UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload',
    ];

    protected StreamInterface $Stream;
    protected string $File;
    protected ?int $Size;
    protected int $Error;
    protected ?string $ClientFilename;
    protected ?string $ClientMediaType;
    private bool $IsMoved = false;

    /**
     * Creates a new HttpServerRequestUpload object
     *
     * @param StreamInterface|resource|string $resource
     */
    public function __construct(
        $resource,
        ?int $size = null,
        int $error = \UPLOAD_ERR_OK,
        ?string $clientFilename = null,
        ?string $clientMediaType = null
    ) {
        $this->Size = $size;
        $this->Error = $error;
        $this->ClientFilename = $clientFilename;
        $this->ClientMediaType = $clientMediaType;

        if ($this->Error !== \UPLOAD_ERR_OK) {
            return;
        }

        if ($resource instanceof StreamInterface) {
            $this->Stream = $resource;
        } elseif (File::isStream($resource)) {
            $this->Stream = new HttpStream($resource);
        } elseif (is_string($resource)) {
            $this->File = $resource;
        } else {
            throw new InvalidArgumentTypeException(
                1,
                'resource',
                'StreamInterface|resource|string',
                $resource
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function getStream(): StreamInterface
    {
        $this->assertIsValid();

        return $this->Stream ?? new HttpStream(File::open($this->File, 'r'));
    }

    /**
     * @inheritDoc
     */
    public function moveTo(string $targetPath): void
    {
        $this->assertIsValid();

        if (isset($this->File)) {
            if (\PHP_SAPI === 'cli') {
                $result = @rename($this->File, $targetPath);
            } else {
                $result = @move_uploaded_file($this->File, $targetPath);
            }
            if ($result === false) {
                $error = error_get_last();
                throw new UploadedFileException($error['message'] ?? sprintf(
                    'Error moving uploaded file %s to %s',
                    $this->File,
                    $targetPath,
                ));
            }
        } else {
            $target = new HttpStream(File::open($targetPath, 'w'));
            HttpStream::copyToStream($this->Stream, $target);
        }

        $this->IsMoved = true;
    }

    /**
     * @inheritDoc
     */
    public function getSize(): ?int
    {
        return $this->Size;
    }

    /**
     * @inheritDoc
     */
    public function getError(): int
    {
        return $this->Error;
    }

    /**
     * @inheritDoc
     */
    public function getClientFilename(): ?string
    {
        return $this->ClientFilename;
    }

    /**
     * @inheritDoc
     */
    public function getClientMediaType(): ?string
    {
        return $this->ClientMediaType;
    }

    private function assertIsValid(): void
    {
        if ($this->Error !== \UPLOAD_ERR_OK) {
            throw new UploadedFileException(sprintf(
                'Upload failed (%d: %s)',
                $this->Error,
                static::ERROR_MESSAGE[$this->Error] ?? '',
            ));
        }

        if ($this->IsMoved) {
            throw new UploadedFileException('Uploaded file already moved');
        }
    }
}
