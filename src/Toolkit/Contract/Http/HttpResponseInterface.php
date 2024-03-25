<?php declare(strict_types=1);

namespace Salient\Contract\Http;

use Psr\Http\Message\ResponseInterface;

interface HttpResponseInterface extends HttpMessageInterface, ResponseInterface {}
