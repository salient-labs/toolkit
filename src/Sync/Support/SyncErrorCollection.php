<?php

declare(strict_types=1);

namespace Lkrms\Sync\Support;

use JsonSerializable;
use Lkrms\Concept\TypedCollection;
use Lkrms\Console\ConsoleFormatter as Formatter;
use Lkrms\Console\ConsoleLevel as Level;
use Lkrms\Facade\Convert;
use Lkrms\Sync\Support\SyncErrorType as ErrorType;

final class SyncErrorCollection extends TypedCollection implements JsonSerializable
{
    protected function getItemClass(): string
    {
        return SyncError::class;
    }

    /**
     * Return a JSON:API-compatible representation of the errors
     *
     */
    public function toSummary(): array
    {
        $summary = [];
        /** @var SyncError $error */
        foreach ($this as $error)
        {
            $code    = $error->getCode();
            $message = $error->Message;
            $key     = "$code.$message";
            if (is_null($summary[$key] ?? null))
            {
                $summary[$key] = [
                    "code"      => $code,
                    "title"     => ErrorType::toName($error->ErrorType),
                    "detail"    => $message,
                    "meta"      => [
                        "level" => Level::toName($error->Level),
                        "count" => 0,
                        "seen"  => 0,
                    ],
                ];
            }
            $summary[$key]["meta"]["values"][] = Convert::flatten($error->Values);
            $summary[$key]["meta"]["count"]++;
            $summary[$key]["meta"]["seen"] += $error->Count;
        }
        ksort($summary);

        return array_values($summary);
    }

    public function __toString(): string
    {
        $summary   = $this->toSummary();
        $lines     = [];
        $separator = PHP_EOL . "  ";
        foreach ($summary as $error)
        {
            $lines[] = sprintf(
                '~~{~~_%d_~~}~~ ___%s___ ~~[~~__%s__~~]~~ ~~(~~_\'%s\'_~~)~~:',
                $error["meta"]["seen"],
                $error["title"],
                $error["meta"]["level"],
                $error["detail"]
            ) . $separator . implode(
                $separator,
                array_map(
                    fn($v) => "`" . Formatter::escape(json_encode($v)) . "`",
                    $error["meta"]["values"]
                )
            );
        }

        return implode(PHP_EOL, $lines);
    }

    public function jsonSerialize(): array
    {
        return $this->toSummary();
    }

}
