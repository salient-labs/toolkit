<?php declare(strict_types=1);

namespace Lkrms\Sync\Support;

use JsonSerializable;
use Lkrms\Concept\TypedCollection;
use Lkrms\Console\Catalog\ConsoleLevel as Level;
use Lkrms\Console\ConsoleFormatter as Formatter;
use Lkrms\Sync\Catalog\SyncErrorType as ErrorType;
use Lkrms\Utility\Convert;

/**
 * A collection of SyncError objects
 *
 * @extends TypedCollection<int,SyncError>
 */
final class SyncErrorCollection extends TypedCollection implements JsonSerializable
{
    protected const ITEM_CLASS = SyncError::class;

    /**
     * Get a JSON:API-compatible representation of the errors
     *
     */
    public function toSummary(): array
    {
        $summary = [];
        /** @var SyncError $error */
        foreach ($this as $error) {
            $code = $error->getCode();
            $message = $error->Message;
            $key = "$code.$message";
            if (is_null($summary[$key] ?? null)) {
                $summary[$key] = [
                    'code' => $code,
                    'title' => ErrorType::toName($error->ErrorType),
                    'detail' => $message,
                    'meta' => [
                        'level' => Level::toName($error->Level),
                        'count' => 0,
                        'seen' => 0,
                    ],
                ];
            }
            $summary[$key]['meta']['values'][] = Convert::flatten($error->Values);
            $summary[$key]['meta']['count']++;
            $summary[$key]['meta']['seen'] += $error->Count;
        }
        ksort($summary);

        return array_values($summary);
    }

    public function __toString(): string
    {
        $summary = $this->toSummary();
        $lines = [];
        $separator = "\n" . '  ';
        foreach ($summary as $error) {
            $lines[] = sprintf(
                "~~{~~_%d_~~}~~ ___%s___ ~~[~~__%s__~~]~~ ~~(~~_'%s'_~~)~~:",
                $error['meta']['seen'],
                $error['title'],
                $error['meta']['level'],
                $error['detail']
            ) . $separator . implode(
                $separator,
                array_map(
                    fn($v) => Formatter::escapeTags(json_encode($v)),
                    $error['meta']['values']
                )
            );
        }

        return implode("\n", $lines);
    }

    public function jsonSerialize(): array
    {
        return $this->toSummary();
    }
}
