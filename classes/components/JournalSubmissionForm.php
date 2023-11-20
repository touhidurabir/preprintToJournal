<?php

namespace APP\plugins\generic\preprintToJournal\classes\components;

use APP\facades\Repo;
use PKP\context\Context;
use APP\publication\Publication;
use PKP\components\forms\FieldSelect;
use PKP\components\forms\FieldRichText;
use PKP\components\forms\FormComponent;
use PKP\components\forms\FieldRichTextarea;
use APP\plugins\generic\preprintToJournal\classes\models\Service;

define('FORM_JOURNAL_SUBMISSION', 'journalSubmission');

class JournalSubmissionForm extends FormComponent
{
    /** @var string id for the form's group and page configuration */
    public const GROUP = 'default';

    /** @copydoc FormComponent::$id */
    public $id = FORM_JOURNAL_SUBMISSION;

    /** @copydoc FormComponent::$method */
    public $method = 'POST';

    protected array $journalConfigurations;

    public function __construct(string $action, Publication $publication, Context $context, array $locales = [], array $values = [], string $selectedLocale = null)
    {
        $this->action = $action;
        $this->locales = $locales;

        $this->journalConfigurations = $values;

        $primaryLocale ??= $context->getPrimaryLocale();

        $this
            ->addField(new FieldRichText('title', [
                'label' => __('common.title'),
                'size' => 'oneline',
                'isRequired' => true,
                'value' => $publication->getData('title', $primaryLocale),
            ]))
            ->addField(new FieldRichTextarea('abstract', [
                'label' => __('common.abstract'),
                'isMultilingual' => false,
                'isRequired' => true,
                'size' => 'large',
                'wordLimit' => 1000,
                'value' => $publication->getData('abstract', $primaryLocale) ?? '',
            ]));
    }

    /**
     * Add a custom button to the form and modify all fields as required
     */
    public function getConfig()
    {
        $this
            ->addPage([
                'id' => self::GROUP,
                'submitButton' => [
                    'label' => __('plugins.generic.preprintToJournal.publishingJournal.service.complete.button.title'),
                    'isPrimary' => true,
                ]
            ])
            ->addGroup([
                'id' => self::GROUP,
                'pageId' => self::GROUP,
            ]);

        foreach ($this->fields as $field) {
            $field->groupId = self::GROUP;
        }

        return parent::getConfig();
    }

    protected function addJournalLocaleOptions(): void
    {

    }

    protected function addJournalSectionOptions(): void
    {
        
    }

    protected function addJournalChecklistOptions(): void
    {
        
    }

    protected function addJournalPrivacyConcentOptions(): void
    {
        
    }

    protected function addPreprintConfigs(): void
    {
        
    }

}
