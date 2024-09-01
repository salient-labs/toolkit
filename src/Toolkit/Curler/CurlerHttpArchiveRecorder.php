<?php declare(strict_types=1);

namespace Salient\Curler;

use Psr\Http\Message\RequestInterface;
use Salient\Contract\Curler\Event\CurlRequestEventInterface;
use Salient\Contract\Curler\Event\CurlResponseEventInterface;
use Salient\Contract\Curler\Event\ResponseCacheHitEventInterface;
use Salient\Core\Facade\Event;
use Salient\Http\HttpRequest;
use Salient\Utility\File;
use Salient\Utility\Json;
use Salient\Utility\Package;
use Closure;
use CurlHandle;
use DateTimeImmutable;
use LogicException;
use RuntimeException;
use stdClass;
use Stringable;

/**
 * Records Curler requests to an HTTP Archive (HAR) stream
 */
class CurlerHttpArchiveRecorder
{
    /** @var resource|null */
    protected $Stream;
    protected bool $IsCloseable;
    protected ?string $Uri;
    protected bool $IsRecording = false;
    /** @var int[] */
    protected array $ListenerIds;
    protected ?RequestInterface $LastRequest = null;
    protected float $LastRequestTime;
    protected int $EntryCount = 0;

    /**
     * Creates a new CurlerHttpArchiveRecorder object
     *
     * `$name` and `$version` are applied to the archive's `<creator>` object.
     * If both are `null`, they are replaced with the name and version of the
     * root package.
     *
     * @param Stringable|string|resource $resource
     */
    public function __construct(
        $resource,
        ?string $name = null,
        ?string $version = null
    ) {
        $uri = null;
        $this->Stream = File::maybeOpen($resource, 'w', $close, $uri);
        $this->IsCloseable = $close;
        $this->Uri = $uri;

        if ($name === null && $version === null) {
            $name = Package::name();
            $version = Package::version(true, true);
        }

        File::writeAll($this->Stream, sprintf(
            '{"log":{"version":"1.2","creator":%s,"pages":[],"entries":[',
            Json::stringify([
                'name' => $name ?? '',
                'version' => $version ?? '',
            ]),
        ), null, $this->Uri);
    }

    /**
     * @internal
     */
    public function __destruct()
    {
        $this->close();
    }

    public function close(): void
    {
        $this->stop();

        if (!$this->Stream) {
            return;
        }

        File::writeAll($this->Stream, ']}}', null, $this->Uri);

        if ($this->IsCloseable) {
            File::close($this->Stream, $this->Uri);
        }

        $this->Stream = null;
        $this->Uri = null;
    }

    public function start(): void
    {
        if ($this->IsRecording) {
            throw new LogicException('Already recording');
        }

        $this->assertIsValid();

        $this->IsRecording = true;

        $dispatcher = Event::getInstance();
        $this->ListenerIds = [
            $dispatcher->listen(Closure::fromCallable([$this, 'handleCurlRequest'])),
            $dispatcher->listen(Closure::fromCallable([$this, 'handleCurlResponse'])),
            $dispatcher->listen(Closure::fromCallable([$this, 'handleResponseCacheHit'])),
        ];
    }

    public function stop(): void
    {
        if (!$this->IsRecording) {
            return;
        }

        foreach ($this->ListenerIds as $id) {
            Event::removeListener($id);
        }
        unset($this->ListenerIds);

        $this->IsRecording = false;
    }

    protected function handleCurlRequest(CurlRequestEventInterface $event): void
    {
        $this->assertIsValid();

        $this->LastRequest = $event->getRequest();
        $this->LastRequestTime = microtime(true);
    }

    protected function handleCurlResponse(CurlResponseEventInterface $event): void
    {
        $this->assertIsValid();

        $request = $event->getRequest();
        if ($request !== $this->LastRequest) {
            throw new RuntimeException('Response does not match request');
        }
        $requestTime = $this->LastRequestTime;

        $this->LastRequest = null;
        unset($this->LastRequestTime);

        $handle = $event->getCurlHandle();

        /** @var int */
        $redirects = $this->getCurlInfo($handle, \CURLINFO_REDIRECT_COUNT);
        if ($redirects !== 0) {
            throw new RuntimeException('Redirects followed by cURL cannot be recorded');
        }

        // According to https://curl.se/libcurl/c/curl_easy_getinfo.html, cURL
        // transfers are processed in the following order, but connection reuse
        // can lead to variations:
        /** @var array{namelookup:int,connect:int,appconnect:int,pretransfer:int,starttransfer:int,transfer:int} */
        $times = [
            'namelookup' => $this->getCurlInfo($handle, \CURLINFO_NAMELOOKUP_TIME_T),
            'connect' => $this->getCurlInfo($handle, \CURLINFO_CONNECT_TIME_T),
            'appconnect' => $this->getCurlInfo($handle, \CURLINFO_APPCONNECT_TIME_T),
            'pretransfer' => $this->getCurlInfo($handle, \CURLINFO_PRETRANSFER_TIME_T),
            'starttransfer' => $this->getCurlInfo($handle, \CURLINFO_STARTTRANSFER_TIME_T),
            'transfer' => $this->getCurlInfo($handle, \CURLINFO_TOTAL_TIME_T),
        ];

        $totalTime = 0;
        $last = 0;
        foreach ($times as $time => $value) {
            $totalTime += $timings[$time] = (int) round(max(0, $value - $last) / 1000);
            $last = $value;
        }

        /** @var string */
        $scheme = $this->getCurlInfo($handle, \CURLINFO_SCHEME);
        $ssl = strcasecmp($scheme, 'https') === 0;
        /** @var string */
        $primaryIP = $this->getCurlInfo($handle, \CURLINFO_PRIMARY_IP);

        $entry = [
            // PHP 7.4 requires 6 digits after the decimal point
            'startedDateTime' => (new DateTimeImmutable(sprintf('@%.6f', $requestTime)))->format('Y-m-d\TH:i:s.vP'),
            // Sum of non-negative timings
            'time' => $totalTime,
            'request' => HttpRequest::fromPsr7($request)->jsonSerialize(),
            'response' => $event->getResponse()->jsonSerialize(),
            'cache' => new stdClass(),
            'timings' => [
                // Time in queue
                'blocked' => -1,
                // DNS resolution time
                'dns' => $timings['namelookup'],
                // Time creating connection, including SSL/TLS negotiation
                'connect' => $timings['connect'] + $timings['appconnect'],
                // Time sending request (must be non-negative)
                'send' => $timings['pretransfer'],
                // Time waiting for response (must be non-negative)
                'wait' => $timings['starttransfer'],
                // Time receiving response (must be non-negative)
                'receive' => $timings['transfer'],
                // Optional (1.2+) SSL/TLS negotiation time
                'ssl' => $ssl ? $timings['appconnect'] : -1,
            ],
            // Optional (1.2+)
            'serverIPAddress' => $primaryIP,
        ];

        $this->writeEntry($entry);
    }

    /**
     * @param CurlHandle|resource $handle
     * @return mixed[]|int|float|string|null
     */
    protected function getCurlInfo($handle, int $option)
    {
        $value = curl_getinfo($handle, $option);
        if ($value === false) {
            throw new RuntimeException('Error getting cURL transfer information');
        }
        /** @var mixed[]|int|float|string|null */
        return $value;
    }

    protected function handleResponseCacheHit(ResponseCacheHitEventInterface $event): void
    {
        $this->assertIsValid();

        $request = $event->getRequest();

        $entry = [
            'startedDateTime' => (new DateTimeImmutable())->format('Y-m-d\TH:i:s.vP'),
            'time' => 0,
            'request' => HttpRequest::fromPsr7($request)->jsonSerialize(),
            'response' => $event->getResponse()->jsonSerialize(),
            'cache' => new stdClass(),
            'timings' => [
                'blocked' => -1,
                'dns' => -1,
                'connect' => -1,
                'send' => 0,
                'wait' => 0,
                'receive' => 0,
            ],
        ];

        $this->writeEntry($entry);
    }

    /**
     * @param array<string,mixed> $entry
     */
    protected function writeEntry(array $entry): void
    {
        $this->assertIsValid();

        File::writeAll(
            $this->Stream,
            ($this->EntryCount++ ? ',' : '') . Json::stringify($entry),
            null,
            $this->Uri,
        );
    }

    /**
     * @phpstan-assert !null $this->Stream
     */
    protected function assertIsValid(): void
    {
        if (!$this->Stream) {
            throw new LogicException('Recorder is closed');
        }
    }
}
