<?php

namespace Foamycastle\HTTP;

use InvalidArgumentException;

class JsonResponse extends Response
{
    protected $data;
    protected int $encodingOptions = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    protected ?string $callback = null;
    
    public function __construct($data = [], int $status = 200, array $headers = [], int $options = 0)
    {
        parent::__construct('', $status, $headers);
        
        if ($options) {
            $this->encodingOptions = $options;
        }
        
        $this->setData($data);
    }
    
    /**
     * Set the JSON data
     */
    public function setData($data = []): self
    {
        $this->data = $data;
        
        return $this->update();
    }
    
    /**
     * Get the JSON data
     */
    public function getData(bool $assoc = false)
    {
        if ($assoc) {
            return json_decode($this->content, true);
        }
        
        return $this->data;
    }
    
    /**
     * Set the JSON encoding options
     */
    public function setEncodingOptions(int $options): self
    {
        $this->encodingOptions = $options;
        
        return $this->update();
    }
    
    /**
     * Get the JSON encoding options
     */
    public function getEncodingOptions(): int
    {
        return $this->encodingOptions;
    }
    
    /**
     * Set the JSONP callback
     */
    public function setCallback(?string $callback = null): self
    {
        if ($callback !== null) {
            // Sanitize callback name
            $callback = preg_replace('/[^a-zA-Z0-9_.]/', '', $callback);
        }
        
        $this->callback = $callback;
        
        return $this->update();
    }
    
    /**
     * Update the content based on the current data
     */
    protected function update(): self
    {
        if ($this->callback !== null) {
            // JSONP response
            $this->setContentType('text/javascript');
            
            $json = json_encode($this->data, $this->encodingOptions);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new InvalidArgumentException('JSON encoding failed: ' . json_last_error_msg());
            }
            
            return parent::setContent(sprintf('/**/%s(%s);', $this->callback, $json));
        }
        
        // Regular JSON response
        $this->setContentType('application/json');
        
        $json = json_encode($this->data, $this->encodingOptions);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('JSON encoding failed: ' . json_last_error_msg());
        }
        
        return parent::setContent($json);
    }
    
    /**
     * Add data to the JSON response
     */
    public function with(string $key, $value): self
    {
        if (is_array($this->data)) {
            $this->data[$key] = $value;
        } elseif (is_object($this->data)) {
            $this->data->{$key} = $value;
        }
        
        return $this->update();
    }
    
    /**
     * Set multiple items at once
     */
    public function withMany(array $data): self
    {
        foreach ($data as $key => $value) {
            $this->with($key, $value);
        }
        
        return $this;
    }
    
    /**
     * Create a JSON response with additional metadata
     */
    public static function success($data = [], string $message = 'Success', int $status = 200): self
    {
        return new static([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }
    
    /**
     * Create an error JSON response
     */
    public static function error(string $message = 'Error', $errors = [], int $status = 400): self
    {
        return new static([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }
    
    /**
     * Create a paginated JSON response
     */
    public static function paginated(
        array $items,
        int $total,
        int $perPage,
        int $currentPage,
        array $meta = []
    ): self {
        $lastPage = (int) ceil($total / $perPage);
        
        return new static([
            'data' => $items,
            'meta' => array_merge([
                'current_page' => $currentPage,
                'from' => ($currentPage - 1) * $perPage + 1,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'to' => min($currentPage * $perPage, $total),
                'total' => $total,
            ], $meta),
        ]);
    }
    
    /**
     * Create a collection JSON response
     */
    public static function collection(array $items, ?callable $transformer = null): self
    {
        if ($transformer) {
            $items = array_map($transformer, $items);
        }
        
        return new static([
            'data' => $items,
        ]);
    }
    
    /**
     * Override setContent to update data
     */
    public function setContent($content): Response
    {
        $this->data = $content;
        
        return $this->update();
    }
}
