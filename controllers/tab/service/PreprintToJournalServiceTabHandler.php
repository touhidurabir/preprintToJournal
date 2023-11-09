<?php

namespace APP\plugins\generic\preprintToJournal\controllers\tab\service;

use APP\handler\Handler;
use PKP\core\PKPRequest;
use APP\core\Application;
use PKP\core\JSONMessage;
use APP\template\TemplateManager;
use APP\notification\NotificationManager;
use APP\plugins\generic\preprintToJournal\classes\models\Service;
use APP\plugins\generic\preprintToJournal\PreprintToJournalPlugin;
use APP\plugins\generic\preprintToJournal\controllers\tab\service\form\ServiceForm;
use APP\plugins\generic\preprintToJournal\controllers\tab\service\JournalRegistration;

class PreprintToJournalServiceTabHandler extends Handler
{
    public static PreprintToJournalPlugin $plugin;

    public static function setPlugin(PreprintToJournalPlugin $plugin): void
    {
        static::$plugin = $plugin;
    }

    public function index($args, $request)
    {
        return new JSONMessage(
            true, 
            TemplateManager::getManager()->fetch(static::$plugin->getTemplateResource('services/list.tpl'))
        );
    }

    public function create(array $args, PKPRequest $request): JSONMessage
    {
        $serviceFrom = new ServiceForm(static::$plugin);
        $serviceFrom->initData();

        return new JSONMessage(true, $serviceFrom->fetch($request));
    }

    public function store(array $args, PKPRequest $request): JSONMessage
    {
        $serviceFrom = new ServiceForm(static::$plugin);
        $serviceFrom->readInputData();

        if ($serviceFrom->validate()) {
            
            $serviceFrom->execute();
            $notificationMgr = new NotificationManager();
            $notificationMgr->createTrivialNotification($request->getUser()->getId());
            $serviceFrom->reset();

            return new JSONMessage(true, $serviceFrom->fetch($request));
        }

        return new JSONMessage(false);
    }

    public function edit(array $args, PKPRequest $request): JSONMessage
    {
        $serviceFrom = new ServiceForm(static::$plugin, Service::find($request->getUserVar('id')));
        $serviceFrom->initData();

        return new JSONMessage(true, $serviceFrom->fetch($request));
    }

    public function update(array $args, PKPRequest $request): JSONMessage
    {   
        $service = Service::find((int) $args['serviceId']);
        $serviceFrom = new ServiceForm(static::$plugin, $service);
        $serviceFrom->readInputData();

        if ($serviceFrom->validate()) {
            
            $serviceFrom->execute();
            $notificationMgr = new NotificationManager();
            $notificationMgr->createTrivialNotification($request->getUser()->getId());

            return \PKP\db\DAO::getDataChangedEvent($service->id);
        }

        return new JSONMessage(false);
    }

    public function delete(array $args, PKPRequest $request): JSONMessage
    {
        if ($args['id']) {
            Service::find($args['id'])->delete();

            $notificationMgr = new NotificationManager();
            $notificationMgr->createTrivialNotification($request->getUser()->getId());

            return \PKP\db\DAO::getDataChangedEvent($args['id']);
        }

        return new JSONMessage(false);
    }

    public function register(array $args, PKPRequest $request): JSONMessage
    {
        $service = Service::find($args['id'] ?? null);

        if (!$service) {
            return new JSONMessage(false);
        }

        if ((new JournalRegistration)->register($service)) {
            
            $notificationMgr = new NotificationManager();
            $notificationMgr->createTrivialNotification($request->getUser()->getId());

            return \PKP\db\DAO::getDataChangedEvent($args['id']);
        }

        return new JSONMessage(false);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\plugins\generic\preprintToJournal\controllers\tab\service\PreprintToJournalServiceTabHandler', '\PreprintToJournalServiceTabHandler');
}
