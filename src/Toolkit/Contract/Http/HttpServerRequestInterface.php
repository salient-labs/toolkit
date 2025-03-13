<?php declare(strict_types=1);

namespace Salient\Contract\Http;

use Psr\Http\Message\ServerRequestInterface;

interface HttpServerRequestInterface extends HttpRequestInterface, ServerRequestInterface {}
