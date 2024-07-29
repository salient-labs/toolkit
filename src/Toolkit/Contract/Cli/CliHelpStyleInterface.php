<?php declare(strict_types=1);

namespace Salient\Contract\Cli;

use Salient\Contract\Console\ConsoleFormatterInterface as FormatterInterface;
use Salient\Contract\Core\Immutable;

/**
 * Formatting instructions for help messages
 *
 * @api
 */
interface CliHelpStyleInterface extends Immutable
{
    /**
     * Get output formatter
     */
    public function getFormatter(): FormatterInterface;

    /**
     * Get output width in columns after subtracting any margin widths
     */
    public function getWidth(): ?int;

    /**
     * Get string to insert before and after bold text
     */
    public function getBold(): string;

    /**
     * Get string to insert before and after italic text
     */
    public function getItalic(): string;

    /**
     * Get escape character
     */
    public function getEscape(): string;

    /**
     * Get string to insert before synopsis
     */
    public function getSynopsisPrefix(): string;

    /**
     * Get string to insert between wrapped synopsis lines
     */
    public function getSynopsisNewline(): string;

    /**
     * Get string to insert between long synopsis lines after wrapping, or an
     * empty string to disable synopsis soft-wrapping
     */
    public function getSynopsisSoftNewline(): string;

    /**
     * Check whether to collapse non-mandatory options to "[options]" when a
     * wrapped synopsis breaks over multiple lines
     */
    public function getCollapseSynopsis(): bool;

    /**
     * Get an instance where non-mandatory options are collapsed to "[options]"
     * when a wrapped synopsis breaks over multiple lines
     *
     * @return static
     */
    public function withCollapseSynopsis(bool $value = true);

    /**
     * Get string to insert before each option line
     */
    public function getOptionIndent(): string;

    /**
     * Get string to insert before each option
     */
    public function getOptionPrefix(): string;

    /**
     * Get string to insert before each option description
     */
    public function getOptionDescriptionPrefix(): string;

    /**
     * Get option visibility flag to require
     *
     * @return CliOptionVisibility::*
     */
    public function getVisibility(): int;

    /**
     * Reflow and optionally indent help message text
     */
    public function prepareHelp(string $text, string $indent = ''): string;

    /**
     * Combine help message sections into one help message
     *
     * @param array<CliHelpSectionName::*|string,string> $sections
     */
    public function buildHelp(array $sections): string;

    /**
     * Escape special characters in a string
     */
    public function maybeEscapeTags(string $string): string;
}
