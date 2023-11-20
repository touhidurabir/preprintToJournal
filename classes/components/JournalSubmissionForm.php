<?php

namespace APP\plugins\generic\preprintToJournal\classes\components;

use APP\facades\Repo;
use PKP\context\Context;
use APP\publication\Publication;
use PKP\components\forms\FieldSelect;
use PKP\components\forms\FieldOptions;
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

        $this->addJournalLocaleOptions();
        $this->addJournalSectionOptions();
        $this->addJournalChecklistOptions();
        $this->addJournalPrivacyConcentOptions();
        $this->addPreprintConfigs($publication, $primaryLocale);
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
        $locales = $this->journalConfigurations['locales'] ?? [];

        if (count($locales) <= 0) {
            return;
        }

        $this->addField(new FieldOptions('journalLocale', [
            'label' => __('submission.submit.submissionLocale'),
            'description' => __('submission.submit.submissionLocaleDescription'),
            'type' => 'radio',
            'options' => $locales,
            'value' => '',
            'isRequired' => true,
        ]));
    }

    protected function addJournalSectionOptions(): void
    {
        $sections = $this->journalConfigurations['sections'] ?? [];

        if (count($sections) <= 0) {
            return;
        }

        $this->addField(new FieldOptions('journalSectionId', [
            'type' => 'radio',
            'label' => 'Journal Section',
            'description' => 'Submissions must be made to one of the journal\'s sections.',
            'options' => $sections,
            'value' => '',
            'isRequired' => true,
        ]));
    }

    protected function addJournalChecklistOptions(): void
    {
        $submissionChecklists = $this->journalConfigurations['submissionChecklists'] ?? [];

        if (count($submissionChecklists) <= 0) {
            return;
        }

        $this->addField(new FieldOptions('journalSubmissionRequirements', [
            'label' => $submissionChecklists['label'],
            'description' => $submissionChecklists['description'],
            'options' => $submissionChecklists['options'],
            'value' => false,
            'isRequired' => true,
        ]));
    }

    protected function addJournalPrivacyConcentOptions(): void
    {
        $privacyConcents = $this->journalConfigurations['privacyConcents'] ?? [];

        if (count($privacyConcents) <= 0) {
            return;
        }

        $this->addField(new FieldOptions('journalPrivacyConsent', [
            'label' => $privacyConcents['label'],
            'options' => $privacyConcents['options'],
            'value' => false,
            'isRequired' => true,
        ]));
    }

    protected function addPreprintConfigs(Publication $publication, string $primaryLocale): void
    {
        $this
            ->addField(new FieldRichText('preprintTitle', [
                'label' => __('common.title'),
                'size' => 'oneline',
                'isRequired' => true,
                'value' => $publication->getData('title', $primaryLocale),
            ]))
            ->addField(new FieldRichTextarea('preprintAbstract', [
                'label' => __('common.abstract'),
                'isMultilingual' => false,
                'isRequired' => true,
                'size' => 'large',
                'wordLimit' => 1000,
                'value' => $publication->getData('abstract', $primaryLocale) ?? '',
            ]));
    }

}
