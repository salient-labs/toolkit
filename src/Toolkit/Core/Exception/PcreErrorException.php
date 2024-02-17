<?php declare(strict_types=1);

namespace Salient\Core\Exception;

use Lkrms\Exception\Exception;
use Salient\Core\Utility\Json;

/**
 * @api
 */
class PcreErrorException extends Exception
{
    protected int $PcreError;
    protected string $PcreErrorName;
    protected string $Function;

    /**
     * @var array<string,callable(array<array-key,string|null>):string>|string[]|string
     */
    protected $Pattern;

    /**
     * @var string[]|string
     */
    protected $Subject;

    /**
     * @var array<int,string>
     */
    private static array $ErrorNameMap;

    /**
     * @param array<string,callable(array<array-key,string|null>):string>|string[]|string $pattern
     * @param string[]|string $subject
     */
    public function __construct(int $pcreError, string $function, $pattern, $subject)
    {
        if (!isset(self::$ErrorNameMap)) {
            self::$ErrorNameMap = [];
            foreach (get_defined_constants(true)['pcre'] as $name => $value) {
                if (substr($name, -6) !== '_ERROR') {
                    continue;
                }
                self::$ErrorNameMap[$value] = $name;
            }
        }

        $this->PcreError = $pcreError;
        $this->PcreErrorName = self::$ErrorNameMap[$pcreError];
        $this->Function = $function;
        $this->Pattern = $pattern;
        $this->Subject = $subject;

        parent::__construct(sprintf('Call to %s() failed with %s', $function, $this->PcreErrorName));
    }

    public function getDetail(): array
    {
        return [
            'PcreError' => (string) $this->PcreError,
            'Pattern' => is_scalar($this->Pattern) ? $this->Pattern : Json::prettyPrint($this->Pattern),
            'Subject' => is_scalar($this->Subject) ? $this->Subject : Json::prettyPrint($this->Subject),
        ];
    }
}
