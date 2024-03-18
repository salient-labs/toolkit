<?php declare(strict_types=1);

namespace Salient\Sync\Support;

use Salient\Collection\AbstractTypedCollection;
use Salient\Console\ConsoleFormatter as Formatter;
use Salient\Contract\Core\MessageLevel as Level;
use Salient\Contract\Sync\SyncErrorType as ErrorType;
use Salient\Core\Utility\Arr;
use Salient\Sync\SyncError;
use JsonSerializable;

/**
 * A collection of SyncError objects
 *
 * @extends AbstractTypedCollection<int,SyncError>
 */
final class SyncErrorCollection extends AbstractTypedCollection implements JsonSerializable
{
    /**
     * Get a JSON:API-compatible representation of the errors
     *
     * @return array<array{code:string,title:string,detail:string,meta:array{level:string,count:int,seen:int,values:list<mixed[]|object|int|float|string|bool|null>}}>
     */
    public function toSummary(): array
    {
        /** @var SyncError $error */
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
     * Get a human-readable representation of the errors
     */
    public function toString(bool $withMarkup = false): string
    {
        $b = $withMarkup ? '__' : '';
        $em = $withMarkup ? '_' : '';
        $d = $withMarkup ? '~~' : '';

        $summary = $this->toSummary();
        $lines = [];
        $separator = "\n  ";
        foreach ($summary as $error) {
            $values = Arr::toScalars($error['meta']['values']);

            if ($withMarkup) {
                foreach ($values as &$value) {
                    $value = Formatter::escapeTags((string) $value);
                }
            }

            $lines[] = sprintf(
                "{$d}{{$d}{$em}%d{$em}{$d}}{$d} {$b}{$em}%s{$em}{$b} {$d}[{$d}{$b}%s{$b}{$d}]{$d} {$d}({$d}{$em}'%s'{$em}{$d}){$d}:%s%s",
                $error['meta']['seen'],
                $error['title'],
                $error['meta']['level'],
                $error['detail'],
                $separator,
                implode($separator, $values),
            );
        }

        return implode("\n", $lines);
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * @return array<array{code:string,title:string,detail:string,meta:array{level:string,count:int,seen:int,values:mixed[]}}>
     */
    public function jsonSerialize(): array
    {
        return $this->toSummary();
    }
}
