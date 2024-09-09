<?php declare(strict_types=1);

namespace Salient\Contract\Polyfill;

/**
 * Abstraction of the streamWrapper prototype class described in the PHP manual
 *
 * Methods that should not be defined by wrappers that do not support them are
 * declared in {@see FilesystemStreamWrapperInterface}, which may be implemented
 * by wrappers that extend this class.
 *
 * @api
 *
 * @link https://www.php.net/manual/en/class.streamwrapper.php
 */
abstract class StreamWrapper implements StreamWrapperInterface
{
    /** @var resource|null */
    public $context;
}
