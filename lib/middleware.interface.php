<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

interface MiddlewareInterface
{
    /**
     * @param  Request  $request
     * @param  callable $next    — call $next($request) to continue the chain
     * @return mixed             — return a response string to short-circuit, or pass through
     */
    public function handle(Request $request, callable $next): mixed;
}
