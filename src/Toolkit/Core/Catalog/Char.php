<?php declare(strict_types=1);

namespace Salient\Core\Catalog;

use Salient\Core\AbstractDictionary;

/**
 * Character sequences
 *
 * @extends AbstractDictionary<string>
 */
final class Char extends AbstractDictionary
{
    public const ALPHABETIC = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    public const ALPHABETIC_LOWER = 'abcdefghijklmnopqrstuvwxyz';
    public const ALPHABETIC_UPPER = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    public const NUMERIC = '0123456789';
    public const ALPHANUMERIC = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
}
