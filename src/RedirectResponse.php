<?php

namespace Foamycastle\HTTP;

class RedirectResponse extends Response
{
    protected string $targetUrl;
    protected array $flashData = [];
    
    public function __construct(string $url, int $status = 302, array $headers = [])
    {
        parent::__construct('', $status, $headers);
        
        $this->setTargetUrl($url);
    }
    
    /**
     * Set the redirect target URL
     */
    public function setTargetUrl(string $url): self
    {
        if (empty($url)) {
            throw new \InvalidArgumentException('Cannot redirect to an empty URL.');
        }
        
        $this->targetUrl = $url;
        
        $this->setContent(
            sprintf('<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8" />
        <meta http-equiv="refresh" content="0;url=\'%1$s\'" />
        <title>Redirecting to %1$s</title>
    </head>
    <body>
        Redirecting to <a href="%1$s">%1$s</a>.
    </body>
</html>', htmlspecialchars($url, ENT_QUOTES, 'UTF-8'))
        );
        
        $this->header('Location', $url);
        
        return $this;
    }
    
    /**
     * Get the target URL
     */
    public function getTargetUrl(): string
    {
        return $this->targetUrl;
    }
    
    /**
     * Flash data to the session
     */
    public function with(string $key, $value): self
    {
        $this->flashData[$key] = $value;
        
        return $this;
    }
    
    /**
     * Flash multiple items to the session
     */
    public function withMany(array $data): self
    {
        foreach ($data as $key => $value) {
            $this->flashData[$key] = $value;
        }
        
        return $this;
    }
    
    /**
     * Flash input to the session
     */
    public function withInput(array $input = []): self
    {
        $this->flashData['_old_input'] = $input;
        
        return $this;
    }
    
    /**
     * Flash errors to the session
     */
    public function withErrors($errors, string $key = 'default'): self
    {
        if (is_array($errors)) {
            $this->flashData['errors'] = [$key => $errors];
        } else {
            $this->flashData['errors'] = [$key => [$errors]];
        }
        
        return $this;
    }
    
    /**
     * Flash a success message
     */
    public function withSuccess(string $message): self
    {
        return $this->with('success', $message);
    }
    
    /**
     * Flash an error message
     */
    public function withError(string $message): self
    {
        return $this->with('error', $message);
    }
    
    /**
     * Flash a warning message
     */
    public function withWarning(string $message): self
    {
        return $this->with('warning', $message);
    }
    
    /**
     * Flash an info message
     */
    public function withInfo(string $message): self
    {
        return $this->with('info', $message);
    }
    
    /**
     * Get flash data
     */
    public function getFlashData(): array
    {
        return $this->flashData;
    }
    
    /**
     * Add a fragment to the URL
     */
    public function withFragment(string $fragment): self
    {
        return $this->setTargetUrl($this->targetUrl . '#' . $fragment);
    }
    
    /**
     * Create a redirect to a URL
     */
    public static function to(string $url, int $status = 302, array $headers = []): self
    {
        return new static($url, $status, $headers);
    }
    
    /**
     * Create a redirect to a named route
     */
    public static function route(string $name, array $parameters = [], int $status = 302, array $headers = []): self
    {
        // This would integrate with your router to generate URLs
        $url = "/$name"; // Placeholder
        
        return new static($url, $status, $headers);
    }
    
    /**
     * Create a redirect to a controller action
     */
    public static function action(string $action, array $parameters = [], int $status = 302, array $headers = []): self
    {
        // This would integrate with your router
        $url = "/$action"; // Placeholder
        
        return new static($url, $status, $headers);
    }
    
    /**
     * Create a redirect back to the previous location
     */
    public static function back(int $status = 302, array $headers = []): self
    {
        $url = $_SERVER['HTTP_REFERER'] ?? '/';
        
        return new static($url, $status, $headers);
    }
    
    /**
     * Create a redirect to the home page
     */
    public static function home(int $status = 302): self
    {
        return new static('/', $status);
    }
    
    /**
     * Create a refresh redirect (same page)
     */
    public static function refresh(int $status = 302): self
    {
        return static::back($status);
    }
    
    /**
     * Create a permanent redirect (301)
     */
    public static function permanent(string $url, array $headers = []): self
    {
        return new static($url, 301, $headers);
    }
    
    /**
     * Create a temporary redirect (302)
     */
    public static function temporary(string $url, array $headers = []): self
    {
        return new static($url, 302, $headers);
    }
    
    /**
     * Create a redirect with secure protocol
     */
    public static function secure(string $path, int $status = 302, array $headers = []): self
    {
        return new static('https://' . $_SERVER['HTTP_HOST'] . '/' . ltrim($path, '/'), $status, $headers);
    }
    
    /**
     * Create a redirect away from the application
     */
    public static function away(string $url, int $status = 302, array $headers = []): self
    {
        return new static($url, $status, $headers);
    }
    
    /**
     * Create a guest redirect (typically to login)
     */
    public static function guest(string $url = '/login', int $status = 302, array $headers = []): self
    {
        return new static($url, $status, $headers);
    }
    
    /**
     * Create an intended redirect (after login)
     */
    public static function intended(string $default = '/', int $status = 302, array $headers = []): self
    {
        // This would check session for intended URL
        $url = $_SESSION['url.intended'] ?? $default;
        
        return new static($url, $status, $headers);
    }
}
