<?php declare(strict_types=1);

namespace Salient\Sync\Support;

use Salient\Collection\AbstractTypedCollection;
use Salient\Console\ConsoleFormatter as Formatter;
use Salient\Contract\Console\ConsoleMessageType as MessageType;
use Salient\Contract\Console\ConsoleWriterInterface;
use Salient\Contract\Core\MessageLevel as Level;
use Salient\Contract\Sync\SyncErrorCollectionInterface;
use Salient\Contract\Sync\SyncErrorInterface;
use Salient\Contract\Sync\SyncErrorType as ErrorType;
use Salient\Core\Facade\Console;
use Salient\Utility\Arr;
use Salient\Utility\Inflect;
use Salient\Utility\Reflect;

/**
 * @extends AbstractTypedCollection<int,SyncErrorInterface>
 */
final class SyncErrorCollection extends AbstractTypedCollection implements SyncErrorCollectionInterface
{
    private int $ErrorCount = 0;
    private int $WarningCount = 0;

    /**
     * @inheritDoc
     */
    public function getErrorCount(): int
    {
        return $this->ErrorCount;
    }

    /**
     * @inheritDoc
     */
    public function getWarningCount(): int
    {
        return $this->WarningCount;
    }

    /**
     * @inheritDoc
     */
    public function getSummary(): array
    {
        foreach ($this->Items as $error) {
            $code = $error->getCode();
            $format = $error->getFormat();
            $key = "$code.$format";

            $summary[$key] ??= [
                'code' => $code,
                'title' => Reflect::getConstantName(ErrorType::class, $error->getType()),
                'detail' => $format,
                'meta' => [
                    'level' => Reflect::getConstantName(Level::class, $error->getLevel()),
                    'count' => 0,
                    'seen' => 0,
                ],
            ];

            /** @var mixed[]|object|int|float|string|bool|null */
            $values = Arr::unwrap($error->getValues());
            $summary[$key]['meta']['values'][] = $values;
            $summary[$key]['meta']['count']++;
            $summary[$key]['meta']['seen'] += $error->getCount();
        }

        return array_values(Arr::sortByKey($summary ?? []));
    }

    /**
     * @inheritDoc
     */
    public function getSummaryText(): string
    {
        return $this->doGetSummaryText(false);
    }

    private function doGetSummaryText(bool $withMarkup): string
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
     * @inheritDoc
     */
    public function reportErrors(
        ?ConsoleWriterInterface $writer = null,
        string $successText = 'No sync errors recorded'
    ): void {
        $writer ??= Console::getInstance();

        if (!$this->ErrorCount && !$this->WarningCount) {
            $writer->info($successText);
            return;
        }

        $level = $this->ErrorCount
            ? Level::ERROR
            : Level::WARNING;

        $writer->message(
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
        );

        $writer->print(
            $this->doGetSummaryText(true),
            $level,
            MessageType::UNFORMATTED,
        );
    }

    /**
     * @inheritDoc
     */
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

    /**
     * @inheritDoc
     */
    protected function handleItemsReplaced(): void
    {
        $errors = 0;
        $warnings = 0;
        foreach ($this->Items as $error) {
            switch ($error->getLevel()) {
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
