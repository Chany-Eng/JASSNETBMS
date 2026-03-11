<?php
/**
 * FileHelper - File upload and management utilities
 */

namespace App\Helpers;

class FileHelper
{
    /**
     * Upload file
     *
     * @param array $file $_FILES array element
     * @param string $directory Upload directory
     * @param array|null $allowedTypes Allowed file types
     * @return array ['success' => filename] or ['error' => message]
     */
    public static function upload($file, $directory = 'uploads/', $allowedTypes = null)
    {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['error' => 'No file was uploaded'];
        }

        // Validate file size
        if ($file['size'] > MAX_FILE_SIZE) {
            return ['error' => 'File is too large. Maximum size is ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB'];
        }

        // Get file extension
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExt = $allowedTypes ?? ALLOWED_FILE_TYPES;

        // Validate file type
        if (!in_array($ext, $allowedExt)) {
            return ['error' => 'File type not allowed. Allowed types: ' . implode(', ', $allowedExt)];
        }

        // Create directory if it doesn't exist
        $uploadDir = APP_ROOT . '/' . $directory;
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                return ['error' => 'Failed to create upload directory'];
            }
        }

        // Generate unique filename
        $filename = uniqid() . '_' . time() . '.' . $ext;
        $filepath = $uploadDir . $filename;

        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            return ['success' => $filename];
        }

        return ['error' => 'Failed to upload file'];
    }

    /**
     * Delete file
     *
     * @param string $filename Filename to delete
     * @param string $directory Directory containing file
     * @return bool
     */
    public static function delete($filename, $directory = 'uploads/')
    {
        $filepath = APP_ROOT . '/' . $directory . $filename;

        if (file_exists($filepath)) {
            return unlink($filepath);
        }

        return false;
    }

    /**
     * Get file size in human readable format
     *
     * @param string $filename
     * @param string $directory
     * @return string|false
     */
    public static function getFileSize($filename, $directory = 'uploads/')
    {
        $filepath = APP_ROOT . '/' . $directory . $filename;

        if (!file_exists($filepath)) {
            return false;
        }

        $bytes = filesize($filepath);
        return self::formatBytes($bytes);
    }

    /**
     * Format bytes to human readable format
     *
     * @param int $bytes
     * @param int $precision
     * @return string
     */
    public static function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Validate file exists
     *
     * @param string $filename
     * @param string $directory
     * @return bool
     */
    public static function exists($filename, $directory = 'uploads/')
    {
        $filepath = APP_ROOT . '/' . $directory . $filename;
        return file_exists($filepath);
    }

    /**
     * Create directory
     *
     * @param string $directory
     * @return bool
     */
    public static function createDirectory($directory)
    {
        $fullPath = APP_ROOT . '/' . $directory;

        if (!is_dir($fullPath)) {
            return mkdir($fullPath, 0755, true);
        }

        return true;
    }

    /**
     * Get file MIME type
     *
     * @param string $filename
     * @param string $directory
     * @return string|false
     */
    public static function getMimeType($filename, $directory = 'uploads/')
    {
        $filepath = APP_ROOT . '/' . $directory . $filename;

        if (!file_exists($filepath)) {
            return false;
        }

        return mime_content_type($filepath);
    }

    /**
     * Get all files in directory
     *
     * @param string $directory
     * @param string|null $extension Filter by extension
     * @return array
     */
    public static function getFiles($directory, $extension = null)
    {
        $fullPath = APP_ROOT . '/' . $directory;
        $files = [];

        if (!is_dir($fullPath)) {
            return $files;
        }

        $items = scandir($fullPath);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $fullPath . $item;

            if (is_file($itemPath)) {
                if ($extension === null || strtolower(pathinfo($item, PATHINFO_EXTENSION)) === $extension) {
                    $files[] = $item;
                }
            }
        }

        return $files;
    }

    /**
     * Copy file
     *
     * @param string $source
     * @param string $destination
     * @param string $directory
     * @return bool
     */
    public static function copy($source, $destination, $directory = 'uploads/')
    {
        $sourcePath = APP_ROOT . '/' . $directory . $source;
        $destPath = APP_ROOT . '/' . $directory . $destination;

        if (!file_exists($sourcePath)) {
            return false;
        }

        return copy($sourcePath, $destPath);
    }
}
