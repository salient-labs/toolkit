<?php declare(strict_types=1);

namespace Salient\Http\Exception;

use Salient\Contract\Http\Exception\StreamEncapsulationException as StreamEncapsulationExceptionInterface;

class StreamEncapsulationException extends StreamException implements StreamEncapsulationExceptionInterface {}
