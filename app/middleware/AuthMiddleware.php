<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): mixed
    {
        if (!Auth::isLogin()) {
            $lang = Seven::app()->router->getLanguage();
            header('Location: ' . Seven::app()->config['baseUrl'] . '/' . $lang . '/auth/login');
            exit;
        }
        return $next($request);
    }
}
