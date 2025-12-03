<?php

namespace Framework\Http;

use InvalidArgumentException;
use RuntimeException;

class BinaryFileResponse extends Response
{
    protected string $file;
    protected bool $deleteFileAfterSend = false;
    protected ?string $filename = null;
    protected bool $isDownload = false;
    
    public function __construct(
        string $file,
        int $status = 200,
        array $headers = [],
        bool $isDownload = false,
        ?string $filename = null
    ) {
        parent::__construct('', $status, $headers);
        
        $this->setFile($file, $isDownload, $filename);
    }
    
    /**
     * Set the file to be sent
     */
    public function setFile(string $file, bool $isDownload = false, ?string $filename = null): self
    {
        if (!is_file($file)) {
            throw new InvalidArgumentException('File must be a regular file.');
        }
        
        if (!is_readable($file)) {
            throw new RuntimeException('File is not readable.');
        }
        
        $this->file = $file;
        $this->isDownload = $isDownload;
        $this->filename = $filename ?? basename($file);
        
        $this->setHeaders();
        
        return $this;
    }
    
    /**
     * Get the file path
     */
    public function getFile(): string
    {
        return $this->file;
    }
    
    /**
     * Set headers for the file response
     */
    protected function setHeaders(): self
    {
        $fileInfo = new \SplFileInfo($this->file);
        
        // Set content type
        $mimeType = $this->getMimeType($this->file);
        $this->setContentType($mimeType, null);
        
        // Set content length
        $this->header('Content-Length', (string) $fileInfo->getSize());
        
        // Set last modified
        $lastModified = new \DateTime('@' . $fileInfo->getMTime());
        $this->header('Last-Modified', $lastModified->format('D, d M Y H:i:s') . ' GMT');
        
        // Set ETag
        $etag = md5_file($this->file);
        $this->header('ETag', '"' . $etag . '"');
        
        // Set content disposition
        if ($this->isDownload) {
            $disposition = 'attachment';
        } else {
            $disposition = $this->shouldBeInline($mimeType) ? 'inline' : 'attachment';
        }
        
        $filename = $this->filename;
        $filenameFallback = str_replace('"', '\\"', $filename);
        
        if ($filename !== $filenameFallback) {
            $this->header('Content-Disposition', "$disposition; filename=\"$filenameFallback\"; filename*=utf-8''" . rawurlencode($filename));
        } else {
            $this->header('Content-Disposition', "$disposition; filename=\"$filename\"");
        }
        
        // Security headers
        $this->header('X-Content-Type-Options', 'nosniff');
        
        return $this;
    }
    
    /**
     * Determine if the file should be displayed inline
     */
    protected function shouldBeInline(string $mimeType): bool
    {
        $inlineTypes = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/svg+xml',
            'application/pdf',
            'text/plain',
            'text/html',
            'video/mp4',
            'audio/mpeg',
        ];
        
        return in_array($mimeType, $inlineTypes);
    }
    
    /**
     * Get the MIME type of the file
     */
    protected function getMimeType(string $file): string
    {
        if (function_exists('mime_content_type')) {
            $mimeType = mime_content_type($file);
            if ($mimeType !== false) {
                return $mimeType;
            }
        }
        
        // Fallback to extension-based detection
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        
        $mimeTypes = [
            'txt' => 'text/plain',
            'htm' => 'text/html',
            'html' => 'text/html',
            'php' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'swf' => 'application/x-shockwave-flash',
            'flv' => 'video/x-flv',
            'png' => 'image/png',
            'jpe' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'ico' => 'image/vnd.microsoft.icon',
            'tiff' => 'image/tiff',
            'tif' => 'image/tiff',
            'svg' => 'image/svg+xml',
            'svgz' => 'image/svg+xml',
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            'exe' => 'application/x-msdownload',
            'msi' => 'application/x-msdownload',
            'cab' => 'application/vnd.ms-cab-compressed',
            'mp3' => 'audio/mpeg',
            'qt' => 'video/quicktime',
            'mov' => 'video/quicktime',
            'pdf' => 'application/pdf',
            'psd' => 'image/vnd.adobe.photoshop',
            'ai' => 'application/postscript',
            'eps' => 'application/postscript',
            'ps' => 'application/postscript',
            'doc' => 'application/msword',
            'rtf' => 'application/rtf',
            'xls' => 'application/vnd.ms-excel',
            'ppt' => 'application/vnd.ms-powerpoint',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'odt' => 'application/vnd.oasis.opendocument.text',
            'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
        ];
        
        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }
    
    /**
     * Set the file to be deleted after sending
     */
    public function deleteFileAfterSend(bool $shouldDelete = true): self
    {
        $this->deleteFileAfterSend = $shouldDelete;
        
        return $this;
    }
    
    /**
     * Send the file content
     */
    public function sendContent(): self
    {
        if (!is_readable($this->file)) {
            throw new RuntimeException('File is not readable.');
        }
        
        // Handle range requests
        $size = filesize($this->file);
        $length = $size;
        $start = 0;
        $end = $size - 1;
        
        if (isset($_SERVER['HTTP_RANGE'])) {
            $range = $_SERVER['HTTP_RANGE'];
            if (preg_match('/bytes=(\d+)-(\d*)/', $range, $matches)) {
                $start = (int) $matches[1];
                $end = $matches[2] !== '' ? (int) $matches[2] : $end;
                
                $length = $end - $start + 1;
                
                $this->setStatusCode(206); // Partial Content
                $this->header('Content-Range', "bytes $start-$end/$size");
                $this->header('Content-Length', (string) $length);
            }
        }
        
        // Open file
        $fp = fopen($this->file, 'rb');
        
        if ($fp === false) {
            throw new RuntimeException('Cannot open file for reading.');
        }
        
        // Seek to start position
        if ($start > 0) {
            fseek($fp, $start);
        }
        
        // Output file in chunks
        $chunkSize = 8192;
        $bytesRemaining = $length;
        
        while ($bytesRemaining > 0 && !feof($fp)) {
            $bytesToRead = min($chunkSize, $bytesRemaining);
            echo fread($fp, $bytesToRead);
            $bytesRemaining -= $bytesToRead;
            
            if (connection_status() != 0) {
                fclose($fp);
                return $this;
            }
        }
        
        fclose($fp);
        
        if ($this->deleteFileAfterSend && file_exists($this->file)) {
            unlink($this->file);
        }
        
        return $this;
    }
    
    /**
     * Check if the file was modified since the given date
     */
    public function isNotModified(Request $request): bool
    {
        $lastModified = filemtime($this->file);
        $etag = md5_file($this->file);
        
        $modifiedSince = $request->header('If-Modified-Since');
        $noneMatch = $request->header('If-None-Match');
        
        if ($modifiedSince && strtotime($modifiedSince) >= $lastModified) {
            return true;
        }
        
        if ($noneMatch && trim($noneMatch, '"') === $etag) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Make the response downloadable
     */
    public function setDownload(?string $filename = null): self
    {
        $this->isDownload = true;
        
        if ($filename !== null) {
            $this->filename = $filename;
        }
        
        $this->setHeaders();
        
        return $this;
    }
    
    /**
     * Make the response inline (viewable in browser)
     */
    public function setInline(?string $filename = null): self
    {
        $this->isDownload = false;
        
        if ($filename !== null) {
            $this->filename = $filename;
        }
        
        $this->setHeaders();
        
        return $this;
    }
}
