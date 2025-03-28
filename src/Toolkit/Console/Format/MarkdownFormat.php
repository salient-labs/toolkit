<?php declare(strict_types=1);

namespace Salient\Console\Format;

use Salient\Console\ConsoleUtil;
use Salient\Contract\Console\Format\TagAttributesInterface;

/**
 * Applies Markdown formatting
 *
 * @api
 */
class MarkdownFormat extends AbstractFormat
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

        $tag = $attributes instanceof TagAttributesInterface
            ? $attributes->getOpenTag()
            : '';

        if ($tag === '##') {
            return $this->enclose($string, '## ', '');
        }

        if (
            ($tag === '_' || $tag === '*') && (
                !$attributes instanceof TagAttributesInterface
                || !$attributes->hasChildren()
            )
        ) {
            /** @var non-empty-string */
            $string = ConsoleUtil::removeEscapes($string);
            return $this->enclose($string, '`', '`');
        }

        if ($before === '`') {
            return $this->enclose($string, '**`', '`**');
        }

        if ($before === '```') {
            return $attributes instanceof TagAttributesInterface
                ? $this->enclose(
                    $string,
                    $tag . $attributes->getInfoString() . "\n",
                    "\n" . $attributes->getIndent() . $tag,
                )
                : $this->enclose(
                    $string,
                    $tag . "\n",
                    "\n" . $tag,
                );
        }

        return $this->enclose($string, $before, $after);
    }

    /**
     * @inheritDoc
     */
    protected static function getTagFormats(): ?TagFormats
    {
        return (new TagFormats(false, true))
            ->withFormat(self::TAG_HEADING, new self('***', '***'))
            ->withFormat(self::TAG_BOLD, new self('**', '**'))
            ->withFormat(self::TAG_ITALIC, new self('*', '*'))
            ->withFormat(self::TAG_UNDERLINE, new self('*<u>', '</u>*'))
            ->withFormat(self::TAG_LOW_PRIORITY, new self('<small>', '</small>'))
            ->withFormat(self::TAG_CODE_SPAN, new self('`', '`'))
            ->withFormat(self::TAG_CODE_BLOCK, new self('```', '```'));
    }
}
