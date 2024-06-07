<?php declare(strict_types=1);

namespace Salient\Db;

use Salient\Contract\Core\Readable;
use Salient\Core\Concern\ReadsProtectedProperties;
use Salient\Utility\Env;
use Salient\Utility\Format;
use Salient\Utility\Get;
use ADOConnection;
use RuntimeException;
use UnexpectedValueException;

/**
 * Creates connections to databases
 *
 * @property-read string $Name
 * @property-read DbDriver::* $Driver
 * @property-read string|null $Dsn
 * @property-read string|null $Hostname
 * @property-read int|null $Port
 * @property-read string|null $Username
 * @property-read string|null $Password
 * @property-read string|null $Database
 * @property-read string|null $Schema
 */
final class DbConnector implements Readable
{
    use ReadsProtectedProperties;

    /** @var string */
    protected $Name;
    /** @var DbDriver::* */
    protected $Driver;
    /** @var string|null */
    protected $Dsn;
    /** @var string|null */
    protected $Hostname;
    /** @var int|null */
    protected $Port;
    /** @var string|null */
    protected $Username;
    /** @var string|null */
    protected $Password;
    /** @var string|null */
    protected $Database;
    /** @var string|null */
    protected $Schema;
    /** @var string */
    private $AdodbDriver;

    /**
     * Creates a new DbConnector object
     *
     * @param string $name The connection name used in the following environment
     * variables:
     * - `<name>_driver`: ignored if `$driver` is set, otherwise required
     * - `<name>_dsn`: if set, other values may be ignored
     * - `<name>_hostname`
     * - `<name>_port`: do not set if `<name>_hostname` specifies a port number
     * - `<name>_username`
     * - `<name>_password`
     * - `<name>_database`
     * - `<name>_schema`
     * @param DbDriver::*|null $driver If `null`, the environment variable
     * `<name>_driver` is used.
     */
    public function __construct(string $name, ?int $driver = null)
    {
        $driver ??= Get::arrayKey(Env::get("{$name}_driver"));
        /** @var (int&DbDriver::*)|string $driver */
        if (is_string($driver)) {
            /** @var (int&DbDriver::*) */
            $driver = DbDriver::fromName($driver);
        }

        $this->Name = $name;
        $this->Driver = $driver;
        $this->Dsn = Env::getNullable("{$name}_dsn", null);
        $this->Hostname = Env::getNullable("{$name}_hostname", null);
        $this->Port = Env::getNullableInt("{$name}_port", null);
        $this->Username = Env::getNullable("{$name}_username", null);
        $this->Password = Env::getNullable("{$name}_password", null);
        $this->Database = Env::getNullable("{$name}_database", null);
        $this->Schema = Env::getNullable("{$name}_schema", null);

        $this->AdodbDriver = DbDriver::toAdodbDriver($driver);
    }

    /**
     * @param array<string,string|int|bool|null> $attributes
     */
    private function getConnectionString(array $attributes, bool $enclose = true): string
    {
        $parts = [];
        foreach ($attributes as $keyword => $value) {
            if (is_bool($value)) {
                $value = Format::bool($value);
            } else {
                $value = (string) $value;
            }
            if (($enclose && strpos($value, '}') !== false)
                    || (!$enclose && strpos($value, ';') !== false)) {
                throw new UnexpectedValueException(sprintf(
                    'Illegal character in attribute: %s',
                    $keyword,
                ));
            }
            $parts[] = sprintf(
                $enclose ? '%s={%s}' : '%s=%s',
                $keyword,
                $value,
            );
        }

        return implode(';', $parts);
    }

    public function getConnection(int $timeout = 15): ADOConnection
    {
        $db = $this->throwOnFailure(
            ADONewConnection($this->AdodbDriver),
            'Error connecting to database',
        );

        $db->SetFetchMode(ADODB_FETCH_ASSOC);

        switch ($this->Driver) {
            case DbDriver::DB2:
                if (!Env::has('DB2CODEPAGE')) {
                    // 1208 = UTF-8 encoding of Unicode
                    Env::set('DB2CODEPAGE', '1208');
                }

                if ($this->Dsn !== null) {
                    $db->Connect($this->Dsn);
                } else {
                    $db->Connect(
                        $this->getConnectionString([
                            'driver' => Env::get('odbc_db2_driver', 'Db2'),
                            'hostname' => $this->Hostname,
                            'protocol' => 'tcpip',
                            'port' => $this->Port,
                            'database' => $this->Database,
                            'uid' => $this->Username,
                            'pwd' => $this->Password,
                            'connecttimeout' => $timeout,
                        ], false)
                    );
                }

                if ($this->Schema !== null) {
                    $db->Execute(
                        'SET SCHEMA = ' . $db->Param('schema'),
                        ['schema' => $this->Schema]
                    );
                }
                break;

            case DbDriver::MSSQL:
                $db->setConnectionParameter('CharacterSet', 'UTF-8');
                $db->setConnectionParameter(
                    'TrustServerCertificate',
                    Env::getBool('mssql_trust_server_certificate', false),
                );
                $db->setConnectionParameter('LoginTimeout', $timeout);
                // No break
            default:
                $db->Connect(
                    (string) $this->Hostname,
                    (string) $this->Username,
                    (string) $this->Password,
                    (string) $this->Database,
                );
                break;
        }

        return $db;
    }

    /**
     * @template T
     *
     * @param T $result
     * @param string|int|float ...$args
     * @return (T is false ? never : T)
     * @phpstan-param T|false $result
     * @phpstan-return ($result is false ? never : T)
     */
    private static function throwOnFailure($result, string $message, ...$args)
    {
        if ($result === false) {
            throw new RuntimeException(sprintf($message, ...$args));
        }

        return $result;
    }
}
