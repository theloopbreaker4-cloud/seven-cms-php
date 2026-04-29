<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

/**
 * Logger — rotating file logger
 *
 * File naming:  logs/{channel}/{YYYY-MM-DD}.log
 * Rotation:     when file exceeds MAX_SIZE_MB → rename to {date}_{N}.log, open fresh
 * Channels:     'app' (default), 'error', 'db', 'auth', 'access'
 * Levels:       DEBUG < INFO < WARN < ERROR
 *
 * Usage:
 *   Seven::app()->logger->info('User logged in');
 *   Seven::app()->logger->error('DB failed: ' . $e->getMessage());
 *   Logger::channel('auth')->warn('Too many attempts from ' . $ip);
 */
class Logger
{
    // Log levels (ordered — used for minimum level filter)
    const DEBUG = 'DEBUG';
    const INFO  = 'INFO';
    const WARN  = 'WARN';
    const ERROR = 'ERROR';

    private const LEVEL_ORDER = [
        self::DEBUG => 0,
        self::INFO  => 1,
        self::WARN  => 2,
        self::ERROR => 3,
    ];

    // Max file size before rotation (bytes)
    private const MAX_SIZE = 5 * 1024 * 1024; // 5 MB

    // Keep N rotated files per channel
    private const MAX_FILES = 30;

    public static ?string $PATH     = null;
    public static string  $MIN_LEVEL = self::DEBUG; // set to INFO/WARN in prod

    /** @var array<string, Logger> */
    private static array $channels = [];

    private string  $channel;
    private mixed   $fp       = null;
    private ?string $openedFile = null;

    // -----------------------------------------------------------------------

    public function __construct(string $channel = 'app')
    {
        $this->channel = $channel;
        $this->openFile();
    }

    // Get (or create) a named channel logger
    public static function channel(string $channel = 'app'): self
    {
        if (!isset(self::$channels[$channel])) {
            self::$channels[$channel] = new self($channel);
        }
        return self::$channels[$channel];
    }

    // -----------------------------------------------------------------------
    // Public level methods

    public function debug(string $message, array $context = []): void
    {
        $this->write($message, self::DEBUG, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->write($message, self::INFO, $context);
    }

    public function warn(string $message, array $context = []): void
    {
        $this->write($message, self::WARN, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write($message, self::ERROR, $context);
    }

    // Legacy method — kept for backwards compatibility with existing callers
    public function log(string $message, bool $isPrint = false, string $level = self::INFO): void
    {
        $this->write($message, $level);

        if ($isPrint && defined('ENVIRONMENT') && ENVIRONMENT === 'dev') {
            $line = $this->format($message, $level);
            echo $line;
        }
    }

    // -----------------------------------------------------------------------
    // Core write logic

    private function write(string $message, string $level, array $context = []): void
    {
        // Minimum level filter
        $minOrder  = self::LEVEL_ORDER[self::$MIN_LEVEL] ?? 0;
        $thisOrder = self::LEVEL_ORDER[$level] ?? 0;
        if ($thisOrder < $minOrder) return;

        // Re-open if date changed (daily rotation) or file handle lost
        $expectedFile = $this->buildFilePath();
        if ($this->openedFile !== $expectedFile || $this->fp === null || $this->fp === false) {
            $this->closeFile();
            $this->openFile();
        }

        // Size-based rotation check
        if ($this->fp && fstat($this->fp)['size'] >= self::MAX_SIZE) {
            $this->rotate();
        }

        $line = $this->format($message, $level, $context);

        if ($this->fp !== null && $this->fp !== false) {
            fwrite($this->fp, $line);
        }
    }

    private function format(string $message, string $level, array $context = []): string
    {
        $ts      = (new DateTime())->format('Y-m-d H:i:s');
        $channel = strtoupper($this->channel);
        $ctx     = $context ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
        return '[' . $ts . '] [' . $channel . '] [' . $level . '] ' . $message . $ctx . PHP_EOL;
    }

    // -----------------------------------------------------------------------
    // File management

    private function channelDir(): string
    {
        return (self::$PATH ?? sys_get_temp_dir()) . DS . $this->channel;
    }

    private function buildFilePath(string $suffix = ''): string
    {
        $date = (new DateTime())->format('Y-m-d');
        $name = $suffix ? $date . '_' . $suffix . '.log' : $date . '.log';
        return $this->channelDir() . DS . $name;
    }

    private function openFile(): void
    {
        if (self::$PATH === null) return;

        $dir = $this->channelDir();
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filePath         = $this->buildFilePath();
        $this->fp         = @fopen($filePath, 'a');
        $this->openedFile = $filePath;
    }

    private function closeFile(): void
    {
        if ($this->fp !== null && $this->fp !== false) {
            fclose($this->fp);
            $this->fp = null;
        }
    }

    // Rename current file to {date}_{N}.log and open a fresh one
    private function rotate(): void
    {
        $this->closeFile();

        $base  = $this->buildFilePath();
        $n     = 1;
        while (file_exists($this->buildFilePath((string)$n))) {
            $n++;
        }
        rename($base, $this->buildFilePath((string)$n));

        $this->openFile();
        $this->pruneOldFiles();
    }

    // Delete oldest rotated files when count exceeds MAX_FILES
    private function pruneOldFiles(): void
    {
        $dir   = $this->channelDir();
        $files = glob($dir . DS . '*.log');
        if (!$files || count($files) <= self::MAX_FILES) return;

        usort($files, fn($a, $b) => filemtime($a) <=> filemtime($b));
        $toDelete = array_slice($files, 0, count($files) - self::MAX_FILES);
        foreach ($toDelete as $f) {
            @unlink($f);
        }
    }

    public function __destruct()
    {
        $this->closeFile();
    }
}
