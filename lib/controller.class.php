<?php

defined('_SEVEN') or die('No direct script access allowed');

class Controller
{
    protected        $data;
    protected        $model  = null;
    protected array  $params = [];
    protected        $app;

    public function __construct(mixed $app, mixed $data = [])
    {
        $this->data   = $data;
        $this->app    = $app;
        $this->params = $this->app->router->getParams();
    }

    public function getData(): mixed  { return $this->data; }
    public function getModel(): mixed { return $this->model; }
    public function getParams(): array { return $this->params; }

    // Redirect to login if not authenticated
    protected function requireLogin(mixed $res): void
    {
        if (!Auth::isLogin()) {
            $lang = Seven::app()->router->getLanguage();
            $base = Seven::app()->config['baseUrl'];
            header('Location: ' . $base . '/' . $lang . '/auth');
            exit;
        }
    }

    // Redirect to 403 if not admin role
    protected function requireAdmin(mixed $res): void
    {
        $this->requireLogin($res);
        $user = Auth::getCurrentUser();
        if (!$user || $user->role !== 'admin') {
            http_response_code(403);
            exit('Access denied.');
        }
    }

    protected function requireEditor(mixed $res): void
    {
        $this->requireLogin($res);
        $user = Auth::getCurrentUser();
        if (!$user || !$user->isEditor()) {
            http_response_code(403);
            exit('Access denied.');
        }
    }

    protected function requireModerator(mixed $res): void
    {
        $this->requireLogin($res);
        $user = Auth::getCurrentUser();
        if (!$user || !$user->isModerator()) {
            http_response_code(403);
            exit('Access denied.');
        }
    }

    // Build view data array for window.__DATA__ (Vue hybrid pages)
    protected function viewData(array $pageData = []): array
    {
        $user = Auth::getCurrentUser();

        // Build rich language list [{code,name,nativeName,flag,isDefault}]
        $langRows = Language::getActive();
        $langList = array_values(array_map([Language::class, 'rowToArray'], $langRows));
        // Fallback: if DB empty, use config codes
        if (empty($langList)) {
            $langList = array_map(fn($c) => [
                'code' => $c, 'name' => $c, 'nativeName' => $c, 'flag' => '🌐', 'isDefault' => $c === 'en',
            ], $this->app->config['languages'] ?? ['en']);
        }

        // Flatten all site lang strings into i18n for Vue
        $i18n = [];
        foreach (Lang::allGroups() as $group => $keys) {
            foreach ($keys as $k => $v) {
                $i18n[$group . '.' . $k] = $v;
                $i18n[$k] = $v; // flat alias for simple lookups
            }
        }

        return array_merge([
            'isLogin'   => Auth::isLogin(),
            'user'      => $user ? $user->toPublic() : null,
            'languages' => $langList,
            'lang'      => $this->app->router->getLanguage(),
            'i18n'      => $i18n,
        ], $pageData);
    }

    // Extract multilingual fields from POST data
    // e.g. title_en, title_ru → ['en' => '...', 'ru' => '...']
    protected function extractI18n(array $data, string $field): array
    {
        $langs  = $this->app->config['languages'] ?? [];
        $result = [];
        foreach ($langs as $lang) {
            $key = $field . '_' . $lang;
            $result[$lang] = isset($data[$key]) ? strip_tags(trim($data[$key])) : '';
        }
        return $result;
    }
}
