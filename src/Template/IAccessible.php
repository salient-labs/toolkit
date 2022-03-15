<?php

declare(strict_types=1);

namespace Lkrms\Template;

/**
 * Base interface for IGettable and ISettable
 *
 * @package Lkrms
 * @see IGettable
 * @see ISettable
 */
interface IAccessible
{
    /**
     * Make all protected properties accessible
     *
     * This is the default for {@see TGettable}.
     */
    public const ALLOW_ALL_PROTECTED = null;

    /**
     * Make all non-public properties inaccessible
     *
     * This is the default for {@see TSettable}.
     */
    public const ALLOW_NONE = [];
}

