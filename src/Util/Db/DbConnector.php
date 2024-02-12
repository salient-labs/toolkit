<?php declare(strict_types=1);

namespace Lkrms\Db;

use Lkrms\Concern\TFullyReadable;
use Lkrms\Contract\IReadable;
use Lkrms\Exception\UnexpectedValueException;
use Lkrms\Utility\Env;
use Lkrms\Utility\Format;
use Lkrms\Utility\Get;
use ADOConnection;

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
final class DbConnector implements IReadable
{
    use TFullyReadable;

    /**
     * @var string
     */
    protected $Name;

    /**
     * @var DbDriver::*
     */
    protected $Driver;

    /**
     * @var string|null
     */
    protected $Dsn;

    /**
     * @var string|null
     */
    protected $Hostname;

    /**
     * @var int|null
     */
    protected $Port;

    /**
     * @var string|null
     */
    protected $Username;

    /**
     * @var string|null
     */
    protected $Password;

    /**
     * @var string|null
     */
    protected $Database;

    /**
     * @var string|null
     */
    protected $Schema;

    /**
     * @var string
     */
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
    public function __construct(string $name, int $driver = null)
    {
        $driver = $driver === null
            ? Get::apparent(Env::get("{$name}_driver"), false, false)
            : $driver;

        $this->Name = $name;
        $this->Driver = is_int($driver) ? $driver : DbDriver::fromName($driver);
        $this->Dsn = Env::getNullable("{$name}_dsn", null);
        $this->Hostname = Env::getNullable("{$name}_hostname", null);
        $this->Port = Env::getNullableInt("{$name}_port", null);
        $this->Username = Env::getNullable("{$name}_username", null);
        $this->Password = Env::getNullable("{$name}_password", null);
        $this->Database = Env::getNullable("{$name}_database", null);
        $this->Schema = Env::getNullable("{$name}_schema", null);

        $this->AdodbDriver = DbDriver::toAdodbDriver($this->Driver);
    }

    /**
     * @param array<string,string|int|bool> $attributes
     */
    private function getConnectionString(array $attributes, bool $enclose = true): string
    {
        $parts = [];
        foreach ($attributes as $keyword => $value) {
            if (is_int($value)) {
                $value = (string) $value;
            } elseif (is_bool($value)) {
                $value = Format::bool($value);
            }
            if (($enclose && strpos($value, '}') !== false) ||
                    (!$enclose && strpos($value, ';') !== false)) {
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
        $db = ADONewConnection($this->AdodbDriver);
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
                    // @phpstan-ignore-next-line
                    Env::getBool('mssql_trust_server_certificate', false),
                );
                // @phpstan-ignore-next-line
                $db->setConnectionParameter('LoginTimeout', $timeout);
                // No break
            default:
                $db->Connect(
                    $this->Hostname,
                    $this->Username,
                    $this->Password,
                    $this->Database,
                );
                break;
        }

        return $db;
    }
}
