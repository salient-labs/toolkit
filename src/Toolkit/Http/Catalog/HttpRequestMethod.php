<?php declare(strict_types=1);

namespace Lkrms\Http\Catalog;

use Salient\Core\AbstractEnumeration;

/**
 * HTTP request methods
 *
 * @extends AbstractEnumeration<string>
 */
final class HttpRequestMethod extends AbstractEnumeration
{
    public const GET = 'GET';

    public const HEAD = 'HEAD';

    public const POST = 'POST';

    public const PUT = 'PUT';

    public const PATCH = 'PATCH';

    public const DELETE = 'DELETE';

    public const CONNECT = 'CONNECT';

    public const OPTIONS = 'OPTIONS';

    public const TRACE = 'TRACE';
}
