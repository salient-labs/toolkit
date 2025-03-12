<?php declare(strict_types=1);

namespace Salient\Contract\Http;

use Psr\Http\Message\StreamInterface;
use Stringable;

interface HttpStreamInterface extends
    StreamInterface,
    Stringable {}
