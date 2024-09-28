<?php declare(strict_types=1);

namespace Salient\Sli;

/**
 * Environment variables used by sli commands
 */
interface EnvVar
{
    public const NS_DEFAULT = 'DEFAULT_NAMESPACE';
    public const NS_PROVIDER = 'PROVIDER_NAMESPACE';
    public const NS_BUILDER = 'BUILDER_NAMESPACE';
    public const NS_FACADE = 'FACADE_NAMESPACE';
    public const NS_TESTS = 'TESTS_NAMESPACE';
}
