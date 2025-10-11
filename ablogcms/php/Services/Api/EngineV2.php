<?php

namespace Acms\Services\Api;

use Acms\Services\Api\Exceptions\NotFoundModuleException;
use Acms\Services\Facades\Database as DB;
use Acms\Services\Facades\Logger as AcmsLogger;
use Acms\Services\Template\Twig\GetModule as TwigModule;
use ACMS_Filter;
use SQL;

class EngineV2 extends Contracts\Api
{
    /**
     * @param string $identifier
     */
    public function get($identifier)
    {
        try {
            $apiInfo = [
                'identifier' => $identifier,
                'moduleName' => $this->getModuleName($identifier),
            ];
            $this->exec($apiInfo);
        } catch (NotFoundModuleException $e) {
            AcmsLogger::error('API機能: 有効なモジュールIDが存在しません', [
                'identifier' => $identifier,
            ]);
            $this->notFound('有効なモジュールIDが存在しません');
        }
    }

    /**
     * APIのレスポンスを組み立て
     *
     * @param array $apiInfo
     * @return string
     */
    protected function buildResponse(array $apiInfo): string
    {
        define('IS_API_BUILD', true);
        $identifier = $apiInfo['identifier'];
        $moduleName = $apiInfo['moduleName'];

        $engine = new TwigModule();
        $data = $engine->moduleFunction($moduleName, $identifier);
        $json = json_encode($data);

        return $this->jsonValidate($json) ? $json : '{}'; // GETモジュールの結果が不正なJSONの場合は'{}'を返す
    }

    /**
     * @param string $identifier
     * @return string
     * @throws NotFoundModuleException
     */
    protected function getModuleName($identifier)
    {
        $sql = SQL::newSelect('module');
        $sql->setSelect('module_name');
        $sql->addLeftJoin('blog', 'blog_id', 'module_blog_id');
        ACMS_Filter::blogTree($sql, BID, 'ancestor-or-self');
        $sql->addWhereOpr('module_identifier', $identifier);
        $sql->addWhereOpr('module_status', 'open');
        $sql->addWhereOpr('module_api_use', 'on');
        $where = SQL::newWhere();
        $where->addWhereOpr('module_blog_id', BID, '=', 'OR');
        $where->addWhereOpr('module_scope', 'global', '=', 'OR');
        $sql->addWhere($where);
        $q = $sql->get(dsn());
        $module = DB::query($q, 'one');

        if (empty($module)) {
            throw new NotFoundModuleException();
        }
        if (strpos($module, 'V2_') !== 0) {
            throw new NotFoundModuleException();
        }
        return $module;
    }
}
