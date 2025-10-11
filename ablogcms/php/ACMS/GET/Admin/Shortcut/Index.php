<?php

declare(strict_types=1);

use Acms\Services\Facades\Application;
use Acms\Services\Shortcut\Entities\Shortcut;

class ACMS_GET_Admin_Shortcut_Index extends ACMS_GET_Admin
{
    /**
     * @var \Acms\Services\Shortcut\Repository
     */
    protected $ShortcutRepository;

    /**
     * @var \Acms\Services\Shortcut\Helper
     */
    protected $ShortcutService;

    public function get()
    {
        $this->ShortcutRepository = Application::make('shortcut.repository');
        $this->ShortcutService = Application::make('shortcut.helper');

        if (!sessionWithAdministration()) {
            die403();
        }

        $Tpl = new Template($this->tpl, new ACMS_Corrector());
        $Shortcuts = $this->ShortcutRepository->findAll(BID);
        if (empty($Shortcuts)) {
            $this->buildNotFround($Tpl);
            return $Tpl->get();
        }

        $this->buildTpl($Tpl, $Shortcuts);

        return $Tpl->get();
    }

    /**
     * テンプレートの組み立て
     *
     * @param Template $Tpl
     * @param Shortcut[] $Shortcuts
     * @return void
     */
    protected function buildTpl(Template $Tpl, array $Shortcuts)
    {
        foreach ($Shortcuts as $Shortcut) {
            // auth
            $Tpl->add(['auth#' . $Shortcut->getAuth(), 'shortcut:loop']);

            // data
            $Tpl->add('shortcut:loop', [
                'name'  => $Shortcut->getName(),
                'sort' => $Shortcut->getSort(),
                'url'   => $this->ShortcutService->createUrl(
                    $Shortcut->getAdmin(),
                    $Shortcut->getIds()
                ),
                'itemUrl'   => acmsLink([
                    'bid'   => BID,
                    'admin' => 'shortcut_edit',
                    'query' => array_merge($Shortcut->getIds(), [
                        'action' => $Shortcut->getAction(),
                        'admin' => $Shortcut->getAdmin(),
                    ]),
                ]),
            ]);
        }
        $Tpl->add(null, $this->buildField($this->Post, $Tpl));
    }

    /**
     * NotFoundテンプレートの組み立て
     *
     * @return void
     */
    protected function buildNotFround(Template $Tpl)
    {
        $Tpl->add('index#notFound');
    }
}
