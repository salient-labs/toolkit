<?php

declare(strict_types=1);

namespace Lkrms\Db;

use ADOConnection;
use Lkrms\Concern\TFullyReadable;
use Lkrms\Contract\IReadable;
use Lkrms\Facade\Env;
use Lkrms\Facade\Format;
use Lkrms\Facade\Test;
use UnexpectedValueException;

/**
 * Creates connections to databases
 *
 * @property-read string $Name
 * @property-read int $Driver
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
     * @internal
     * @var string
     */
    protected $Name;

    /**
     * @internal
     * @var int
     */
    protected $Driver;

    /**
     * @internal
     * @var string|null
     */
    protected $Dsn;

    /**
     * @internal
     * @var string|null
     */
    protected $Hostname;

    /**
     * @internal
     * @var int|null
     */
    protected $Port;

    /**
     * @internal
     * @var string|null
     */
    protected $Username;

    /**
     * @internal
     * @var string|null
     */
    protected $Password;

    /**
     * @internal
     * @var string|null
     */
    protected $Database;

    /**
     * @internal
     * @var string|null
     */
    protected $Schema;

    /**
     * @var string
     */
    private $AdodbDriver;

    /**
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
     * @param int|null $driver A {@see DbDriver} value, or `null` to use
     * environment variable `<name>_driver`.
     */
    public function __construct(
        string $name,
        int $driver = null
    ) {
        $driver = is_null($driver) ? Env::get("{$name}_driver") : $driver;

        $this->Name     = $name;
        $this->Driver   = Test::isIntValue($driver) ? (int)$driver : DbDriver::fromName($driver);
        $this->Dsn      = Env::get("{$name}_dsn", null);
        $this->Hostname = Env::get("{$name}_hostname", null);
        $this->Port     = (int)Env::get("{$name}_port", null) ?: null;
        $this->Username = Env::get("{$name}_username", null);
        $this->Password = Env::get("{$name}_password", null);
        $this->Database = Env::get("{$name}_database", null);
        $this->Schema   = Env::get("{$name}_schema", null);

        $this->AdodbDriver = DbDriver::toAdodbDriver($this->Driver);
    }

    /**
     * @param array<string,string> $attributes
     */
    private function getConnectionString(array $attributes, bool $enclose = true): string
    {
        $parts = [];
        foreach ($attributes as $keyword => $value)
        {
            if (($enclose && strpos($value, "}") !== false) ||
                (!$enclose && strpos($value, ";") !== false))
            {
                throw new UnexpectedValueException("Illegal character in attribute: $keyword");
            }
            $parts[] = "$keyword=" . ($enclose ? "{{$value}}" : "$value");
        }
        return implode(";", $parts);
    }

    public function getConnection(): ADOConnection
    {
        $db = ADONewConnection($this->AdodbDriver);
        $db->SetFetchMode(ADODB_FETCH_ASSOC);

        switch ($this->Driver)
        {
            case DbDriver::DB2:
                $db->Connect($this->Dsn ?: $this->getConnectionString([
                    "driver"   => Env::get("odbc_db2_driver", "Db2"),
                    "hostname" => $this->Hostname,
                    "protocol" => "tcpip",
                    "port"     => (string)$this->Port,
                    "database" => $this->Database,
                    "uid"      => $this->Username,
                    "pwd"      => $this->Password,
                ], false));
                if ($this->Schema)
                {
                    $db->Execute("SET SCHEMA = " . $db->Param("schema"),
                        ["schema" => $this->Schema]);
                }
                break;

            case DbDriver::MSSQL:
                $db->setConnectionParameter("TrustServerCertificate",
                    Format::yn(!Env::get("mssql_validate_server", null)));
            default:
                $db->Connect(
                    $this->Hostname,
                    $this->Username,
                    $this->Password,
                    $this->Database
                );
                break;
        }
        return $db;
    }
}
