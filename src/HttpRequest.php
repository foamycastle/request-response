<?php

namespace Foamycastle\HTTP;

class HttpRequest
{
    protected array $query = [];
    protected array $request = [];
    protected array $attributes = [];
    protected array $cookies = [];
    protected array $files = [];
    protected array $server = [];
    protected array $headers = [];
    protected ?string $content = null;
    protected array $json = [];
    protected ?string $method = null;
    protected ?string $pathInfo = null;
    protected array $routeParameters = [];
    protected mixed $userResolver = null;
    
    public function __construct(
        array $query = [],
        array $request = [],
        array $attributes = [],
        array $cookies = [],
        array $files = [],
        array $server = [],
        $content = null
    ) {
        $this->query = $query;
        $this->request = $request;
        $this->attributes = $attributes;
        $this->cookies = $cookies;
        $this->files = $this->convertFileInformation($files);
        $this->server = $server;
        $this->content = $content;
        $this->headers = $this->extractHeaders($server);
    }
    
    /**
     * Create a HttpRequest from PHP globals
     */
    public static function capture(): self
    {
        return new static(
            $_GET,
            $_POST,
            [],
            $_COOKIE,
            $_FILES,
            $_SERVER,
            file_get_contents('php://input')
        );
    }
    
    /**
     * Create a HttpRequest from a URI and method
     */
    public static function create(
        string $uri,
        string $method = 'GET',
        array $parameters = [],
        array $cookies = [],
        array $files = [],
        array $server = [],
        $content = null
    ): self {
        $server = array_replace([
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'HTTP_HOST' => 'localhost',
            'HTTP_USER_AGENT' => 'Framework/1.0',
            'HTTP_ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'HTTP_ACCEPT_LANGUAGE' => 'en-us,en;q=0.5',
            'HTTP_ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.7',
            'REMOTE_ADDR' => '127.0.0.1',
            'SCRIPT_NAME' => '',
            'SCRIPT_FILENAME' => '',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'REQUEST_TIME' => time(),
            'REQUEST_TIME_FLOAT' => microtime(true),
        ], $server);
        
        $server['REQUEST_METHOD'] = strtoupper($method);
        
        $components = parse_url($uri);
        if (isset($components['host'])) {
            $server['SERVER_NAME'] = $components['host'];
            $server['HTTP_HOST'] = $components['host'];
        }
        
        if (isset($components['scheme'])) {
            if ($components['scheme'] === 'https') {
                $server['HTTPS'] = 'on';
                $server['SERVER_PORT'] = 443;
            } else {
                unset($server['HTTPS']);
                $server['SERVER_PORT'] = 80;
            }
        }
        
        if (isset($components['port'])) {
            $server['SERVER_PORT'] = $components['port'];
            $server['HTTP_HOST'] .= ':' . $components['port'];
        }
        
        if (isset($components['user'])) {
            $server['PHP_AUTH_USER'] = $components['user'];
        }
        
        if (isset($components['pass'])) {
            $server['PHP_AUTH_PW'] = $components['pass'];
        }
        
        if (!isset($components['path'])) {
            $components['path'] = '/';
        }
        
        $server['REQUEST_URI'] = $components['path'] . (isset($components['query']) ? '?' . $components['query'] : '');
        $server['QUERY_STRING'] = $components['query'] ?? '';
        
        $queryString = [];
        if (isset($components['query'])) {
            parse_str($components['query'], $queryString);
        }
        
        $request = [];
        if (in_array(strtoupper($method), ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            $request = $parameters;
        }
        
        return new static($queryString, $request, [], $cookies, $files, $server, $content);
    }
    
    /**
     * Get the request method
     */
    public function getMethod(): string
    {
        if ($this->method !== null) {
            return $this->method;
        }
        
        $this->method = strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
        
        // Check for method override
        if ($this->method === 'POST') {
            if ($method = $this->input('_method')) {
                $this->method = strtoupper($method);
            } elseif ($this->header('X-HTTP-METHOD-OVERRIDE')) {
                $this->method = strtoupper($this->header('X-HTTP-METHOD-OVERRIDE'));
            }
        }
        
        return $this->method;
    }
    
    /**
     * Check if the request method matches
     */
    public function isMethod(string $method): bool
    {
        return $this->getMethod() === strtoupper($method);
    }
    
    /**
     * Get the request URI
     */
    public function path(): string
    {
        if ($this->pathInfo !== null) {
            return $this->pathInfo;
        }
        
        $requestUri = $this->server['REQUEST_URI'] ?? '/';
        
        // Remove query string
        if (false !== $pos = strpos($requestUri, '?')) {
            $requestUri = substr($requestUri, 0, $pos);
        }
        
        $this->pathInfo = $requestUri;
        
        return $this->pathInfo;
    }
    
    /**
     * Get the full URL
     */
    public function url(): string
    {
        return $this->getScheme() . '://' . $this->getHttpHost() . $this->path();
    }
    
    /**
     * Get the full URL with query string
     */
    public function fullUrl(): string
    {
        $query = $this->getQueryString();
        return $this->url() . ($query ? '?' . $query : '');
    }
    
    /**
     * Check if the path matches a pattern
     */
    public function is(...$patterns): bool
    {
        $path = $this->path();
        
        foreach ($patterns as $pattern) {
            if (preg_match('#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#', $path)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get an input item from the request
     */
    public function input(?string $key = null, $default = null)
    {
        return data_get(
            $this->getInputSource()->all() + $this->query,
            $key,
            $default
        );
    }
    
    /**
     * Get a subset of the input data
     */
    public function only(array|string $keys): array
    {
        $keys = is_array($keys) ? $keys : func_get_args();
        
        $results = [];
        $input = $this->all();
        
        foreach ($keys as $key) {
            $results[$key] = data_get($input, $key);
        }
        
        return $results;
    }
    
    /**
     * Get all input except for specified keys
     */
    public function except(array|string $keys): array
    {
        $keys = is_array($keys) ? $keys : func_get_args();
        
        $results = $this->all();
        
        foreach ($keys as $key) {
            unset($results[$key]);
        }
        
        return $results;
    }
    
    /**
     * Get all input and files
     */
    public function all(): array
    {
        return array_replace_recursive($this->input(), $this->files());
    }
    
    /**
     * Determine if the request contains a given input item
     */
    public function has(string|array $key): bool
    {
        $keys = is_array($key) ? $key : func_get_args();
        
        $input = $this->all();
        
        foreach ($keys as $value) {
            if (!array_key_exists($value, $input)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Determine if the request contains any of the given inputs
     */
    public function hasAny(string|array $keys): bool
    {
        $keys = is_array($keys) ? $keys : func_get_args();
        
        $input = $this->all();
        
        foreach ($keys as $key) {
            if (array_key_exists($key, $input)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Determine if the request contains a non-empty value
     */
    public function filled(string|array $key): bool
    {
        $keys = is_array($key) ? $key : func_get_args();
        
        foreach ($keys as $value) {
            if ($this->isEmptyString($value)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Determine if the request is missing a given input item
     */
    public function missing(string|array $key): bool
    {
        return !$this->has($key);
    }
    
    /**
     * Get a query string item
     */
    public function query(?string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->query;
        }
        
        return data_get($this->query, $key, $default);
    }
    
    /**
     * Get a value from the POST body
     */
    public function post(?string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->request;
        }
        
        return data_get($this->request, $key, $default);
    }
    
    /**
     * Get the JSON payload for the request
     */
    public function json(?string $key = null, $default = null)
    {
        if (!$this->json) {
            $this->json = json_decode($this->getContent(), true) ?? [];
        }
        
        if ($key === null) {
            return $this->json;
        }
        
        return data_get($this->json, $key, $default);
    }
    
    /**
     * Determine if the request is sending JSON
     */
    public function isJson(): bool
    {
        return str_contains($this->header('CONTENT_TYPE') ?? '', '/json') ||
               str_contains($this->header('CONTENT_TYPE') ?? '', '+json');
    }
    
    /**
     * Determine if the current request expects JSON
     */
    public function expectsJson(): bool
    {
        return ($this->ajax() && !$this->pjax()) || $this->wantsJson();
    }
    
    /**
     * Determine if the current request is asking for JSON
     */
    public function wantsJson(): bool
    {
        $acceptable = $this->getAcceptableContentTypes();
        
        return isset($acceptable[0]) && str_contains($acceptable[0], '/json');
    }
    
    /**
     * Determine if the request is an AJAX request
     */
    public function ajax(): bool
    {
        return $this->header('X-Requested-With') === 'XMLHttpRequest';
    }
    
    /**
     * Determine if the request is a PJAX request
     */
    public function pjax(): bool
    {
        return $this->header('X-PJAX') === 'true';
    }
    
    /**
     * Get a header from the request
     */
    public function header(?string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->headers;
        }
        
        $key = strtoupper(str_replace('-', '_', $key));
        
        return $this->headers[$key] ?? $default;
    }
    
    /**
     * Get the bearer token from the request headers
     */
    public function bearerToken(): ?string
    {
        $header = $this->header('Authorization', '');
        
        if (preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    /**
     * Get a cookie from the request
     */
    public function cookie(?string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->cookies;
        }
        
        return $this->cookies[$key] ?? $default;
    }
    
    /**
     * Get all files
     */
    public function files(): array
    {
        return $this->files;
    }
    
    /**
     * Get a file from the request
     */
    public function file(?string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->files;
        }
        
        return data_get($this->files, $key, $default);
    }
    
    /**
     * Determine if the uploaded data contains a file
     */
    public function hasFile(string $key): bool
    {
        $file = $this->file($key);
        
        return $file instanceof UploadedFile && $file->isValid();
    }
    
    /**
     * Get a server variable
     */
    public function server(?string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->server;
        }
        
        return $this->server[$key] ?? $default;
    }
    
    /**
     * Get the client IP address
     */
    public function ip(): ?string
    {
        return $this->server['REMOTE_ADDR'] ?? null;
    }
    
    /**
     * Get the client IP addresses
     */
    public function ips(): array
    {
        $ips = [];
        
        if ($this->server['HTTP_X_FORWARDED_FOR'] ?? null) {
            $ips = array_map('trim', explode(',', $this->server['HTTP_X_FORWARDED_FOR']));
        }
        
        if ($ip = $this->ip()) {
            $ips[] = $ip;
        }
        
        return $ips;
    }
    
    /**
     * Get the client user agent
     */
    public function userAgent(): ?string
    {
        return $this->header('User-Agent');
    }
    
    /**
     * Get the request scheme (http or https)
     */
    public function getScheme(): string
    {
        return $this->isSecure() ? 'https' : 'http';
    }
    
    /**
     * Determine if the request is over HTTPS
     */
    public function isSecure(): bool
    {
        return isset($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off';
    }
    
    /**
     * Get the host name
     */
    public function getHttpHost(): string
    {
        return $this->server['HTTP_HOST'] ?? $this->server['SERVER_NAME'] ?? 'localhost';
    }
    
    /**
     * Get the port
     */
    public function getPort(): int
    {
        return (int) ($this->server['SERVER_PORT'] ?? 80);
    }
    
    /**
     * Get the content of the request
     */
    public function getContent(): string
    {
        if ($this->content === null) {
            $this->content = file_get_contents('php://input');
        }
        
        return $this->content;
    }
    
    /**
     * Get the query string
     */
    public function getQueryString(): ?string
    {
        return $this->server['QUERY_STRING'] ?? null;
    }
    
    /**
     * Get acceptable content types
     */
    public function getAcceptableContentTypes(): array
    {
        $accept = $this->header('Accept');
        
        if (!$accept) {
            return [];
        }
        
        $types = [];
        foreach (explode(',', $accept) as $type) {
            $types[] = trim(explode(';', $type)[0]);
        }
        
        return $types;
    }
    
    /**
     * Merge new input into the request
     */
    public function merge(array $input): self
    {
        $this->getInputSource()->add($input);
        
        return $this;
    }
    
    /**
     * Replace the input for the request
     */
    public function replace(array $input): self
    {
        $this->getInputSource()->replace($input);
        
        return $this;
    }
    
    /**
     * Set a route parameter
     */
    public function setRouteParameter(string $key, $value): self
    {
        $this->routeParameters[$key] = $value;
        
        return $this;
    }
    
    /**
     * Get a route parameter
     */
    public function route(?string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->routeParameters;
        }
        
        return $this->routeParameters[$key] ?? $default;
    }
    
    /**
     * Get the user making the request
     */
    public function user()
    {
        if ($this->userResolver) {
            return call_user_func($this->userResolver, $this);
        }
        
        return null;
    }
    
    /**
     * Set the user resolver
     */
    public function setUserResolver(callable $callback): self
    {
        $this->userResolver = $callback;
        
        return $this;
    }
    
    /**
     * Extract headers from server variables
     */
    protected function extractHeaders(array $server): array
    {
        $headers = [];
        
        foreach ($server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headers[substr($key, 5)] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'])) {
                $headers[$key] = $value;
            }
        }
        
        return $headers;
    }
    
    /**
     * Get the input source for the request
     */
    protected function getInputSource(): InputBag
    {
        if ($this->isJson()) {
            return new InputBag($this->json());
        }
        
        return new InputBag($this->getMethod() === 'GET' ? $this->query : $this->request);
    }
    
    /**
     * Determine if the given input key is empty
     */
    protected function isEmptyString(string $key): bool
    {
        $value = $this->input($key);
        
        return !is_bool($value) && !is_array($value) && trim((string) $value) === '';
    }
    
    /**
     * Convert uploaded file information to UploadedFile instances
     */
    protected function convertFileInformation(array $files): array
    {
        $converted = [];
        
        foreach ($files as $key => $value) {
            if (is_array($value)) {
                if (isset($value['tmp_name'])) {
                    // Single file
                    if (is_string($value['tmp_name'])) {
                        $converted[$key] = new UploadedFile(
                            $value['tmp_name'],
                            $value['name'] ?? '',
                            $value['type'] ?? null,
                            $value['error'] ?? UPLOAD_ERR_OK,
                            $value['size'] ?? null
                        );
                    } else {
                        // Multiple files
                        $converted[$key] = [];
                        foreach ($value['tmp_name'] as $index => $tmpName) {
                            $converted[$key][$index] = new UploadedFile(
                                $tmpName,
                                $value['name'][$index] ?? '',
                                $value['type'][$index] ?? null,
                                $value['error'][$index] ?? UPLOAD_ERR_OK,
                                $value['size'][$index] ?? null
                            );
                        }
                    }
                } else {
                    // Nested array
                    $converted[$key] = $this->convertFileInformation($value);
                }
            }
        }
        
        return $converted;
    }
}



/**
 * Helper function to get data from array using dot notation
 */
if (!function_exists('data_get')) {
    function data_get($target, $key, $default = null)
    {
        if ($key === null) {
            return $target;
        }
        
        $key = is_array($key) ? $key : explode('.', $key);
        
        foreach ($key as $segment) {
            if (is_array($target) && array_key_exists($segment, $target)) {
                $target = $target[$segment];
            } else {
                return $default;
            }
        }
        
        return $target;
    }
}
