<?php declare(strict_types=1);

namespace Salient\Sync\Support;

use Salient\Collection\AbstractTypedCollection;
use Salient\Console\ConsoleFormatter as Formatter;
use Salient\Contract\Console\ConsoleMessageType as MessageType;
use Salient\Contract\Core\MessageLevel as Level;
use Salient\Contract\Sync\SyncErrorType as ErrorType;
use Salient\Core\Facade\Console;
use Salient\Sync\SyncError;
use Salient\Utility\Arr;
use Salient\Utility\Inflect;
use JsonSerializable;

/**
 * A collection of SyncError objects
 *
 * @extends AbstractTypedCollection<int,SyncError>
 */
final class SyncErrorCollection extends AbstractTypedCollection implements JsonSerializable
{
    private int $ErrorCount = 0;
    private int $WarningCount = 0;

    public function getErrorCount(): int
    {
        return $this->ErrorCount;
    }

    public function getWarningCount(): int
    {
        return $this->WarningCount;
    }

    /**
     * Get a JSON:API-compatible representation of errors in the collection
     * after grouping them by level, type and message
     *
     * @return array<array{code:string,title:string,detail:string,meta:array{level:string,count:int,seen:int,values:list<mixed[]|object|int|float|string|bool|null>}}>
     */
    public function getSummary(): array
    {
        foreach ($this as $error) {
            $code = $error->getCode();
            $message = $error->Message;
            $key = "$code.$message";

            $summary[$key] ??= [
                'code' => $code,
                'title' => ErrorType::toName($error->ErrorType),
                'detail' => $message,
                'meta' => [
                    'level' => Level::toName($error->Level),
                    'count' => 0,
                    'seen' => 0,
                ],
            ];

            /** @var mixed[]|object|int|float|string|bool|null */
            $values = Arr::unwrap($error->Values);
            $summary[$key]['meta']['values'][] = $values;
            $summary[$key]['meta']['count']++;
            $summary[$key]['meta']['seen'] += $error->Count;
        }

        return array_values(Arr::sortByKey($summary ?? []));
    }

    /**
     * Get a human-readable representation of errors in the collection after
     * grouping them by level, type and message
     */
    public function getSummaryText(bool $withMarkup = false): string
    {
        $format = $withMarkup
            ? "~~{~~_%d_~~}~~ ___%s___ ~~[~~__%s__~~]~~ ~~(~~_'%s'_~~)~~:\n  %s"
            : "{%d} %s [%s] ('%s'):\n  %s";
        foreach ($this->getSummary() as $error) {
            $values = Arr::toScalars($error['meta']['values']);

            if ($withMarkup) {
                foreach ($values as $key => $value) {
                    $values[$key] = Formatter::escapeTags((string) $value);
                }
            }

            $lines[] = sprintf(
                $format,
                $error['meta']['seen'],
                $error['title'],
                $error['meta']['level'],
                $error['detail'],
                implode("\n  ", $values),
            );
        }

        return implode("\n", $lines ?? []);
    }

    /**
     * Write errors in the collection to the console after grouping them by
     * level, type and message
     *
     * Output is written to the console with level:
     * - NOTICE if no errors have been recorded
     * - ERROR if one or more recorded errors have level ERROR or higher
     * - WARNING if all recorded errors have level WARNING or lower
     *
     * @return $this
     */
    public function reportErrors(string $successText = 'No sync errors recorded')
    {
        if (!$this->ErrorCount && !$this->WarningCount) {
            Console::info($successText);
            return $this;
        }

        $level = $this->ErrorCount
            ? Level::ERROR
            : Level::WARNING;

        // Print a message with level ERROR or WARNING as appropriate without
        // Console recording an additional error or warning
        Console::message(
            $level,
            Inflect::format(
                $this->ErrorCount,
                '{{#}} sync {{#:error}}%s recorded:',
                $this->WarningCount
                    ? Inflect::format($this->WarningCount, ' and {{#}} {{#:warning}}')
                    : ''
            ),
            null,
            MessageType::STANDARD,
            null,
            false,
        );

        Console::print(
            $this->getSummaryText(true),
            $level,
            MessageType::UNFORMATTED,
        );

        return $this;
    }

    public function __toString(): string
    {
        return $this->getSummaryText();
    }

    /**
     * @return array<array{code:string,title:string,detail:string,meta:array{level:string,count:int,seen:int,values:list<mixed[]|object|int|float|string|bool|null>}}>
     */
    public function jsonSerialize(): array
    {
        return $this->getSummary();
    }

    protected function handleItemsReplaced(): void
    {
        $errors = 0;
        $warnings = 0;
        foreach ($this->Items as $error) {
            switch ($error->Level) {
                case Level::EMERGENCY:
                case Level::ALERT:
                case Level::CRITICAL:
                case Level::ERROR:
                    $errors++;
                    break;
                case Level::WARNING:
                    $warnings++;
                    break;
            }
        }

        $this->ErrorCount = $errors;
        $this->WarningCount = $warnings;
    }
}
