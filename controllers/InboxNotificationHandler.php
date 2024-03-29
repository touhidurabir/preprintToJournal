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
        return true;
    }

    public function inbox(array $args, Request $request)
    {
        $ldnNotificationManager = new LDNNotificationManager;

        $notification = $ldnNotificationManager->storeNotification(
            LDNNotificationManager::DIRECTION_INBOUND,
            $request->getUserVar('notification'),
            $request->getUserVar('submissionId')
        );

        $ldnNotificationManager->executeActionBasedOnNotification($notification);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\plugins\generic\preprintToJournal\controllers\InboxNotificationHandler', '\InboxNotificationHandler');
}
