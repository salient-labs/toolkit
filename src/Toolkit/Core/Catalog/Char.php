<?php declare(strict_types=1);

namespace Salient\Core\Catalog;

use Salient\Core\AbstractDictionary;

/**
 * Character sequences
 *
 * @api
 *
 * @extends AbstractDictionary<string>
 */
final class Char extends AbstractDictionary
{
    public const ALPHA = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    public const LOWER = 'abcdefghijklmnopqrstuvwxyz';
    public const UPPER = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    public const NUMERIC = '0123456789';
    public const ALPHANUMERIC = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
}
