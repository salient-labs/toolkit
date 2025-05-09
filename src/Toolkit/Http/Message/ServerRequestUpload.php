<?php declare(strict_types=1);

namespace Salient\Http\Message;

use Psr\Http\Message\StreamInterface as PsrStreamInterface;
use Psr\Http\Message\UploadedFileInterface as PsrUploadedFileInterface;
use Salient\Http\Exception\UploadFailedException;
use Salient\Http\HttpUtil;
use Salient\Utility\Exception\InvalidArgumentTypeException;
use Salient\Utility\File;

/**
 * @api
 */
class ServerRequestUpload implements PsrUploadedFileInterface
{
    /**
     * @var array<int,string>
     */
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

    private PsrStreamInterface $Stream;
    private string $File;
    private ?int $Size;
    private int $Error;
    private ?string $ClientFilename;
    private ?string $ClientMediaType;
    private bool $IsMoved = false;

    /**
     * @api
     *
     * @param PsrStreamInterface|resource|string $resource Stream or filename.
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

        if ($error === \UPLOAD_ERR_OK) {
            if ($resource instanceof PsrStreamInterface) {
                $this->Stream = $resource;
            } elseif (File::isStream($resource)) {
                $this->Stream = new Stream($resource);
            } elseif (is_string($resource)) {
                $this->File = $resource;
            } else {
                throw new InvalidArgumentTypeException(
                    1,
                    'resource',
                    PsrStreamInterface::class . '|resource|string',
                    $resource,
                );
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function getStream(): PsrStreamInterface
    {
        $this->assertIsValid();

        return $this->Stream ?? new Stream(File::open($this->File, 'r'));
    }

    /**
     * @inheritDoc
     */
    public function moveTo(string $targetPath): void
    {
        $this->assertIsValid();

        if (isset($this->File)) {
            $result = \PHP_SAPI === 'cli'
                ? @rename($this->File, $targetPath)
                : @move_uploaded_file($this->File, $targetPath);
            if ($result === false) {
                $error = error_get_last();
                throw new UploadFailedException($error['message'] ?? sprintf(
                    'Error moving %s to %s',
                    $this->File,
                    $targetPath,
                ));
            }
        } else {
            $target = new Stream(File::open($targetPath, 'w'));
            HttpUtil::copyStream($this->Stream, $target);
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
            throw new UploadFailedException(sprintf(
                'Upload failed (%d: %s)',
                $this->Error,
                static::ERROR_MESSAGE[$this->Error] ?? '',
            ));
        }

        if ($this->IsMoved) {
            throw new UploadFailedException('Upload already moved');
        }
    }
}
