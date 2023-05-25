<?php

declare(strict_types=1);

namespace APP\plugins\generic\preprintToJournal;

use APP\core\Request;
use APP\core\Application;
use APP\handler\Handler;
use APP\template\TemplateManager;
use APP\plugins\generic\preprintToJournal\PreprintToJournalPlugin;

class PreprintToJournalApiKeyHandler extends Handler
{
    public const URL_PAGE_HANDLER = 'PreprintToJournalApiKey';

    public PreprintToJournalPlugin $plugin;

    public function __construct(PreprintToJournalPlugin $plugin)
    {
        parent::__construct();

        $this->plugin = $plugin;
    }

    public function index($args, $request)
    {
        $templateMgr = TemplateManager::getManager($request); /** @var TemplateManager $templateMgr */

        return $templateMgr->display(
            $this->plugin->getTemplateResource(
                'customApiProfile.tpl'
            )
        );
    }
}