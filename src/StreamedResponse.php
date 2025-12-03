<?php

namespace Framework\Http;

use RuntimeException;

class StreamedResponse extends Response
{
    protected $callback;
    protected bool $streamed = false;
    
    public function __construct(callable $callback = null, int $status = 200, array $headers = [])
    {
        parent::__construct('', $status, $headers);
        
        if ($callback !== null) {
            $this->setCallback($callback);
        }
    }
    
    /**
     * Set the callback function
     */
    public function setCallback(callable $callback): self
    {
        $this->callback = $callback;
        
        return $this;
    }
    
    /**
     * Send the response content
     */
    public function sendContent(): self
    {
        if ($this->streamed) {
            return $this;
        }
        
        $this->streamed = true;
        
        if (!is_callable($this->callback)) {
            throw new RuntimeException('The Response callback must be a valid callable.');
        }
        
        call_user_func($this->callback);
        
        return $this;
    }
    
    /**
     * Check if the response has been streamed
     */
    public function isStreamed(): bool
    {
        return $this->streamed;
    }
    
    /**
     * Create a streamed download response
     */
    public static function streamDownload(callable $callback, string $filename, array $headers = []): self
    {
        $response = new static($callback, 200, $headers);
        
        $disposition = 'attachment; filename="' . $filename . '"';
        $response->header('Content-Disposition', $disposition);
        $response->header('X-Content-Type-Options', 'nosniff');
        
        return $response;
    }
    
    /**
     * Create a CSV download stream
     */
    public static function streamCsv(array $data, string $filename = 'export.csv', array $headers = []): self
    {
        return static::streamDownload(function() use ($data) {
            $output = fopen('php://output', 'w');
            
            foreach ($data as $row) {
                fputcsv($output, $row);
            }
            
            fclose($output);
        }, $filename, array_merge(['Content-Type' => 'text/csv'], $headers));
    }
    
    /**
     * Create a Server-Sent Events (SSE) response
     */
    public static function serverSentEvents(callable $callback): self
    {
        $response = new static($callback);
        
        $response->header('Content-Type', 'text/event-stream');
        $response->header('Cache-Control', 'no-cache');
        $response->header('Connection', 'keep-alive');
        $response->header('X-Accel-Buffering', 'no'); // Disable Nginx buffering
        
        return $response;
    }
    
    /**
     * Send SSE message
     */
    public static function sendSseMessage(string $data, ?string $event = null, ?string $id = null, ?int $retry = null): void
    {
        if ($id !== null) {
            echo "id: $id\n";
        }
        
        if ($event !== null) {
            echo "event: $event\n";
        }
        
        if ($retry !== null) {
            echo "retry: $retry\n";
        }
        
        foreach (explode("\n", $data) as $line) {
            echo "data: $line\n";
        }
        
        echo "\n";
        
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }
    
    /**
     * Stream a large file in chunks
     */
    public static function streamFile(string $path, ?string $filename = null, array $headers = []): self
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('File does not exist or is not readable.');
        }
        
        $filename = $filename ?? basename($path);
        
        return static::streamDownload(function() use ($path) {
            $handle = fopen($path, 'rb');
            
            if ($handle === false) {
                throw new RuntimeException('Cannot open file for reading.');
            }
            
            while (!feof($handle)) {
                echo fread($handle, 8192);
                
                if (connection_status() != 0) {
                    break;
                }
                
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }
            
            fclose($handle);
        }, $filename, $headers);
    }
    
    /**
     * Stream JSON data line by line (NDJSON)
     */
    public static function streamJsonLines(iterable $data, array $headers = []): self
    {
        return new static(function() use ($data) {
            foreach ($data as $item) {
                echo json_encode($item) . "\n";
                
                if (connection_status() != 0) {
                    break;
                }
                
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }
        }, 200, array_merge(['Content-Type' => 'application/x-ndjson'], $headers));
    }
    
    /**
     * Stream a generator function
     */
    public static function streamGenerator(\Generator $generator, array $headers = []): self
    {
        return new static(function() use ($generator) {
            foreach ($generator as $chunk) {
                echo $chunk;
                
                if (connection_status() != 0) {
                    break;
                }
                
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }
        }, 200, $headers);
    }
    
    /**
     * Stream a ZIP file on the fly
     */
    public static function streamZip(array $files, string $zipname = 'archive.zip'): self
    {
        return static::streamDownload(function() use ($files) {
            if (!class_exists('ZipStream\ZipStream')) {
                throw new RuntimeException('ZipStream library is required for streaming ZIP files.');
            }
            
            $zip = new \ZipStream\ZipStream(
                outputName: 'archive.zip',
                sendHttpHeaders: false,
            );
            
            foreach ($files as $filename => $filepath) {
                if (is_file($filepath)) {
                    $zip->addFileFromPath($filename, $filepath);
                } elseif (is_string($filepath)) {
                    $zip->addFile($filename, $filepath);
                }
            }
            
            $zip->finish();
        }, $zipname);
    }
    
    /**
     * Prevent content from being cached
     */
    public function setCallback cannot be called after the response has been streamed
     */
    public function setContent($content): Response
    {
        if ($this->streamed) {
            throw new RuntimeException('The content cannot be set on a streamed response.');
        }
        
        // Streamed responses don't have content
        return $this;
    }
    
    /**
     * Get the response content (always empty for streamed responses)
     */
    public function getContent()
    {
        return false;
    }
}
