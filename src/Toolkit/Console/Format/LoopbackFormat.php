<?php declare(strict_types=1);

namespace Salient\Console\Format;

use Salient\Contract\Console\Format\TagAttributesInterface;

/**
 * Reapplies inline formatting tags as they originally appeared
 *
 * @api
 */
class LoopbackFormat extends AbstractFormat
{
    use EncloseTrait;

    /**
     * @inheritDoc
     */
    public function apply(string $string, $attributes = null): string
    {
        if ($string === '') {
            return '';
        }

        $before = $this->Before;
        $after = $this->After;

        if (
            $attributes instanceof TagAttributesInterface
            && $attributes->getOpenTag() !== ''
        ) {
            $before = $attributes->getOpenTag();
            $after = $before === '<' ? '>' : $attributes->getOpenTag();
        }

        if ($before === '##') {
            return $this->enclose($string, '## ', ' ##');
        }

        if ($this->Before === '```') {
            return $attributes instanceof TagAttributesInterface
                ? $this->enclose(
                    $string,
                    $before . $attributes->getInfoString() . "\n",
                    "\n" . $attributes->getIndent() . $after,
                )
                : $this->enclose(
                    $string,
                    $before . "\n",
                    "\n" . $after,
                );
        }

        return $this->enclose($string, $before, $after);
    }

    /**
     * @inheritDoc
     */
    protected static function getTagFormats(): ?TagFormats
    {
        return (new TagFormats(false))
            ->withFormat(self::TAG_HEADING, new self('***', '***'))
            ->withFormat(self::TAG_BOLD, new self('**', '**'))
            ->withFormat(self::TAG_ITALIC, new self('*', '*'))
            ->withFormat(self::TAG_UNDERLINE, new self('<', '>'))
            ->withFormat(self::TAG_LOW_PRIORITY, new self('~~', '~~'))
            ->withFormat(self::TAG_CODE_SPAN, new self('`', '`'))
            ->withFormat(self::TAG_CODE_BLOCK, new self('```', '```'));
    }
}
