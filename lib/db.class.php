<?php

defined('_SEVEN') or die('No direct script access allowed');

class Db extends \RedBeanPHP\Facade
{
    /**
     * RedBean's facade method is `exec()`. The codebase historically called it
     * as `DB::execute()` (PDO-style), which falls through to __callStatic and
     * fails with "Plugin 'execute' does not exist". Alias here so existing
     * call sites keep working without a sweep.
     */
    public static function execute($sql, $bindings = [])
    {
        return parent::exec($sql, $bindings);
    }

    public static function isSetDatabase(array $dbConfig, Logger $logger): void
    {
        $host     = $dbConfig['dbHost']    ?? '127.0.0.1';
        $user     = $dbConfig['user']      ?? '';
        $password = $dbConfig['password']  ?? '';
        $database = $dbConfig['dbname']    ?? 'sevencms';
        $dbLog    = Logger::channel('db');

        try {
            $link = mysqli_connect($host, $user, $password);
            if (!$link) {
                throw new RuntimeException('Could not connect to MySQL: ' . mysqli_connect_error());
            }

            if (!mysqli_select_db($link, $database)) {
                $escaped = mysqli_real_escape_string($link, $database);
                if (mysqli_query($link, 'CREATE DATABASE `' . $escaped . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci')) {
                    $dbLog->info('Database created', ['db' => $database]);
                } else {
                    $dbLog->error('Create database failed', ['db' => $database, 'error' => mysqli_error($link)]);
                }
            } else {
                $dbLog->debug('Database connected', ['db' => $database, 'host' => $host]);
            }

            mysqli_close($link);
        } catch (RuntimeException $e) {
            $dbLog->error('DB init error', ['message' => $e->getMessage()]);
        }
    }
}
