<?php declare(strict_types=1);

namespace Salient\Contract\Http;

use Psr\Http\Message\RequestInterface;

/** @api */
interface HttpRequestInterface extends HttpMessageInterface, RequestInterface {}
