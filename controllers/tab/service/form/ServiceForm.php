<?php

namespace APP\plugins\generic\preprintToJournal\controllers\tab\service\form;

use PKP\form\Form;
use APP\core\Services;
use PKP\context\Context;
use PKP\core\PKPRequest;
use APP\core\Application;
use APP\template\TemplateManager;
use APP\notification\Notification;
use PKP\form\validation\FormValidator;
use APP\notification\NotificationManager;
use PKP\form\validation\FormValidatorUrl;
use PKP\form\validation\FormValidatorCSRF;
use PKP\form\validation\FormValidatorPost;
use APP\plugins\generic\preprintToJournal\classes\models\Service;
use APP\plugins\generic\preprintToJournal\PreprintToJournalPlugin;

class ServiceForm extends Form
{
    public PreprintToJournalPlugin $plugin;

    protected ?Service $service = null;

    protected Context $context;
    
    /**
     * Create a new instance of service form
     */
    public function __construct(PreprintToJournalPlugin $plugin, Service $service = null)
    {
        $this->plugin = $plugin;
        $this->service = $service;

        if ($this->service) {
            $contextService = Services::get('context'); /** @var \APP\services\ContextService $contextService */
            $this->context = $contextService->get((int)$this->service->context_id);
        } else {
            $this->context = Application::get()->getRequest()->getContext();
        }

        parent::__construct($plugin->getTemplateResource('services/form.tpl'));

        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
        
        $this->addCheck(new FormValidatorUrl(
            $this, 
            'url', 
            FormValidator::FORM_VALIDATOR_REQUIRED_VALUE,
            'user.profile.form.urlInvalid'
        ));

        $this->addCheck(new FormValidator(
            $this, 
            'name', 
            FormValidator::FORM_VALIDATOR_REQUIRED_VALUE,
            'plugins.generic.preprintToJournal.service.from.field.name.required'
        ));
    }

    /**
     * @copydoc \PKP\form\Form::initData()
     */
    public function initData()
    {
        if ($this->service) {
            $this->_data = [
                'serviceId'     => $this->service->id,
                'name'          => $this->service->name,
                'description'   => $this->service->description,
                'url'           => $this->service->url,
                'ip'            => $this->service->ip,
            ];
        }

        parent::initData();
    }

    /**
     * @copydoc \PKP\form\Form::readInputData()
     */
    public function readInputData()
    {
        $this->readUserVars(['name', 'description', 'url', 'ip', 'serviceId']);

        parent::readInputData();
    }

    /**
     * @copydoc \PKP\form\Form::fetch()
     */
    public function fetch($request, $template = null, $display = false)
    {
        $templateManager = TemplateManager::getManager($request);
        $templateManager->assign([
            'pluginName' => $this->plugin->getName(),
            'op' => $this->service ? 'update' : 'store',
        ]);

        return parent::fetch($request, $template, $display);
    }

    /**
     * @copydoc \PKP\form\Form::execute()
     */
    public function execute(...$functionArgs)
    {
        $data = [
            'context_id'    => $this->context->getId(),
            'name'          => $this->getData('name'),
            'description'   => $this->getData('description'),
            'url'           => $this->getData('url'),
            'ip'            => $this->getData('ip') ?? gethostbyname(parse_url($this->getData('url'), PHP_URL_HOST)),
        ];

        $this->service
            ? $this->service->update($data) 
            : Service::create(array_merge($data, [
                'status'        => Service::STATUS_PENDING,
                'creator_id'    => Application::get()->getRequest()->getUser()->getId(),
            ]));

        $notificationMgr = new NotificationManager();
        $notificationMgr->createTrivialNotification(
            Application::get()->getRequest()->getUser()->getId(),
            Notification::NOTIFICATION_TYPE_SUCCESS,
            ['contents' => __('common.changesSaved')]
        );

        return parent::execute();
    }

    /**
     * Reset the from input data
     */
    public function reset(): void
    {
        $this->_data = [];
    }
}
