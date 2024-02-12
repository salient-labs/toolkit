<?php declare(strict_types=1);

namespace Lkrms\Curler;

use CURLFile;
use LogicException;

/**
 * File upload helper
 */
final class CurlerFile
{
    /**
     * @var string
     */
    private $Filename;

    /**
     * @var string
     */
    private $PostFilename;

    /**
     * @var string|null
     */
    private $MimeType;

    /**
     * Creates a new CurlerFile object
     *
     * @param string $postFilename Filename used in upload data. If `null`, the
     * basename of `$filename` is used.
     * @param string $mimeType If `null`, {@see mime_content_type()} is used to
     * detect the MIME type of `$filename`.
     */
    public function __construct(
        string $filename,
        string $postFilename = null,
        string $mimeType = null
    ) {
        if (!is_file($filename)) {
            throw new LogicException(sprintf('File not found: %s', $filename));
        }

        if ($postFilename === null) {
            $postFilename = basename($filename);
        }

        if ($mimeType === null) {
            $mimeType = mime_content_type($filename);
            if ($mimeType === false) {
                $mimeType = null;
            }
        }

        $this->Filename = $filename;
        $this->PostFilename = $postFilename;
        $this->MimeType = $mimeType;
    }

    /**
     * @internal
     */
    public function getCurlFile(): CURLFile
    {
        return new CURLFile($this->Filename, $this->MimeType, $this->PostFilename);
    }
}
