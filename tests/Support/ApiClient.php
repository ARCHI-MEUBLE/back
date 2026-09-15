<?php

declare(strict_types=1);

namespace Tests\Support;

use CURLFile;
use CurlHandle;
use RuntimeException;

final class ApiClient
{
    private readonly string $cookieJar;

    public function __construct(private readonly string $baseUrl)
    {
        $this->cookieJar = tempnam(sys_get_temp_dir(), 'archimeuble-cookies-');
    }

    public function get(string $path, array $headers = []): ApiResponse
    {
        return $this->send('GET', $path, null, $headers);
    }

    public function delete(string $path, ?array $json = null, array $headers = []): ApiResponse
    {
        return $this->send('DELETE', $path, $json === null ? null : self::encode($json), self::jsonHeaders($json, $headers));
    }

    public function post(string $path, ?array $json = null, array $headers = []): ApiResponse
    {
        return $this->send('POST', $path, $json === null ? '' : self::encode($json), self::jsonHeaders($json, $headers));
    }

    public function put(string $path, ?array $json = null, array $headers = []): ApiResponse
    {
        return $this->send('PUT', $path, $json === null ? '' : self::encode($json), self::jsonHeaders($json, $headers));
    }

    public function options(string $path, array $headers = []): ApiResponse
    {
        return $this->send('OPTIONS', $path, null, $headers);
    }

    public function raw(string $method, string $path, string $body, array $headers = []): ApiResponse
    {
        return $this->send($method, $path, $body, $headers);
    }

    public function multipart(string $path, array $fields, array $files = []): ApiResponse
    {
        $payload = $fields;
        foreach ($files as $field => $file) {
            $payload[$field] = new CURLFile($file['path'], $file['type'], $file['name']);
        }
        return $this->send('POST', $path, $payload, []);
    }

    public function clearCookies(): void
    {
        file_put_contents($this->cookieJar, '');
    }

    private function send(string $method, string $path, mixed $body, array $headers): ApiResponse
    {
        $handle = curl_init($this->baseUrl . $path);
        if (!$handle instanceof CurlHandle || $method === '') {
            throw new RuntimeException('curl_init failed');
        }
        $headerLines = array_values(array_filter($headers, 'is_string'));
        $responseHeaders = [];
        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEJAR => $this->cookieJar,
            CURLOPT_COOKIEFILE => $this->cookieJar,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HEADERFUNCTION => static function (CurlHandle $curl, string $line) use (&$responseHeaders): int {
                $separator = strpos($line, ':');
                if ($separator !== false) {
                    $name = strtolower(trim(substr($line, 0, $separator)));
                    $responseHeaders[$name][] = trim(substr($line, $separator + 1));
                }
                return strlen($line);
            },
        ]);
        if ($body !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }
        $responseBody = curl_exec($handle);
        if (!is_string($responseBody)) {
            $error = curl_error($handle);
            curl_close($handle);
            throw new RuntimeException(sprintf('%s %s failed: %s', $method, $path, $error));
        }
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);
        return new ApiResponse($status, $responseHeaders, $responseBody);
    }

    private static function encode(array $json): string
    {
        return json_encode($json, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private static function jsonHeaders(?array $json, array $headers): array
    {
        if ($json === null) {
            return $headers;
        }
        return array_merge(['Content-Type: application/json'], $headers);
    }
}
