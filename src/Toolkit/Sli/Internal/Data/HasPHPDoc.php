<?php declare(strict_types=1);

namespace Salient\Sli\Internal\Data;

use Salient\Contract\Console\ConsoleInterface;
use Salient\PHPDoc\PHPDoc;

/**
 * @internal
 */
trait HasPHPDoc
{
    private static function checkPHPDoc(
        PHPDoc $phpDoc,
        ?ConsoleInterface $console
    ): void {
        if (!$console || !$phpDoc->hasErrors()) {
            return;
        }
        foreach ($phpDoc->getErrors() as $error) {
            $console->warn('PHPDoc error:', $error->getMessage());
        }
    }

    /**
     * @return static
     */
    private function applyPHPDoc(PHPDoc $phpDoc): self
    {
        $original = $phpDoc->getOriginal();

        $this->Summary = $phpDoc->getSummary();
        $this->Description = $phpDoc->getDescription();
        $this->SummaryInherited = $this->Summary !== null && $original->getSummary() === null;
        $this->DescriptionInherited = $this->Description !== null && $original->getDescription() === null;
        $this->Api = $phpDoc->hasTag('api');
        $this->Internal = $phpDoc->hasTag('internal');
        $this->Deprecated = $phpDoc->hasTag('deprecated');
        $this->HasDocComment = !$phpDoc->isEmpty();

        return $this;
    }
}
