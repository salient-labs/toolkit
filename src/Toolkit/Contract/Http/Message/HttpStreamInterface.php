<?php declare(strict_types=1);

namespace Salient\Contract\Http\Message;

use Psr\Http\Message\StreamInterface;
use Stringable;

interface HttpStreamInterface extends
    StreamInterface,
    Stringable {}
