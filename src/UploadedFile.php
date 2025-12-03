<?php

namespace Foamycastle\HTTP;

use RuntimeException;

class UploadedFile
{
    protected string $path;
    protected string $originalName;
    protected ?string $mimeType;
    protected int $error;
    protected ?int $size;
    protected bool $test = false;
    
    public function __construct(
        string $path,
        string $originalName,
        ?string $mimeType = null,
        int $error = UPLOAD_ERR_OK,
        ?int $size = null,
        bool $test = false
    ) {
        $this->path = $path;
        $this->originalName = $originalName;
        $this->mimeType = $mimeType;
        $this->error = $error;
        $this->size = $size;
        $this->test = $test;
    }
    
    /**
     * Check if the file was uploaded successfully
     */
    public function isValid(): bool
    {
        $isOk = $this->error === UPLOAD_ERR_OK;
        
        return $this->test ? $isOk : $isOk && is_uploaded_file($this->path);
    }
    
    /**
     * Get the file path
     */
    public function path(): string
    {
        return $this->path;
    }
    
    /**
     * Get the file path (alias for path)
     */
    public function getRealPath(): string
    {
        return $this->path();
    }
    
    /**
     * Get the original file name
     */
    public function getClientOriginalName(): string
    {
        return $this->originalName;
    }
    
    /**
     * Get the file extension based on the original name
     */
    public function getClientOriginalExtension(): string
    {
        return pathinfo($this->originalName, PATHINFO_EXTENSION);
    }
    
    /**
     * Get the file MIME type
     */
    public function getMimeType(): ?string
    {
        if ($this->mimeType) {
            return $this->mimeType;
        }
        
        if (function_exists('mime_content_type')) {
            return mime_content_type($this->path);
        }
        
        return null;
    }
    
    /**
     * Get the file extension based on MIME type
     */
    public function guessExtension(): ?string
    {
        $mimeType = $this->getMimeType();
        
        $mimeTypes = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            'application/pdf' => 'pdf',
            'application/zip' => 'zip',
            'application/x-zip-compressed' => 'zip',
            'text/plain' => 'txt',
            'text/html' => 'html',
            'application/json' => 'json',
            'text/css' => 'css',
            'text/javascript' => 'js',
            'application/javascript' => 'js',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        ];
        
        return $mimeTypes[$mimeType] ?? null;
    }
    
    /**
     * Get the file size
     */
    public function getSize(): ?int
    {
        if ($this->size !== null) {
            return $this->size;
        }
        
        if (file_exists($this->path)) {
            return filesize($this->path);
        }
        
        return null;
    }
    
    /**
     * Get the upload error code
     */
    public function getError(): int
    {
        return $this->error;
    }
    
    /**
     * Get the error message
     */
    public function getErrorMessage(): string
    {
        static $errors = [
            UPLOAD_ERR_INI_SIZE => 'The file exceeds the upload_max_filesize directive in php.ini',
            UPLOAD_ERR_FORM_SIZE => 'The file exceeds the MAX_FILE_SIZE directive in the HTML form',
            UPLOAD_ERR_PARTIAL => 'The file was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload',
        ];
        
        return $errors[$this->error] ?? 'Unknown upload error';
    }
    
    /**
     * Move the uploaded file to a new location
     */
    public function move(string $directory, ?string $name = null): bool
    {
        if (!$this->isValid()) {
            throw new RuntimeException($this->getErrorMessage());
        }
        
        $name = $name ?? $this->hashName();
        $target = rtrim($directory, '/') . '/' . $name;
        
        // Create directory if it doesn't exist
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $directory));
            }
        }
        
        if ($this->test) {
            return rename($this->path, $target);
        }
        
        if (!move_uploaded_file($this->path, $target)) {
            throw new RuntimeException('Could not move uploaded file');
        }
        
        @chmod($target, 0644);
        
        $this->path = $target;
        
        return true;
    }
    
    /**
     * Store the uploaded file
     */
    public function store(string $path, ?string $name = null): string
    {
        $name = $name ?? $this->hashName();
        $fullPath = $path . '/' . $name;
        
        $this->move($path, $name);
        
        return $fullPath;
    }
    
    /**
     * Store the uploaded file with public visibility
     */
    public function storePublicly(string $path, ?string $name = null): string
    {
        return $this->store($path, $name);
    }
    
    /**
     * Store the uploaded file with its original name
     */
    public function storeAs(string $path, string $name): string
    {
        return $this->store($path, $name);
    }
    
    /**
     * Generate a unique filename with hash
     */
    public function hashName(?string $path = null): string
    {
        $hash = bin2hex(random_bytes(20));
        $extension = $this->guessExtension() ?? $this->getClientOriginalExtension();
        
        if ($path) {
            return $path . '/' . $hash . '.' . $extension;
        }
        
        return $hash . '.' . $extension;
    }
    
    /**
     * Get the file contents
     */
    public function get(): string|false
    {
        return file_get_contents($this->path);
    }
    
    /**
     * Check if file is an image
     */
    public function isImage(): bool
    {
        $mimeType = $this->getMimeType();
        
        return $mimeType && str_starts_with($mimeType, 'image/');
    }
    
    /**
     * Get image dimensions
     */
    public function dimensions(): ?array
    {
        if (!$this->isImage()) {
            return null;
        }
        
        $info = @getimagesize($this->path);
        
        if ($info === false) {
            return null;
        }
        
        return [
            'width' => $info[0],
            'height' => $info[1],
        ];
    }
}
