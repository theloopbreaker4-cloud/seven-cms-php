<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

class LanguageApiController extends ApiController
{
    public function __construct($app) { parent::__construct($app); }

    // GET /api/language/list — public, returns active languages with flag+native
    public function list($req, $res, $params)
    {
        $rows = Language::getActive();
        return $this->json(array_map([Language::class, 'rowToArray'], $rows));
    }

    // GET /api/language/known — all known codes for admin dropdown
    public function known($req, $res, $params)
    {
        $this->requireAdminToken();
        $active = Language::getActiveCodes();
        $out = [];
        foreach (Language::KNOWN as $code => $info) {
            $out[] = [
                'code'       => $code,
                'name'       => $info['name'],
                'nativeName' => $info['native'],
                'flag'       => $info['flag'],
                'active'     => in_array($code, $active, true),
            ];
        }
        return $this->json($out);
    }
}
