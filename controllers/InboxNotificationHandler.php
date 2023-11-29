<?php

namespace APP\plugins\generic\preprintToJournal\controllers;

use APP\core\Request;
use APP\handler\Handler;
use APP\plugins\generic\preprintToJournal\classes\managers\LDNNotificationManager;
use APP\plugins\generic\preprintToJournal\PreprintToJournalPlugin;

class InboxNotificationHandler extends Handler
{
    public static PreprintToJournalPlugin $plugin;

    public static function setPlugin(PreprintToJournalPlugin $plugin): void
    {
        static::$plugin = $plugin;
    }
    
    public function authorize($request, &$args, $roleAssignments)
    {
        return parent::authorize($request, $args, $roleAssignments);
    }

    public function inbox(array $args, Request $request)
    {
        $ldnNotificationManager = new LDNNotificationManager;

        $ldnNotificationManager->storeNotification(
            LDNNotificationManager::DIRECTION_INBOUND,
            $request->getUserVar('notification'),
            $request->getUserVar('submissionId')
        );
    }
}