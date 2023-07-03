<?php declare(strict_types=1);

namespace Lkrms\Tests\Container;

use Lkrms\Cli\Contract\ICliApplication;
use Lkrms\Container\Application;
use Lkrms\Container\Container;
use Lkrms\Contract\IApplication;
use Lkrms\Contract\IContainer;
use Lkrms\Exception\ContainerServiceNotFoundException;
use Psr\Container\ContainerInterface;

final class ApplicationTest extends \Lkrms\Tests\TestCase
{
    public function testBindContainer()
    {
        $app = new Application();
        $this->assertSame($app, $app->get(ContainerInterface::class));
        $this->assertSame($app, $app->get(IContainer::class));
        $this->assertSame($app, $app->get(IApplication::class));
        $this->assertSame($app, $app->get(Container::class));
        $this->assertSame($app, $app->get(Application::class));
        $this->expectException(ContainerServiceNotFoundException::class);
        $app->get(ICliApplication::class);
    }
}
