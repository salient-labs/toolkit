<?php

declare(strict_types=1);

namespace Lkrms\Curler;

use CURLFile;
use RuntimeException;

/**
 * File upload helper
 *
 * @package Lkrms
 */
class CurlerFile
{
    private $Filename;

    private $PostFilename;

    private $MimeType;

    /**
     * @param string $filename File to upload.
     * @param string $postFilename Name to give file when uploading (default:
     * `basename($filename)`).
     * @param string $mimeType MIME type of file (default:
     * `mime_content_type($filename)`).
     */
    public function __construct(string $filename, string $postFilename = null, string $mimeType = null)
    {
        if (!is_file($filename) || ($filename = realpath($filename)) === false)
        {
            throw new RuntimeException("File not found: $filename");
        }

        $this->Filename     = $filename;
        $this->PostFilename = $postFilename ?: basename($filename);
        $this->MimeType     = $mimeType ?: mime_content_type($filename);
    }

    public function getCurlFile(): CURLFile
    {
        return new CURLFile($this->Filename, $this->MimeType, $this->PostFilename);
    }
}
