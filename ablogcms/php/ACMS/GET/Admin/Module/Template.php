<?php

use Acms\Services\Facades\Database as DB;
use Acms\Services\Facades\LocalStorage;
use Acms\Services\Facades\Config;
use Acms\Services\Facades\Logger;

class ACMS_GET_Admin_Module_Template extends ACMS_GET
{
    private const STANDARD_TEMPLATE = [
        'templatePath' => '',
        'templateLabel' => '標準',
    ];

    public function auth()
    {
        if (!sessionWithAdministration()) {
            return false;
        }
        return true;
    }

    public function get()
    {
        if (!$this->auth()) {
            Logger::notice('認可されていないページにアクセスしました');
            die403();
        }
        $tplEngine = new Template($this->tpl, new ACMS_Corrector());
        $mid = (int)$this->Get->get('mid');
        $selectedTpl = $this->Get->get('selectedTpl');

        if ($mid <= 0) {
            return $tplEngine->get();
        }

        $module = $this->getModule($mid);
        if (is_null($module)) {
            return $tplEngine->get();
        }

        $templates = $this->getModuleTemplates($module, $selectedTpl);
        $selectetTemplate = $this->getSelectedTemplate($templates, $selectedTpl);
        $fixedTemplate = $this->getFixedTemplate($templates, $module);
        if ($selectetTemplate !== null) {
            if ($fixedTemplate !== null) {
                $this->renderTemplates($tplEngine, [$selectetTemplate, $fixedTemplate], $module);
            } else {
                $this->renderTemplates($tplEngine, array_merge([self::STANDARD_TEMPLATE], $templates), $module);
            }
        } else {
            if ($fixedTemplate !== null) {
                $this->renderTemplates($tplEngine, [$fixedTemplate], $module);
            } else {
                $this->renderTemplates($tplEngine, array_merge([self::STANDARD_TEMPLATE], $templates), $module);
            }
        }

        return $tplEngine->get();
    }

    /**
     * 固定テンプレートかどうか調べるためのモジュール一覽を取得するためのSQL_Selectを生成する
     *
     * @param string $name モジュール名
     * @return SQL_Select
     */
    protected function buildModuleListSql(string $name): SQL_Select
    {
        $sql = SQL::newSelect('module');
        $sql->addWhereOpr('module_name', $name);
        $sql->addWhereOpr('module_layout_use', '1');

        return $sql;
    }

    /**
     * 指定したモジュール名のモジュールを配列で取得する
     *
     * @param string $name モジュール名
     * @return array{module_id: int, module_identifier: string, module_name: string}[]
     */
    protected function getModuleList(string $name): array
    {
        $sql = $this->buildModuleListSql($name);
        $query = $sql->get(dsn());
        $modules = DB::query($query, 'all');

        return $modules;
    }

    /**
     * モジュールのテンプレートを取得する
     *
     * @param array{module_name: string, module_identifier: string} $module モジュール
     * @param string $selectedTpl 選択中のテンプレート名
     * @return array{templatePath: string, templateLabel: string}[]
     */
    protected function getModuleTemplates(array $module, string $selectedTpl): array
    {
        $themes = [];
        $theme = config('theme');
        $tplModuleDir = 'include/module/template/';
        while (!empty($theme)) {
            array_unshift($themes, $theme);
            $theme  = preg_replace('/^[^@]*?(@|$)/', '', $theme);
        }
        array_unshift($themes, 'system');

        $name = $module['module_name'];
        $identifier = $module['module_identifier'];
        $modules = $this->getModuleList($name);

        //---------------
        // layout module
        $tplAry     = [];
        $tplLabels  = [];
        foreach ($themes as $themeName) {
            $baseDir = THEMES_DIR . $themeName . '/' . $tplModuleDir;
            $dir = THEMES_DIR . $themeName . '/' . $tplModuleDir . $name . '/';
            if (!LocalStorage::validateDirectoryTraversalPath($dir, $baseDir, false)) {
                continue;
            }
            if (LocalStorage::isDirectory($dir)) {
                $templateDir = opendir($dir);
                if ($templateDir === false) {
                    continue;
                }
                while ($tpl = readdir($templateDir)) {
                    preg_match('/(?:.*)\/(.*)(?:\.([^.]+$))/', $dir . $tpl, $info);
                    /**
                     * $info[1] はファイル名
                     * $info[2] は拡張子
                     */
                    if (!isset($info[1]) || !isset($info[2])) {
                        continue;
                    }
                    if (strncasecmp($tpl, '.', 1) === 0) {
                        // ファイル名がドットで始まる場合はテンプレートファイルとして認識しない
                        continue;
                    }
                    if ($info[2] === 'yaml') {
                        // 拡張子がyamlの場合はテンプレートファイルとして認識しない
                        continue;
                    }
                    $tplAry[] = $tpl;
                }
                $tplAry = array_values(array_filter($tplAry, function ($tpl) use ($selectedTpl, $module, $modules) {
                    if ($tpl === $selectedTpl) {
                        // 選択中のテンプレートは常に表示
                        return true;
                    }

                    if ($this->isFixedTemplatePath($tpl, $module)) {
                        // 固定テンプレートは常に表示
                        return true;
                    }

                    if ($this->usedAsFixedTemplate($tpl, $modules)) {
                        // 他のモジュールで固定テンプレートとして使用されているテンプレートは除外
                        return false;
                    }

                    return true;
                }));
                if ($labelAry = Config::yamlLoad($dir . 'label.yaml')) {
                    $tplLabels += $labelAry;
                }
            }
        }
        $tplAry = array_unique($tplAry);

        $tplSort = [];
        // label.yamlで定義されているテンプレートを優先的に処理
        foreach ($tplLabels as $tpl => $label) {
            $key = array_search($tpl, $tplAry, true);
            if ($key !== false) {
                // ラベルが定義されているテンプレートを追加
                $tplSort[] = [
                    'templatePath' => $tpl,
                    'templateLabel' => $label,
                ];
                // ラベルが定義されているテンプレートを削除
                unset($tplAry[$key]);
            }
        }
        // label.yamlで定義されていないテンプレートを処理
        foreach ($tplAry as $tpl) {
            $tplSort[] = [
                'templatePath' => $tpl,
                'templateLabel' => $tpl, // ラベルがない場合はファイル名をそのまま表示用ラベルとして使用
            ];
        }

        return $tplSort;
    }

    /**
     * 指定したモジュールIDのモジュールを取得する
     *
     * @param int $mid モジュールID
     * @return array{module_name: string, module_identifier: string}|null
     */
    protected function getModule(int $mid): ?array
    {
        $sql = SQL::newSelect('module');
        $sql->addWhereOpr('module_id', $mid);
        /** @var array{module_name: string, module_identifier: string}|false $module */
        $module = DB::query($sql->get(dsn()), 'row');

        return is_array($module) ? $module : null;
    }

    /**
     * 選択中のテンプレートを取得する
     *
     * @param array{templatePath: string, templateLabel: string}[] $templates テンプレート一覧
     * @param string $selectedTpl 選択中のテンプレート名
     * @return array{templatePath: string, templateLabel: string}|null
     */
    protected function getSelectedTemplate(array $templates, string $selectedTpl): ?array
    {
        foreach ($templates as $template) {
            if ($template['templatePath'] === $selectedTpl) {
                return $template;
            }
        }

        return null;
    }

    /**
     * 固定テンプレートを取得する
     *
     * @param array{templatePath: string, templateLabel: string}[] $templates テンプレート一覧
     * @param array{module_name: string, module_identifier: string} $module モジュール
     * @return array{templatePath: string, templateLabel: string}|null
     */
    protected function getFixedTemplate(array $templates, array $module): ?array
    {
        foreach ($templates as $template) {
            if ($this->isFixedTemplate($template, $module)) {
                return $template;
            }
        }

        return null;
    }

    /**
     * テンプレートが固定テンプレートかどうかを調べる
     *
     * @param array{templatePath: string, templateLabel: string} $template テンプレート
     * @param array{module_name: string, module_identifier: string} $module モジュール
     * @return bool
     */
    protected function isFixedTemplate(array $template, array $module): bool
    {
        $path = $template['templatePath'];
        if ($this->isFixedTemplatePath($path, $module)) {
            return true;
        }
        return false;
    }

    /**
     * テンプレートパスが固定テンプレートかどうかを調べる
     *
     * @param string $path テンプレートパス
     * @param array{module_name: string, module_identifier: string} $module モジュール
     * @return bool
     */
    protected function isFixedTemplatePath(string $path, array $module): bool
    {
        if ($path === '') {
            return false;
        }
        $filename = pathinfo($path, PATHINFO_FILENAME);
        $identifier = $module['module_identifier'];
        if ($filename === $identifier) {
            // モジュール識別子と同じ名前のテンプレートは固定テンプレート
            return true;
        }
        $suffix = config('module_identifier_duplicate_suffix');
        if (preg_match('/^' . preg_quote($filename, '/') . $suffix . '/', $identifier)) {
            // モジュール識別子に重複サフィックスを付与した名前のテンプレートは固定テンプレート
            return true;
        }
        return false;
    }

    /**
     * テンプレートパスが他のモジュールで固定テンプレートとして使用されているかどうかを調べる
     *
     * @param string $path テンプレートパス
     * @param array{module_name: string, module_identifier: string}[] $modules モジュール一覧
     * @return bool
     */
    protected function usedAsFixedTemplate(string $path, array $modules): bool
    {
        foreach ($modules as $module) {
            if ($this->isFixedTemplatePath($path, $module)) {
                return true;
            }
        }
        return false;
    }
    /**
     * テンプレートをレンダリングする
     *
     * @param Template $tplEngine テンプレートエンジン
     * @param array{templatePath: string, templateLabel: string}[] $templates テンプレート一覧
     * @param array{module_name: string, module_identifier: string} $module モジュール
     * @return void
     */
    protected function renderTemplates(Template $tplEngine, array $templates, array $module): void
    {
        foreach ($templates as $i => $template) {
            if ($i < count($templates) - 1) {
                $tplEngine->add(['glue', 'template:loop']);
            }
            $tplEngine->add('template:loop', $this->isFixedTemplate($template, $module) ? [
                'templatePath' => '',
                'templateLabel' => '固定テンプレート',
            ] : $template);
        }
    }
}
