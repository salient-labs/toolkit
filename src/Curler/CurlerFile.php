<?php

declare(strict_types=1);

namespace Lkrms\Curler;
use CURLFile;
use Exception;

class CurlerFile
{
    private $FileName;

    private $PostName;

    private $MimeType;

    /**
     * @param string $filename Path to file to upload.
     * @param string $postname Name to give file when uploading. (default: basename($filename))
     * @param string $mimetype MIME type of file. (default: mime_content_type($filename))
     */
    public function __construct(string $filename, string $postname = null, string $mimetype = null)
    {
        if (empty($filename) || ! is_file($filename) || ! ($filename = realpath($filename)))
        {
            throw new Exception('Invalid filename');
        }

        $this->FileName  = $filename;
        $this->PostName  = is_null($postname) ? basename($filename) : $postname;
        $this->MimeType  = is_null($mimetype) ? mime_content_type($filename) : $mimetype;
    }

    public function GetCurlFile() : CURLFile
    {
        return new CURLFile($this->FileName, $this->MimeType, $this->PostName);
    }
}

