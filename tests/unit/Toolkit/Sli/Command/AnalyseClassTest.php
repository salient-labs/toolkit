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
        string $output,
        int $exitStatus,
        array $args
    ): void {
        File::chdir(self::getFixturesPath(__CLASS__));
        $this->assertCommandProduces(
            self::normaliseConsoleOutput($output),
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
     * @return array<array{string,int,string[]}>
     */
    public static function runProvider(): array
    {
        $dir = self::getFixturesPath(__CLASS__);
        return [
            [
                File::getContents("$dir/output0.json"),
                0,
                ['.'],
            ],
            [
                File::getContents("$dir/output1.csv"),
                0,
                ['--format', 'csv', '.'],
            ],
            [
                File::getContents("$dir/output2.md"),
                0,
                ['--format', 'md', '.'],
            ],
            [
                File::getContents("$dir/output3.md"),
                0,
                ['--format', 'md', '--skip', 'meta', '.'],
            ],
        ];
    }
}
