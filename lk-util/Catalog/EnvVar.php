<?php declare(strict_types=1);

namespace Lkrms\LkUtil\Catalog;

use Lkrms\Concept\Dictionary;

/**
 * Environment variables used by lk-util commands
 *
 * @extends Dictionary<string>
 */
final class EnvVar extends Dictionary
{
    public const NS_DEFAULT = 'DEFAULT_NAMESPACE';

    public const NS_PROVIDER = 'PROVIDER_NAMESPACE';

    public const NS_BUILDER = 'BUILDER_NAMESPACE';

    public const NS_FACADE = 'FACADE_NAMESPACE';

    public const NS_TESTS = 'TESTS_NAMESPACE';
}
