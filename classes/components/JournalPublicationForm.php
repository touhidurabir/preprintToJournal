<?php

namespace APP\plugins\generic\preprintToJournal\classes\components;

use APP\server\Server;
use APP\publication\Publication;
use PKP\components\forms\FieldText;
use PKP\components\forms\FormComponent;

define('FORM_JOURNAL_PUBLICATION', 'journalPublication');

class JournalPublicationForm extends FormComponent
{
    /** @copydoc FormComponent::$id */
    public $id = FORM_JOURNAL_PUBLICATION;

    /** @copydoc FormComponent::$method */
    public $method = 'POST';

    public function __construct(string $action, Publication $publication, Server $context, array $locales = [])
    {
        $this->action = $action;
        $this->locales = $locales;

        $this
            ->addField(new FieldText('publishingJournalUrl', [
                'label' => __('plugins.generic.preprintToJournal.publishingJournal.url.label'),
                'description' => __('plugins.generic.preprintToJournal.publishingJournal.url.description'),
                'value' => '',
                'size' => 'large',
                'isRequired' => true,
            ]))
            ->addField(new FieldText('apiKey', [
                'label' => __('plugins.generic.preprintToJournal.publishingJournal.apiKey.label'),
                'description' => __('plugins.generic.preprintToJournal.publishingJournal.apiKey.description'),
                'value' => '',
                'size' => 'large',
                'isRequired' => true
            ]));
    }
}