<?php declare(strict_types=1);

namespace Salient\Tests\Sli\Command;

use Salient\Sli\Command\AnalyseClass;
use Salient\Tests\CommandTestCase;
use Salient\Utility\File;

/**
 * @covers \Salient\Sli\Command\AnalyseClass
 * @covers \Salient\Sli\Internal\Data\ClassData
 * @covers \Salient\Sli\Internal\Data\ConstantData
 * @covers \Salient\Sli\Internal\Data\MethodData
 * @covers \Salient\Sli\Internal\Data\NamespaceData
 * @covers \Salient\Sli\Internal\Data\PropertyData
 */
final class AnalyseClassTest extends CommandTestCase
{
    /**
     * @dataProvider runProvider
     * @backupGlobals enabled
     *
     * @param string[] $args
     */
    public function testRun(
        string $filename,
        int $exitStatus,
        array $args,
        ?string $dir = null
    ): void {
        if ($dir !== null) {
            File::chdir($dir);
        }
        $this->assertCommandProduces(
            self::normaliseConsoleOutput(File::getContents($filename)),
            $exitStatus,
            AnalyseClass::class,
            $args,
            [],
            false,
            false,
            null,
            1,
            null,
            true,
        );
    }

    /**
     * @return array<array{string,int,string[],3?:string|null}>
     */
    public static function runProvider(): array
    {
        $dir = self::getFixturesPath(__CLASS__);
        return [
            [
                "$dir/output0.json",
                0,
                ['--json', '.'],
                $dir,
            ],
            [
                "$dir/output1.csv",
                0,
                ['--csv', '--skip', 'inherited', '.'],
                $dir,
            ],
            [
                "$dir/output2.md",
                0,
                ['--markdown', '.'],
                $dir,
            ],
            [
                "$dir/output3.md",
                0,
                ['--markdown', '--skip', 'meta,from', '.'],
                $dir,
            ],
        ];
    }
}
