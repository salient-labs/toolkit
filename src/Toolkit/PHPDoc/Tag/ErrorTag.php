<?php declare(strict_types=1);

namespace Salient\PHPDoc\Tag;

/**
 * @api
 */
class ErrorTag extends GenericTag
{
    protected string $Message;

    /**
     * Creates a new ErrorTag object
     */
    public function __construct(
        string $tag,
        string $message,
        ?string $description = null,
        ?string $class = null,
        ?string $member = null
    ) {
        parent::__construct($tag, $description, $class, $member);
        $this->Message = $this->filterString($message, 'message');
    }

    /**
     * Get the error message associated with the tag
     */
    public function getMessage(): string
    {
        return $this->Message;
    }
}
