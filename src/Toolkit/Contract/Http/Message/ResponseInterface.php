<?php declare(strict_types=1);

namespace Salient\Contract\Http\Message;

use Psr\Http\Message\ResponseInterface as PsrResponseInterface;

/**
 * @api
 *
 * @extends MessageInterface<PsrResponseInterface>
 */
interface ResponseInterface extends
    MessageInterface,
    PsrResponseInterface
{
    /**
     * @inheritDoc
     *
     * @return array{status:int,statusText:string,httpVersion:string,cookies:array<array{name:string,value:string,path?:string,domain?:string,expires?:string,httpOnly?:bool,secure?:bool}>,headers:array<array{name:string,value:string}>,content:array{size:int,mimeType:string,text:string},redirectURL:string,headersSize:int,bodySize:int}
     */
    public function jsonSerialize(): array;
}
