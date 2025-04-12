<?php

class ImageDownloader {
    private $linksFile;
    private $saveDirectory;
    private $logLevel;
    private $logFile;
    private $downloadedCount = 0;
    private $skippedCount = 0;
    private $errorCount = 0;
    
    public function __construct($linksFile, $saveDirectory, $logLevel = 1) {
        $this->linksFile = $linksFile;
        $this->saveDirectory = rtrim($saveDirectory, '/') . '/';
        $this->logLevel = $logLevel;
        $this->logFile = 'download_log_' . date('Y-m-d_H-i-s') . '.txt';
    }
    
    public function run() {
        $this->log("Начало работы: " . date('Y-m-d H:i:s'), true);
        
        try {
            // Проверка и создание директории для сохранения
            $this->ensureDirectoryExists($this->saveDirectory);
            
            // Чтение файла с ссылками
            $links = $this->readLinksFile();
            $totalLinks = count($links);
            $this->log("Найдено ссылок: $totalLinks");
            
            // Обработка каждой ссылки
            foreach ($links as $link) {
                $this->processLink($link);
            }
            
            // Итоговая статистика
            $this->log("Успешно загружено: {$this->downloadedCount}");
            $this->log("Пропущено: {$this->skippedCount}");
            $this->log("Ошибок: {$this->errorCount}");
            
        } catch (Exception $e) {
            $this->log("Ошибка: " . $e->getMessage(), true);
        }
        
        $this->log("Окончание работы: " . date('Y-m-d H:i:s'), true);
    }
    
    private function ensureDirectoryExists($dir) {
        if (!file_exists($dir)) {
            if (!mkdir($dir, 0777, true)) {
                throw new Exception("Не удалось создать директорию: $dir");
            }
            $this->log("Создана директория: $dir");
        }
    }
    
    private function readLinksFile() {
        if (!file_exists($this->linksFile)) {
            throw new Exception("Файл с ссылками не найден: {$this->linksFile}");
        }
        
        $content = file_get_contents($this->linksFile);
        if ($content === false) {
            throw new Exception("Не удалось прочитать файл с ссылками: {$this->linksFile}");
        }
        
        $links = explode("\n", trim($content));
        return array_filter($links);
    }
    
    private function processLink($link) {
        $link = trim($link);
        if (empty($link)) {
            return;
        }
        
        $filename = basename($link);
        $filepath = $this->saveDirectory . $filename;
        
        // Проверка на уже существующий файл
        if (file_exists($filepath)) {
            $this->skippedCount++;
            $this->log("Пропущено: $link (файл уже существует)", $this->logLevel >= 2);
            return;
        }
        
        // Загрузка файла
        $context = stream_context_create([
            'http' => ['ignore_errors' => true]
        ]);
        
        $fileContent = @file_get_contents($link, false, $context);
        
        // Получение кода ответа
        $httpCode = 200;
        if (isset($http_response_header)) {
            foreach ($http_response_header as $header) {
                if (preg_match('#HTTP/\d+\.\d+ (\d+)#', $header, $matches)) {
                    $httpCode = (int)$matches[1];
                    break;
                }
            }
        }
        
        if ($fileContent === false || $httpCode !== 200) {
            $this->errorCount++;
            $this->log("Ошибка: $link (HTTP код: $httpCode)", $this->logLevel >= 2);
            return;
        }
        
        // Сохранение файла
        $bytesWritten = file_put_contents($filepath, $fileContent);
        if ($bytesWritten === false) {
            $this->errorCount++;
            $this->log("Ошибка сохранения: $link", $this->logLevel >= 2);
            return;
        }
        
        $this->downloadedCount++;
        
        // Логирование подробностей для уровня 2 и выше
        if ($this->logLevel >= 2) {
            $status = "Загружено: $link";
            
            // Дополнительная информация для уровня 3
            if ($this->logLevel >= 3) {
                $fileSize = $this->formatFileSize($bytesWritten);
                $status .= " | Размер: $fileSize | HTTP код: $httpCode";
            }
            
            $this->log($status, true);
        }
    }
    
    private function formatFileSize($bytes) {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }
    
    private function log($message, $forceLog = false) {
        if ($this->logLevel >= 1 || $forceLog) {
            $logMessage = "[" . date('Y-m-d H:i:s') . "] " . $message . PHP_EOL;
            file_put_contents($this->logFile, $logMessage, FILE_APPEND);
        }
    }
}

// Пример использования
try {
    // Настройки
    $linksFile = 'images.txt';          // Путь к файлу со ссылками
    $saveDirectory = 'downloaded/';    // Директория для сохранения
    $logLevel = 3;                     // Уровень логирования (1-3)
    
    $downloader = new ImageDownloader($linksFile, $saveDirectory, $logLevel);
    $downloader->run();
    
    echo "Загрузка завершена. Проверьте лог-файл для деталей.";
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage();
}
?>
