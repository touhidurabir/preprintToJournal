<?php

namespace APP\plugins\generic\preprintToJournal\controllers\tab\service;

use APP\core\Services;
use APP\core\Application;
use PKP\linkAction\LinkAction;
use PKP\controllers\grid\GridRow;
use PKP\linkAction\request\AjaxModal;
use PKP\linkAction\request\RedirectAction;
use PKP\linkAction\request\RemoteActionConfirmationModal;
use APP\plugins\generic\preprintToJournal\classes\models\Service;
use APP\plugins\generic\preprintToJournal\PreprintToJournalPlugin;
use APP\plugins\generic\preprintToJournal\classes\models\RemoteService;
use APP\plugins\generic\preprintToJournal\controllers\tab\service\form\ServiceForm;

class PreprintToJournalServiceGridRow extends GridRow
{
    /**
     * @copydoc GridRow::initialize()
     *
     * @param null|mixed $template
     */
    public function initialize($request, $template = null)
    {
        parent::initialize($request, $template);

        // Is this a new row or an existing row?
        $element = $this->getData();

        assert($element instanceof Service || $element instanceof RemoteService);

        $rowId = $this->getId();

        $contextService = Services::get('context'); /** @var \APP\services\ContextService $contextService */
        $context = $contextService->get((int)$element->context_id) ?? $request->getContext();

        if (PreprintToJournalPlugin::isOJS()) {

            if ($element->status !== RemoteService::STATUS_AUTHORIZED) {
                $this->addAction(
                    new LinkAction(
                        'authorize',
                        new RemoteActionConfirmationModal(
                            $request->getSession(),
                            __(
                                'plugins.generic.preprintToJournal.service.authorize.confirm', 
                                ['ServiceName' => $element->name]
                            ),
                            null,
                            $request->getDispatcher()->url(
                                $request,
                                Application::ROUTE_COMPONENT,
                                $context->getData('urlPath'),
                                'plugins.generic.preprintToJournal.controllers.tab.service.PreprintToJournalServiceTabHandler',
                                'respond',
                                null,
                                ['id' => $rowId, 'statusResponse' => RemoteService::STATUS_AUTHORIZED]
                            )
                        ),
                        __('plugins.generic.preprintToJournal.service.action.authorize'),
                        'authorize'
                    )
                );
            }

            if ($element->status !== RemoteService::STATUS_UNAUTHORIZED) {
                $this->addAction(
                    new LinkAction(
                        'unauthorize',
                        new RemoteActionConfirmationModal(
                            $request->getSession(),
                            __(
                                'plugins.generic.preprintToJournal.service.unauthorize.confirm', 
                                ['ServiceName' => $element->name]
                            ),
                            null,
                            $request->getDispatcher()->url(
                                $request,
                                Application::ROUTE_COMPONENT,
                                $context->getData('urlPath'),
                                'plugins.generic.preprintToJournal.controllers.tab.service.PreprintToJournalServiceTabHandler',
                                'respond',
                                null,
                                ['id' => $rowId, 'statusResponse' => RemoteService::STATUS_UNAUTHORIZED]
                            )
                        ),
                        __('plugins.generic.preprintToJournal.service.action.unauthorize'),
                        'unauthorize'
                    )
                );
            }

            return;
        }

        $this->addAction(
            new LinkAction(
                'edit',
                new AjaxModal(
                    $request->getDispatcher()->url(
                        $request,
                        Application::ROUTE_COMPONENT,
                        $context->getData('urlPath'),
                        'plugins.generic.preprintToJournal.controllers.tab.service.PreprintToJournalServiceTabHandler',
                        'edit',
                        null,
                        ['id' => $rowId]
                    ),
                    __('grid.action.edit'),
                    'modal_edit',
                    true,
                    ServiceForm::FORM_ID_UPDATE
                ),
                __('grid.action.edit')
            )
        );
        
        $this->addAction(
            new LinkAction(
                'delete',
                new RemoteActionConfirmationModal(
                    $request->getSession(),
                    __(
                        'plugins.generic.preprintToJournal.service.delete.confirm', 
                        ['ServiceName' => $element->name]
                    ),
                    null,
                    $request->getDispatcher()->url(
                        $request,
                        Application::ROUTE_COMPONENT,
                        $context->getData('urlPath'),
                        'plugins.generic.preprintToJournal.controllers.tab.service.PreprintToJournalServiceTabHandler',
                        'delete',
                        null,
                        ['id' => $rowId]
                    )
                ),
                __('grid.action.remove'),
                'delete'
            )
        );
        
        $this->addAction(
            new LinkAction(
                $element->isActive() ? 'disbale' : 'enable',
                new RemoteActionConfirmationModal(
                    $request->getSession(),
                    __(
                        $element->isActive() 
                            ? 'plugins.generic.preprintToJournal.service.disable.confirm'
                            : 'plugins.generic.preprintToJournal.service.enable.confirm',
                        ['ServiceName' => $element->name]
                    ),
                    null,
                    $request->getDispatcher()->url(
                        $request,
                        Application::ROUTE_COMPONENT,
                        $context->getData('urlPath'),
                        'plugins.generic.preprintToJournal.controllers.tab.service.PreprintToJournalServiceTabHandler',
                        'activeStatusUpdate',
                        null,
                        ['id' => $rowId, 'action' => (int)!$element->active]
                    )
                ),
                $element->isActive() 
                    ? __('plugins.generic.preprintToJournal.service.action.disbale')
                    : __('plugins.generic.preprintToJournal.service.action.enable'),
                $element->isActive() ? 'disbale' : 'enable',
            )
        );

        if ($element instanceof Service && !$element->hasRegistered()) {

            $this->addAction(
                new LinkAction(
                    'register',
                    new RemoteActionConfirmationModal(
                        $request->getSession(),
                        __(
                            'plugins.generic.preprintToJournal.service.register.confirm', 
                            ['ServiceName' => $element->name]
                        ),
                        null,
                        $request->getDispatcher()->url(
                            $request,
                            Application::ROUTE_COMPONENT,
                            $context->getData('urlPath'),
                            'plugins.generic.preprintToJournal.controllers.tab.service.PreprintToJournalServiceTabHandler',
                            'register',
                            null,
                            ['id' => $rowId]
                        )
                    ),
                    __('plugins.generic.preprintToJournal.service.action.register'),
                    'register'
                )
            );
        }
    }
}