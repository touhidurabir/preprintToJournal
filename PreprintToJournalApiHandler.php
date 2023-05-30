<?php

declare(strict_types=1);

namespace APP\plugins\generic\preprintToJournal;

use APP\core\Request;
use APP\core\Application;
use APP\handler\Handler;
use APP\plugins\generic\preprintToJournal\PreprintToJournalPlugin;

class PreprintToJournalApiHandler extends Handler
{
    public const URL_PAGE_HANDLER = 'PreprintToJournalApi';

    public PreprintToJournalPlugin $plugin;

    public function __construct(PreprintToJournalPlugin $plugin)
    {
        parent::__construct();

        $this->plugin = $plugin;
    }

    //
    // Implement template methods from PKPHandler
    //
    /**
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        return parent::authorize($request, $args, $roleAssignments);
    }

    public function authenticate($args, $request)
    {
        dump('I am here');
    }
}