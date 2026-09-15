<?php

declare(strict_types=1);

namespace App\Domain\System;

use App\Http\Request;
use App\Http\Response;
use App\Http\RouteCollection;

final class TestEchoRoutes
{
    public function register(RouteCollection $routes): void
    {
        $routes->script('test')
            ->get(null, fn(Request $r): Response => $this->echo($r))
            ->post(null, fn(Request $r): Response => $this->echo($r));
    }

    private function echo(Request $request): Response
    {
        return Response::json([
            'method' => $request->method,
            'content_type' => $request->header('content-type') ?? 'not set',
            'raw_input' => $request->rawBody(),
            'headers' => $this->titleCasedHeaders($request),
            'get' => $request->query,
            'post' => $request->form,
        ]);
    }

    private function titleCasedHeaders(Request $request): array
    {
        $headers = [];
        foreach ($request->headers as $name => $value) {
            $headers[implode('-', array_map('ucfirst', explode('-', $name)))] = $value;
        }
        return $headers;
    }
}
