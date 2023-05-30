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
                'label' => 'Target OJS Journal URL',
                'description' => 'Target OJS Journal URL',
                'value' => '',
                'size' => 'large',
                'isRequired' => true,
            ]))
            ->addField(new FieldText('apiKey', [
                'label' => 'User API Key',
                'description' => 'User API Key',
                'value' => '',
                'size' => 'large',
                'isRequired' => true
            ]));
    }
}