<?php declare(strict_types=1);

namespace Salient\Curler;

use Salient\Http\HttpMultipartStreamPart;
use Salient\Utility\File;
use InvalidArgumentException;

/**
 * A file to upload to an HTTP endpoint
 *
 * @api
 */
class CurlerFile extends HttpMultipartStreamPart
{
    /**
     * @api
     *
     * @param string|null $uploadFilename Default: `basename($filename)`
     * @param string|null $mediaType Default: `mime_content_type($filename)`,
     * `application/octet-stream` on failure.
     */
    public function __construct(
        string $filename,
        ?string $uploadFilename = null,
        ?string $mediaType = null,
        ?string $fallbackFilename = null,
        ?string $name = null
    ) {
        if (!is_file($filename)) {
            throw new InvalidArgumentException(sprintf(
                'File not found: %s',
                $filename,
            ));
        }

        parent::__construct(
            File::open($filename, 'r'),
            $name,
            $uploadFilename ?? basename($filename),
            self::getFileMediaType($filename, $mediaType),
            $fallbackFilename,
        );
    }
}
