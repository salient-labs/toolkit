<?php declare(strict_types=1);

namespace Salient\Cli;

use Salient\Cli\Exception\CliInvalidArgumentsException;
use Salient\Utility\AbstractUtility;
use Salient\Utility\File;
use Salient\Utility\Json;
use JsonException;

/**
 * @api
 */
final class CliUtil extends AbstractUtility
{
    /**
     * Get data from a user-supplied JSON file
     *
     * If `$filename` is `"-"`, JSON is read from `STDIN`.
     *
     * @return mixed[]|object
     */
    public static function getJson(string $filename, bool $associative = true)
    {
        $json = File::getContents($filename === '-' ? 'php://stdin' : $filename);

        try {
            $json = $associative
                ? Json::objectAsArray($json)
                : Json::parse($json);
        } catch (JsonException $ex) {
            $message = $ex->getMessage();
            throw new CliInvalidArgumentsException(
                $filename === '-'
                    ? sprintf('invalid JSON: %s', $message)
                    : sprintf("invalid JSON in '%s': %s", $filename, $message)
            );
        }

        if (!is_array($json) && ($associative || !is_object($json))) {
            throw new CliInvalidArgumentsException(
                $filename === '-'
                    ? 'invalid payload'
                    : sprintf('invalid payload: %s', $filename)
            );
        }

        return $json;
    }
}
