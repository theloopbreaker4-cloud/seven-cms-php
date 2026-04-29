<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

/**
 * Middleware pipeline.
 *
 * Usage:
 *   $result = Pipeline::make($request)
 *       ->through([new AuthMiddleware(), new ThrottleMiddleware()])
 *       ->then(function(Request $req) use ($handler) {
 *           return $handler($req);
 *       });
 */
class Pipeline
{
    private Request $request;
    /** @var MiddlewareInterface[] */
    private array $middleware = [];

    private function __construct(Request $request)
    {
        $this->request = $request;
    }

    public static function make(Request $request): static
    {
        return new static($request);
    }

    /** @param MiddlewareInterface[] $middleware */
    public function through(array $middleware): static
    {
        $this->middleware = $middleware;
        return $this;
    }

    public function then(callable $destination): mixed
    {
        $chain = array_reduce(
            array_reverse($this->middleware),
            fn(callable $carry, MiddlewareInterface $mw) =>
                fn(Request $req) => $mw->handle($req, $carry),
            $destination
        );

        return $chain($this->request);
    }
}
