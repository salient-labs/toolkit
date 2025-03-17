<?php declare(strict_types=1);

namespace Salient\Console\Format;

use Salient\Contract\Console\Format\MessageFormatInterface as Format;
use Salient\Contract\Core\Immutable;
use Salient\Core\Concern\ImmutableTrait;

/**
 * @api
 */
class MessageFormats implements Immutable
{
    use ImmutableTrait;

    /** @var array<Format::LEVEL_*,array<Format::TYPE_*,Format>> */
    private array $Formats = [];
    private NullMessageFormat $FallbackFormat;

    /**
     * @api
     */
    public function __construct()
    {
        $this->FallbackFormat = new NullMessageFormat();
    }

    /**
     * Get an instance where a format is assigned to the given message levels
     * and types
     *
     * @param array<Format::LEVEL_*>|Format::LEVEL_* $level
     * @param array<Format::TYPE_*>|Format::TYPE_* $type
     * @return static
     */
    public function withFormat($level, $type, Format $format)
    {
        $formats = $this->Formats;
        $levels = (array) $level;
        $types = (array) $type;
        foreach ($levels as $level) {
            foreach ($types as $type) {
                $formats[$level][$type] = $format;
            }
        }
        return $this->with('Formats', $formats);
    }

    /**
     * Get the format assigned to a given message level and type
     *
     * @param Format::LEVEL_* $level
     * @param Format::TYPE_* $type
     */
    public function getFormat(int $level, int $type): Format
    {
        return $this->Formats[$level][$type] ?? $this->FallbackFormat;
    }
}
