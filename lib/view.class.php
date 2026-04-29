<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

class View
{
    protected array   $data       = [];
    protected ?string $path       = null;
    protected ?string $layoutPath = null;
    protected mixed   $router;

    public function __construct(array $data = [], ?string $layoutPath = null, ?string $path = null)
    {
        $this->router     = Seven::app()->router;
        $this->layoutPath = $layoutPath ?: $this->getDefaultLayoutPath();
        $this->path       = $path       ?: $this->getDefaultViewPath($this->router);
        $this->data       = $data;
    }

    public function getDefaultViewPath(mixed $router): string
    {
        $prefix = $router->getMethodPrefix();
        $sub    = $prefix ?: 'site';
        return Seven::app()->config['viewPath']
             . $sub . DS
             . $router->getController() . DS
             . $router->getAction() . '.html';
    }

    public function getDefaultLayoutPath(): string
    {
        $prefix = Seven::app()->router->getMethodPrefix();
        $sub    = $prefix ?: 'site';
        return Seven::app()->config['viewPath']
             . $sub . DS
             . Seven::app()->config['viewLayout'] . '.html';
    }

    public function setData(array $data = []): void
    {
        $this->data = $data;
    }

    public function renderLayout(string $layout = ''): string
    {
        $prefix     = $this->router->getMethodPrefix();
        $sub        = $prefix ?: 'site';
        $layoutFile = Seven::app()->config['viewPath']
                    . $sub . DS
                    . Seven::app()->config['viewLayout'] . '.html';

        // $viewData is available in layout for window.__DATA__
        // It contains all page data except 'content' (the rendered PHP partial)
        $viewData = array_filter(
            $this->data,
            fn($k) => $k !== 'content',
            ARRAY_FILTER_USE_KEY
        );

        // Inject $viewData as a key so includeTemplate can extract it
        $layoutData = array_merge($this->data, ['viewData' => $viewData]);

        ob_start();
        if (file_exists($layoutFile)) {
            $this->includeTemplate($layoutFile, $layoutData);
        } else {
            Seven::app()->logger->error('Layout not found: ' . $layoutFile);
        }
        return ob_get_clean() . PHP_EOL;
    }

    public function render(?string $templateName = null, array $data = []): string
    {
        $templateName = $templateName ?: $this->router->getAction();
        $mergedData   = array_merge($this->data, $data);
        $this->data   = $mergedData;

        $prefix = $this->router->getMethodPrefix();
        $sub    = $prefix ?: 'site';

        $this->path = Seven::app()->config['viewPath']
                    . $sub . DS
                    . $this->router->getController() . DS
                    . $templateName . '.html';

        ob_start();
        if (file_exists($this->path)) {
            $this->includeTemplate($this->path, $mergedData);
        } else {
            Seven::app()->logger->error('View not found: ' . $this->path);
        }
        return ob_get_clean() . PHP_EOL;
    }

    public function renderPartial(?string $partialName = null, array $data = []): string
    {
        if (!$partialName) return '';

        $mergedData = array_merge($this->data, $data);
        $prefix = $this->router->getMethodPrefix();
        $sub    = $prefix ?: 'site';
        $path   = Seven::app()->config['viewPath'] . $sub . DS . '_partial' . DS . $partialName . '.html';

        ob_start();
        if (file_exists($path)) {
            $this->includeTemplate($path, $mergedData);
        } else {
            Seven::app()->logger->error('Partial not found: ' . $path);
        }
        return ob_get_clean() . PHP_EOL;
    }

    // Include a template with data variables extracted into its scope
    private function includeTemplate(string $path, array $data): void
    {
        $safe = array_filter($data, fn($k) => preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $k), ARRAY_FILTER_USE_KEY);
        extract($safe, EXTR_SKIP);
        include $path;
    }

    public function getAllData(): array  { return $this->data; }
    public function getData(string $key): mixed { return $this->data[$key] ?? null; }
    public function getPath(): ?string  { return $this->path; }
}
