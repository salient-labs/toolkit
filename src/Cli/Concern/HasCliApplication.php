<?php declare(strict_types=1);

namespace Lkrms\Cli\Concern;

use Lkrms\Cli\CliApplication;

trait HasCliApplication
{
    /**
     * @var CliApplication
     */
    protected $App;

    public function __construct(CliApplication $app)
    {
        $this->App = $app;
    }

    final public function app(): CliApplication
    {
        return $this->App;
    }

    final public function container(): CliApplication
    {
        return $this->App;
    }
}
