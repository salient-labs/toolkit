<?php declare(strict_types=1);

namespace Salient\Contract\Http;

use Psr\Http\Message\ResponseInterface;

/**
 * @api
 */
interface HttpResponseInterface extends HttpMessageInterface, ResponseInterface {}
