<?php declare(strict_types=1);

namespace Salient\Core\Internal;

use SQLite3;

/**
 * @internal
 */
final class StoreState
{
    public bool $IsOpen;
    public SQLite3 $Db;
    public string $Filename;
    public bool $IsTemporary;
    public bool $HasTransaction;
}
