<?php declare(strict_types=1);

namespace Salient\Contract\Http\Message;

use Psr\Http\Message\RequestInterface;

interface HttpRequestInterface extends HttpMessageInterface, RequestInterface
{
    /**
     * @return array{method:string,url:string,httpVersion:string,cookies:array<array{name:string,value:string,path?:string,domain?:string,expires?:string,httpOnly?:bool,secure?:bool}>,headers:array<array{name:string,value:string}>,queryString:array<array{name:string,value:string}>,postData?:array{mimeType:string,params:array{},text:string},headersSize:int,bodySize:int}
     */
    public function jsonSerialize(): array;
}
