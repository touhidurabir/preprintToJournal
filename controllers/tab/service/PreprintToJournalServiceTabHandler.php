<?php

namespace APP\plugins\generic\preprintToJournal\controllers\tab\service;

use APP\handler\Handler;
use APP\core\Request;
use PKP\core\JSONMessage;
use APP\template\TemplateManager;
use APP\notification\NotificationManager;
use APP\plugins\generic\preprintToJournal\classes\models\RemoteService;
use APP\plugins\generic\preprintToJournal\classes\models\Service;
use APP\plugins\generic\preprintToJournal\PreprintToJournalPlugin;
use APP\plugins\generic\preprintToJournal\controllers\tab\service\form\ServiceForm;
use APP\plugins\generic\preprintToJournal\controllers\tab\service\ServiceManager;

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

    public function create(array $args, Request $request): JSONMessage
    {
        $serviceFrom = new ServiceForm(static::$plugin);
        $serviceFrom->initData();

        return new JSONMessage(true, $serviceFrom->fetch($request));
    }

    public function store(array $args, Request $request): JSONMessage
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

    public function edit(array $args, Request $request): JSONMessage
    {
        $serviceFrom = new ServiceForm(static::$plugin, Service::find($request->getUserVar('id')));
        $serviceFrom->initData();

        return new JSONMessage(true, $serviceFrom->fetch($request));
    }

    public function update(array $args, Request $request): JSONMessage
    {   
        $service = Service::find((int) $args['serviceId']);
        $serviceFrom = new ServiceForm(static::$plugin, $service);
        $serviceFrom->readInputData();

        if ($serviceFrom->validate()) {
            
            $serviceFrom->execute();
            $notificationMgr = new NotificationManager();
            $notificationMgr->createTrivialNotification($request->getUser()->getId());
            $serviceFrom->reset();

            return \PKP\db\DAO::getDataChangedEvent($service->id);
        }

        return new JSONMessage(false);
    }

    public function delete(array $args, Request $request): JSONMessage
    {
        if ($args['id']) {
            Service::find($args['id'])->delete();

            $notificationMgr = new NotificationManager();
            $notificationMgr->createTrivialNotification($request->getUser()->getId());

            return \PKP\db\DAO::getDataChangedEvent($args['id']);
        }

        return new JSONMessage(false);
    }

    public function activeStatusUpdate(array $args, Request $request): JSONMessage
    {
        if ($args['id']) {
            Service::find($args['id'])->update([
                'active' => $args['action'],
            ]);

            $notificationMgr = new NotificationManager();
            $notificationMgr->createTrivialNotification($request->getUser()->getId());

            return \PKP\db\DAO::getDataChangedEvent($args['id']);
        }

        return new JSONMessage(false);
    }

    public function register(array $args, Request $request): JSONMessage
    {
        $service = Service::find($args['id'] ?? null);

        if (!$service) {
            return new JSONMessage(false);
        }

        if ((new ServiceManager)->register($service)) {
            $notificationMgr = new NotificationManager();
            $notificationMgr->createTrivialNotification($request->getUser()->getId());

            return \PKP\db\DAO::getDataChangedEvent($args['id']);
        }

        return new JSONMessage(false);
    }

    public function respond(array $args, Request $request): JSONMessage
    {
        $remoteService = RemoteService::find($args['id'] ?? null);

        if (!$remoteService) {
            return new JSONMessage(false);
        }

        if ((new ServiceManager)->respond($remoteService, $args['statusResponse'])) {
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
