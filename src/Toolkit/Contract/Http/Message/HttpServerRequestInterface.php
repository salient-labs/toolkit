<?php declare(strict_types=1);

namespace Salient\Contract\Http\Message;

use Psr\Http\Message\ServerRequestInterface as PsrServerRequestInterface;

interface HttpServerRequestInterface extends HttpRequestInterface, PsrServerRequestInterface {}
