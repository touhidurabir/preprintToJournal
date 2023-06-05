<?php

namespace APP\plugins\generic\preprintToJournal\controllers;

use APP\handler\Handler;
use PKP\core\PKPRequest;
use PKP\security\authorization\UserRequiredPolicy;
use APP\plugins\generic\preprintToJournal\PreprintToJournalPlugin;

class JournalPublishingHandler extends Handler
{
    public static PreprintToJournalPlugin $plugin;

    public static function setPlugin(PreprintToJournalPlugin $plugin): void
    {
        static::$plugin = $plugin;
    }
    
    public function authorize($request, &$args, $roleAssignments)
    {
        // User must be logged in
        $this->addPolicy(new UserRequiredPolicy($request));

        return parent::authorize($request, $args, $roleAssignments);
    }

    public function verify(array $args, PKPRequest $request)
    {
        
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\plugins\generic\preprintToJournal\controllers\JournalPublishingHandler', '\JournalPublishingHandler');
}
