<?php declare(strict_types=1);

namespace Salient\Sli\Internal\Data;

use Salient\PHPDoc\PHPDoc;

/**
 * @internal
 */
trait HasPHPDoc
{
    /**
     * @return static
     */
    private function applyPHPDoc(PHPDoc $phpDoc): self
    {
        $this->Summary = $phpDoc->getSummary();
        $this->Api = $phpDoc->hasTag('api');
        $this->Internal = $phpDoc->hasTag('internal');
        $this->Deprecated = $phpDoc->hasTag('deprecated');
        $this->HasDocComment = !$phpDoc->isEmpty();

        return $this;
    }
}
