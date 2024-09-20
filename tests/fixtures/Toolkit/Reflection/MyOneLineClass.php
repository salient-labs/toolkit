<?php declare(strict_types=1);

namespace Salient\Tests\Reflection { trait MyOneLineTrait { use MyOtherOneLineTrait { Method as MyMethod; } } }
namespace Salient\Tests\Reflection { trait MyOtherOneLineTrait { public function Method(): void {} } class MyOneLineClass { use MyOneLineTrait { MyMethod as MyOneLineMethod; } } }
