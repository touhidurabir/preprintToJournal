<?php

namespace APP\plugins\generic\preprintToJournal\controllers\tab\user;

use PKP\core\JSONMessage;
use APP\notification\NotificationManager;
use PKP\controllers\tab\user\ProfileTabHandler;
use APP\plugins\generic\preprintToJournal\PreprintToJournalPlugin;
use APP\plugins\generic\preprintToJournal\controllers\tab\user\form\CustomApiProfileForm;

class CustomApiProfileTabHandler extends ProfileTabHandler
{
    public static PreprintToJournalPlugin $plugin;

    public static function setPlugin(PreprintToJournalPlugin $plugin): void
    {
        static::$plugin = $plugin;
    }
    
    /**
     * 
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON-formatted response
     */
    public function preprintToJournalApiProfile($args, $request)
    {
        $this->setupTemplate($request);
        $apiProfileForm = new CustomApiProfileForm($request->getUser(), static::$plugin);
        $apiProfileForm->initData();
        return new JSONMessage(true, $apiProfileForm->fetch($request));
    }

    /**
     * 
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON-formatted response
     */
    public function savePreprintToJournalApiProfile($args, $request)
    {
        $this->setupTemplate($request);

        $apiProfileForm = new CustomApiProfileForm($request->getUser(), static::$plugin);
        $apiProfileForm->readInputData();
        if ($apiProfileForm->validate()) {
            $apiProfileForm->execute();
            $notificationMgr = new NotificationManager();
            $notificationMgr->createTrivialNotification($request->getUser()->getId());
            return new JSONMessage(true, $apiProfileForm->fetch($request));
        }
        return new JSONMessage(true, $apiProfileForm->fetch($request));
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\plugins\generic\preprintToJournal\controllers\tab\user\CustomApiProfileTabHandler', '\CustomApiProfileTabHandler');
}