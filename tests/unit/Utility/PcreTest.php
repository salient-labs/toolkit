<?php declare(strict_types=1);

namespace Lkrms\Tests\Utility;

use Lkrms\Exception\PcreErrorException;
use Lkrms\Utility\Pcre;

final class PcreTest extends \Lkrms\Tests\TestCase
{
    public function testMatch(): void
    {
        $this->expectException(PcreErrorException::class);
        $this->expectExceptionMessage('Call to preg_match() failed with PREG_BACKTRACK_LIMIT_ERROR');
        // This was taken from PHP's manual
        Pcre::match('/(?:\D+|<\d+>)*[!?]/', 'foobar foobar foobar');
    }

    public function testMatchAll(): void
    {
        $this->expectException(PcreErrorException::class);
        $this->expectExceptionMessage('Call to preg_match_all() failed with PREG_BACKTRACK_LIMIT_ERROR');
        Pcre::matchAll('/(?:\D+|<\d+>)*[!?]/', 'foobar foobar foobar');
    }

    public function testReplace(): void
    {
        $this->expectException(PcreErrorException::class);
        $this->expectExceptionMessage('Call to preg_replace() failed with PREG_BACKTRACK_LIMIT_ERROR');
        Pcre::replace('/(?:\D+|<\d+>)*[!?]/', '', 'foobar foobar foobar');
    }

    public function testReplaceCallback(): void
    {
        $this->expectException(PcreErrorException::class);
        $this->expectExceptionMessage('Call to preg_replace_callback() failed with PREG_BACKTRACK_LIMIT_ERROR');
        Pcre::replaceCallback('/(?:\D+|<\d+>)*[!?]/', fn() => '', 'foobar foobar foobar');
    }

    public function testReplaceCallbackArray(): void
    {
        $this->expectException(PcreErrorException::class);
        $this->expectExceptionMessage('Call to preg_replace_callback_array() failed with PREG_BACKTRACK_LIMIT_ERROR');
        Pcre::replaceCallbackArray(['/(?:\D+|<\d+>)*[!?]/' => fn() => ''], 'foobar foobar foobar');
    }
}
