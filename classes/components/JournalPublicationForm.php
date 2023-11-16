<?php

namespace APP\plugins\generic\preprintToJournal\classes\components;

use APP\plugins\generic\preprintToJournal\classes\models\Service;
use APP\publication\Publication;
use PKP\components\forms\FieldSelect;
use PKP\context\Context;
use PKP\components\forms\FormComponent;

define('FORM_JOURNAL_PUBLICATION', 'journalPublication');

class JournalPublicationForm extends FormComponent
{
    /** @copydoc FormComponent::$id */
    public $id = FORM_JOURNAL_PUBLICATION;

    /** @copydoc FormComponent::$method */
    public $method = 'POST';

    public function __construct(string $action, Publication $publication, Context $context, array $locales = [])
    {
        $this->action = $action;
        $this->locales = $locales;

        $this
            ->addField(new FieldSelect('publishingJournalServiceId', [
                'label'         => __('plugins.generic.preprintToJournal.publishingJournal.service.select.label'),
                'description'   => __('plugins.generic.preprintToJournal.publishingJournal.service.select.description'),
                'options'       => $this->getAuthorizedServices(),
                'size'          => 'large',
                'isRequired'    => true,
            ]));
    }

    protected function getAuthorizedServices(): array
    {
        $services = Service::where('status', Service::STATUS_AUTHORIZED)->get();

        return $services->map(function($service) {
            return [
                'label' => $service->name, 
                'value' => $service->id,
            ];
        })->toArray();
    }
}
