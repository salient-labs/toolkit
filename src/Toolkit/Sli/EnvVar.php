<?php declare(strict_types=1);

namespace Salient\Sli;

use Salient\Core\AbstractDictionary;

/**
 * Environment variables used by sli commands
 *
 * @extends AbstractDictionary<string>
 */
final class EnvVar extends AbstractDictionary
{
    public const NS_DEFAULT = 'DEFAULT_NAMESPACE';
    public const NS_PROVIDER = 'PROVIDER_NAMESPACE';
    public const NS_BUILDER = 'BUILDER_NAMESPACE';
    public const NS_FACADE = 'FACADE_NAMESPACE';
    public const NS_TESTS = 'TESTS_NAMESPACE';
}
