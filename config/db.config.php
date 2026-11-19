<?php

defined('_SEVEN') or die('No direct script access allowed');

$dbConfig = [
    'dbHost'   => Env::get('DB_HOST', '127.0.0.1'),
    'dbPort'   => (int)Env::get('DB_PORT', 3306),
    'user'     => Env::get('DB_USER', 'root'),
    'password' => Env::get('DB_PASSWORD', ''),
    'dbname'   => Env::get('DB_NAME', 'sevencms'),
];
