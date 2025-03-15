<?php declare(strict_types=1);

namespace Salient\Sync\Support;

use Salient\Collection\Collection;
use Salient\Contract\Console\ConsoleInterface;
use Salient\Contract\Sync\ErrorType;
use Salient\Contract\Sync\SyncErrorCollectionInterface;
use Salient\Contract\Sync\SyncErrorInterface;
use Salient\Contract\HasMessageLevel;
use Salient\Core\Facade\Console;
use Salient\Utility\Arr;
use Salient\Utility\Inflect;
use Salient\Utility\Reflect;

/**
 * @extends Collection<int,SyncErrorInterface>
 */
final class SyncErrorCollection extends Collection implements SyncErrorCollectionInterface
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
                    'level' => Reflect::getConstantName(HasMessageLevel::class, $error->getLevel()),
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
                    $values[$key] = Console::escape((string) $value);
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
        ?ConsoleInterface $console = null,
        string $successText = 'No sync errors recorded'
    ): void {
        $console ??= Console::getInstance();

        if (!$this->ErrorCount && !$this->WarningCount) {
            $console->info($successText);
            return;
        }

        $level = $this->ErrorCount
            ? Console::LEVEL_ERROR
            : Console::LEVEL_WARNING;

        $console->message(
            Inflect::format(
                $this->ErrorCount,
                '{{#}} sync {{#:error}}%s recorded:',
                $this->WarningCount
                    ? Inflect::format($this->WarningCount, ' and {{#}} {{#:warning}}')
                    : ''
            ),
            null,
            $level,
            Console::TYPE_STANDARD,
        );

        $console->print(
            $this->doGetSummaryText(true),
            $level,
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
                case Console::LEVEL_EMERGENCY:
                case Console::LEVEL_ALERT:
                case Console::LEVEL_CRITICAL:
                case Console::LEVEL_ERROR:
                    $errors++;
                    break;
                case Console::LEVEL_WARNING:
                    $warnings++;
                    break;
            }
        }

        $this->ErrorCount = $errors;
        $this->WarningCount = $warnings;
    }
}
