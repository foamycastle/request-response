<?php

namespace Framework\Http;

use InvalidArgumentException;

class Response
{
    protected $content;
    protected int $statusCode;
    protected string $statusText;
    protected array $headers = [];
    protected array $cookies = [];
    protected string $version = '1.1';
    
    public static array $statusTexts = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        103 => 'Early Hints',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',
        208 => 'Already Reported',
        226 => 'IM Used',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Payload Too Large',
        414 => 'URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Range Not Satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',
        421 => 'Misdirected Request',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Too Early',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        451 => 'Unavailable For Legal Reasons',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        510 => 'Not Extended',
        511 => 'Network Authentication Required',
    ];
    
    public function __construct($content = '', int $status = 200, array $headers = [])
    {
        $this->setContent($content);
        $this->setStatusCode($status);
        $this->setHeaders($headers);
    }
    
    /**
     * Create a new response instance
     */
    public static function make($content = '', int $status = 200, array $headers = []): self
    {
        return new static($content, $status, $headers);
    }
    
    /**
     * Create a JSON response
     */
    public static function json($data = [], int $status = 200, array $headers = [], int $options = 0): JsonResponse
    {
        return new JsonResponse($data, $status, $headers, $options);
    }
    
    /**
     * Create a JSONP response
     */
    public static function jsonp(string $callback, $data = [], int $status = 200, array $headers = [], int $options = 0): JsonResponse
    {
        return static::json($data, $status, $headers, $options)->setCallback($callback);
    }
    
    /**
     * Create a redirect response
     */
    public static function redirect(string $url, int $status = 302, array $headers = []): RedirectResponse
    {
        return new RedirectResponse($url, $status, $headers);
    }
    
    /**
     * Create a download response
     */
    public static function download(string $file, ?string $name = null, array $headers = []): BinaryFileResponse
    {
        return new BinaryFileResponse($file, 200, $headers, true, $name);
    }
    
    /**
     * Create a file response
     */
    public static function file(string $file, array $headers = []): BinaryFileResponse
    {
        return new BinaryFileResponse($file, 200, $headers, false);
    }
    
    /**
     * Create a streamed response
     */
    public static function stream(callable $callback, int $status = 200, array $headers = []): StreamedResponse
    {
        return new StreamedResponse($callback, $status, $headers);
    }
    
    /**
     * Create a no content response
     */
    public static function noContent(int $status = 204, array $headers = []): self
    {
        return new static('', $status, $headers);
    }
    
    /**
     * Create a view response
     */
    public static function view(string $view, array $data = [], int $status = 200, array $headers = []): self
    {
        // This would integrate with your templating engine
        $content = "<!-- View: $view -->"; // Placeholder
        return new static($content, $status, $headers);
    }
    
    /**
     * Set the response content
     */
    public function setContent($content): self
    {
        $this->content = $content;
        
        return $this;
    }
    
    /**
     * Get the response content
     */
    public function getContent()
    {
        return $this->content;
    }
    
    /**
     * Set the status code
     */
    public function setStatusCode(int $code, ?string $text = null): self
    {
        $this->statusCode = $code;
        
        if ($text === null && isset(self::$statusTexts[$code])) {
            $text = self::$statusTexts[$code];
        }
        
        $this->statusText = $text ?? 'Unknown';
        
        return $this;
    }
    
    /**
     * Get the status code
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
    
    /**
     * Check if response is successful (2xx)
     */
    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }
    
    /**
     * Check if response is OK (200)
     */
    public function isOk(): bool
    {
        return $this->statusCode === 200;
    }
    
    /**
     * Check if response is a redirect (3xx)
     */
    public function isRedirect(?string $location = null): bool
    {
        return in_array($this->statusCode, [201, 301, 302, 303, 307, 308]) 
            && ($location === null || $location === $this->headers['Location'] ?? null);
    }
    
    /**
     * Check if response is forbidden (403)
     */
    public function isForbidden(): bool
    {
        return $this->statusCode === 403;
    }
    
    /**
     * Check if response is not found (404)
     */
    public function isNotFound(): bool
    {
        return $this->statusCode === 404;
    }
    
    /**
     * Check if response is a client error (4xx)
     */
    public function isClientError(): bool
    {
        return $this->statusCode >= 400 && $this->statusCode < 500;
    }
    
    /**
     * Check if response is a server error (5xx)
     */
    public function isServerError(): bool
    {
        return $this->statusCode >= 500 && $this->statusCode < 600;
    }
    
    /**
     * Set a header
     */
    public function header(string $key, $value, bool $replace = true): self
    {
        if ($replace || !isset($this->headers[$key])) {
            $this->headers[$key] = $value;
        }
        
        return $this;
    }
    
    /**
     * Set multiple headers
     */
    public function setHeaders(array $headers): self
    {
        foreach ($headers as $key => $value) {
            $this->header($key, $value);
        }
        
        return $this;
    }
    
    /**
     * Get a header value
     */
    public function getHeader(string $key, $default = null)
    {
        return $this->headers[$key] ?? $default;
    }
    
    /**
     * Get all headers
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }
    
    /**
     * Remove a header
     */
    public function removeHeader(string $key): self
    {
        unset($this->headers[$key]);
        
        return $this;
    }
    
    /**
     * Check if a header exists
     */
    public function hasHeader(string $key): bool
    {
        return isset($this->headers[$key]);
    }
    
    /**
     * Set the content type
     */
    public function setContentType(string $contentType, ?string $charset = 'utf-8'): self
    {
        $value = $contentType;
        
        if ($charset !== null) {
            $value .= '; charset=' . $charset;
        }
        
        return $this->header('Content-Type', $value);
    }
    
    /**
     * Set cache control header
     */
    public function setCache(array $options): self
    {
        if (isset($options['etag'])) {
            $this->header('ETag', $options['etag']);
        }
        
        if (isset($options['last_modified'])) {
            $this->header('Last-Modified', 
                $options['last_modified'] instanceof \DateTime 
                    ? $options['last_modified']->format('D, d M Y H:i:s') . ' GMT'
                    : $options['last_modified']
            );
        }
        
        if (isset($options['max_age'])) {
            $this->header('Cache-Control', 'max-age=' . $options['max_age']);
        }
        
        if (isset($options['s_maxage'])) {
            $cacheControl = $this->getHeader('Cache-Control', '');
            $this->header('Cache-Control', $cacheControl . ', s-maxage=' . $options['s_maxage']);
        }
        
        if (isset($options['public']) && $options['public'] === true) {
            $cacheControl = $this->getHeader('Cache-Control', '');
            $this->header('Cache-Control', trim($cacheControl . ', public', ', '));
        }
        
        if (isset($options['private']) && $options['private'] === true) {
            $cacheControl = $this->getHeader('Cache-Control', '');
            $this->header('Cache-Control', trim($cacheControl . ', private', ', '));
        }
        
        return $this;
    }
    
    /**
     * Disable caching
     */
    public function setNoCache(): self
    {
        return $this->header('Cache-Control', 'no-cache, no-store, must-revalidate')
                    ->header('Pragma', 'no-cache')
                    ->header('Expires', '0');
    }
    
    /**
     * Set a cookie
     */
    public function cookie(
        string $name,
        string $value = '',
        int $minutes = 0,
        string $path = '/',
        ?string $domain = null,
        bool $secure = false,
        bool $httpOnly = true,
        bool $raw = false,
        ?string $sameSite = 'lax'
    ): self {
        $expire = $minutes === 0 ? 0 : time() + ($minutes * 60);
        
        $this->cookies[$name] = compact(
            'value', 'expire', 'path', 'domain', 'secure', 'httpOnly', 'raw', 'sameSite'
        );
        
        return $this;
    }
    
    /**
     * Set a cookie that expires when the browser closes
     */
    public function cookieForever(
        string $name,
        string $value = '',
        string $path = '/',
        ?string $domain = null,
        bool $secure = false,
        bool $httpOnly = true,
        bool $raw = false,
        ?string $sameSite = 'lax'
    ): self {
        return $this->cookie($name, $value, 2628000, $path, $domain, $secure, $httpOnly, $raw, $sameSite);
    }
    
    /**
     * Expire a cookie
     */
    public function expireCookie(
        string $name,
        string $path = '/',
        ?string $domain = null
    ): self {
        return $this->cookie($name, '', -2628000, $path, $domain);
    }
    
    /**
     * Get all cookies
     */
    public function getCookies(): array
    {
        return $this->cookies;
    }
    
    /**
     * Set the response as JSON
     */
    public function json($data, int $status = null, array $headers = [], int $options = 0): self
    {
        if ($status !== null) {
            $this->setStatusCode($status);
        }
        
        $this->setHeaders($headers);
        $this->setContent(json_encode($data, $options));
        $this->setContentType('application/json');
        
        return $this;
    }
    
    /**
     * Append content to the response
     */
    public function appendContent($content): self
    {
        $this->content .= $content;
        
        return $this;
    }
    
    /**
     * Prepend content to the response
     */
    public function prependContent($content): self
    {
        $this->content = $content . $this->content;
        
        return $this;
    }
    
    /**
     * Send HTTP headers
     */
    public function sendHeaders(): self
    {
        // Check if headers have already been sent
        if (headers_sent()) {
            return $this;
        }
        
        // Send status line
        header(sprintf('HTTP/%s %s %s', $this->version, $this->statusCode, $this->statusText), true, $this->statusCode);
        
        // Send headers
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value, false, $this->statusCode);
        }
        
        // Send cookies
        foreach ($this->cookies as $name => $cookie) {
            if ($cookie['raw']) {
                setrawcookie(
                    $name,
                    $cookie['value'],
                    $cookie['expire'],
                    $cookie['path'],
                    $cookie['domain'] ?? '',
                    $cookie['secure'],
                    $cookie['httpOnly']
                );
            } else {
                setcookie(
                    $name,
                    $cookie['value'],
                    [
                        'expires' => $cookie['expire'],
                        'path' => $cookie['path'],
                        'domain' => $cookie['domain'] ?? '',
                        'secure' => $cookie['secure'],
                        'httponly' => $cookie['httpOnly'],
                        'samesite' => $cookie['sameSite'] ?? 'lax',
                    ]
                );
            }
        }
        
        return $this;
    }
    
    /**
     * Send content
     */
    public function sendContent(): self
    {
        echo $this->content;
        
        return $this;
    }
    
    /**
     * Send the response
     */
    public function send(): self
    {
        $this->sendHeaders();
        $this->sendContent();
        
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        
        return $this;
    }
    
    /**
     * Set the response to be downloadable
     */
    public function setDownload(string $filename): self
    {
        $disposition = 'attachment; filename="' . $filename . '"';
        
        return $this->header('Content-Disposition', $disposition);
    }
    
    /**
     * Get the content length
     */
    public function getContentLength(): int
    {
        return strlen($this->content);
    }
    
    /**
     * Morphs the Response instance into a JSON response
     */
    public function morphToJson(): JsonResponse
    {
        return new JsonResponse(
            json_decode($this->content, true) ?? $this->content,
            $this->statusCode,
            $this->headers
        );
    }
    
    /**
     * Check if the response has the given exception
     */
    public function withException(\Throwable $e): self
    {
        // Store exception for debugging purposes
        $this->exception = $e;
        
        return $this;
    }
    
    /**
     * Convert response to string
     */
    public function __toString(): string
    {
        return (string) $this->content;
    }
}
