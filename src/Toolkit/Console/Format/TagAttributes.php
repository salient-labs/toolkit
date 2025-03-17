<?php declare(strict_types=1);

namespace Salient\Console\Format;

use Salient\Contract\Console\Format\TagAttributesInterface;

/**
 * @internal
 */
final class TagAttributes implements TagAttributesInterface
{
    /** @var self::TAG_* */
    private int $Tag;
    private string $OpenTag;
    private int $Depth;
    private bool $HasChildren;
    private ?string $Indent;
    private ?string $InfoString;

    /**
     * @param TagAttributes::TAG_* $tag
     */
    public function __construct(
        int $tag,
        string $openTag,
        int $depth = 0,
        bool $hasChildren = false,
        ?string $indent = null,
        ?string $infoString = null
    ) {
        $this->Tag = $tag;
        $this->OpenTag = $openTag;
        $this->Depth = $depth;
        $this->HasChildren = $hasChildren;
        $this->Indent = $indent;
        $this->InfoString = $infoString;
    }

    /**
     * @inheritDoc
     */
    public function getTag(): int
    {
        return $this->Tag;
    }

    /**
     * @inheritDoc
     */
    public function getOpenTag(): string
    {
        return $this->OpenTag;
    }

    /**
     * @inheritDoc
     */
    public function getDepth(): int
    {
        return $this->Depth;
    }

    /**
     * @inheritDoc
     */
    public function hasChildren(): bool
    {
        return $this->HasChildren;
    }

    /**
     * @inheritDoc
     */
    public function getIndent(): ?string
    {
        return $this->Indent;
    }

    /**
     * @inheritDoc
     */
    public function getInfoString(): ?string
    {
        return $this->InfoString;
    }
}
