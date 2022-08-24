<?php

declare(strict_types=1);

namespace Lkrms\Container;

use RuntimeException;

/**
 * A context-specific facade for a container
 *
 */
class ContextContainer extends Container
{
    /**
     * @var string|null
     */
    private $Context;

    protected static function create(Container $container, string $context): ContextContainer
    {
        $instance = new static();
        $instance->BackingContainer = $container;
        $instance->Context          = $context;
        return $instance;
    }

    private function assertHasContext()
    {
        if (is_null($this->Context))
        {
            throw new RuntimeException("Instance must be created by " . __CLASS__ . "::create()");
        }
    }

    private function getSubstitution(string $id)
    {
        $this->assertHasContext();
        $_id  = ltrim($id, "\\");
        $subs = $this->dice()->getRule($this->Context)["substitutions"] ?? [];
        return array_key_exists($_id, $subs) ? $subs[$_id] : $id;
    }

    public function get(string $id, ...$params)
    {
        return parent::get($this->getSubstitution($id), $params);
    }

    public function name(string $id): string
    {
        return parent::name($this->getSubstitution($id));
    }

    /**
     * @throws RuntimeException if `$constructParams` or `$shareInstances` are
     * not `null`, or if `$customRule` is not empty
     * @todo Add support for `$constructParams`, `$shareInstances` and
     * `$customRule`
     */
    public function bind(
        string $id,
        string $instanceOf     = null,
        array $constructParams = null,
        array $shareInstances  = null,
        array $customRule      = []
    ) {
        $this->assertHasContext();
        if (!is_null($constructParams) || !is_null($shareInstances) || !empty($customRule))
        {
            throw new RuntimeException(static::class . " only supports 'id' and 'instanceOf'");
        }
        $this->addRule(
            $this->Context, ["substitutions" => [$id => $instanceOf]]
        );
        return $this;
    }

    /**
     * @todo Implement this method
     */
    public function singleton(
        string $id,
        ?string $instanceOf     = null,
        ?array $constructParams = null,
        ?array $shareInstances  = null,
        array $customRule       = []
    ) {
        $this->assertHasContext();
        throw new RuntimeException(static::class . " has not implemented " . __FUNCTION__);
    }

}
