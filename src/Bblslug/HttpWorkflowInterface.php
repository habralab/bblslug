<?php

declare(strict_types=1);

namespace Bblslug;

/**
 * Request-local continuation for APIs that require more than one HTTP exchange.
 *
 * @phpstan-type HttpResponse array{status:int,body:string,headers:array<string,array<int,string>>,debugRequest:string,debugResponse:string}
 * @phpstan-type HttpRequest array{url:string,body:string,headers:array<int,string>,maskPatterns:array<int,string>,timeoutSeconds:int}
 */
interface HttpWorkflowInterface
{
    /**
     * @param HttpResponse $upload
     * @param callable(HttpRequest):HttpResponse $request Authenticated HTTP transport.
     * @return HttpResponse
     */
    public function complete(array $upload, callable $request): array;
}
