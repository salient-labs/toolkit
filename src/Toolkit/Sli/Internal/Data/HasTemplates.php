<?php declare(strict_types=1);

namespace Salient\Sli\Internal\Data;

use Salient\PHPDoc\PHPDoc;

/**
 * @internal
 */
trait HasTemplates
{
    /**
     * @return static
     */
    private function applyTemplates(PHPDoc $phpDoc): self
    {
        foreach ($phpDoc->getTemplates(false) as $name => $tag) {
            $template = '';
            if (($type = $tag->getType()) !== null) {
                $template .= " of {$type}";
            }
            if (($default = $tag->getDefault()) !== null) {
                $template .= " = {$default}";
            }
            $this->Templates[$name] = $template;
        }

        return $this;
    }
}
