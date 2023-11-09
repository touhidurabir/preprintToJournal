<?php

namespace APP\plugins\generic\preprintToJournal\controllers\tab\service;

use APP\core\Services;
use APP\core\Application;
use PKP\core\PKPApplication;
use PKP\linkAction\LinkAction;
use PKP\controllers\grid\GridRow;
use PKP\linkAction\request\AjaxModal;
use PKP\linkAction\request\RedirectAction;
use PKP\linkAction\request\RemoteActionConfirmationModal;
use APP\plugins\generic\preprintToJournal\classes\models\Service;

class PreprintToJournalServiceGridRow extends GridRow
{
    //
    // Overridden methods from GridRow
    //
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

        assert($element instanceof Service);

        $rowId = $this->getId();

        $contextService = Services::get('context'); /** @var \APP\services\ContextService $contextService */
        $context = $contextService->get((int)$element->context_id);

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
                    'preprintToJournalServiceForm'
                ),
                __('grid.action.edit'),
                'edit'
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
    }
}