<?php

declare(strict_types=1);

namespace Lkrms\Sync\Support;

use JsonSerializable;
use Lkrms\Concept\TypedCollection;
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
                    ],
                ];
            }
            $summary[$key]["meta"]["values"][] = Convert::toInner($error->Values);
            $summary[$key]["meta"]["count"]++;
        }
        ksort($summary);

        return array_values($summary);
    }

    public function jsonSerialize(): array
    {
        return $this->toSummary();
    }

}
