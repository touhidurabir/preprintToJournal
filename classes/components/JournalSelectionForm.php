<?php

namespace APP\plugins\generic\preprintToJournal\classes\components;

use PKP\core\PKPString;
use PKP\context\Context;
use APP\publication\Publication;
use PKP\components\forms\FieldHTML;
use PKP\components\forms\FieldSelect;
use PKP\components\forms\FieldOptions;
use PKP\components\forms\FormComponent;
use Illuminate\Database\Eloquent\Collection;
use APP\plugins\generic\preprintToJournal\classes\models\Service;

define('FORM_JOURNAL_SELECTION', 'journalSelection');

class JournalSelectionForm extends FormComponent
{
    /** @var string id for the form's group and page configuration */
    public const GROUP = 'preprintToJournalSelection';

    /** @copydoc FormComponent::$id */
    public $id = FORM_JOURNAL_SELECTION;

    /** @copydoc FormComponent::$method */
    public $method = 'POST';

    public function __construct(string $action, Publication $publication, Context $context, array $locales = [])
    {
        $this->action = $action;
        $this->locales = $locales;

        $services = Service::where('status', Service::STATUS_AUTHORIZED)
            ->where('active', true)
            ->get();

        $this
            ->addField(new FieldHTML('introduction', [
                'label' => __('plugins.generic.preprintToJournal.publishingJournal.service.starting.title'),
                'description' => __('plugins.generic.preprintToJournal.publishingJournal.service.starting.description'),
            ]))
            ->addField(new FieldOptions('publishingJournalServiceId', [
                'label'         => __('plugins.generic.preprintToJournal.publishingJournal.service.select.label'),
                'description'   => __('plugins.generic.preprintToJournal.publishingJournal.service.select.description'),
                'options'       => $this->getAuthorizedServices($services),
                'size'          => 'large',
                'isRequired'    => true,
                'type'          => 'radio',
            ]));
        
        $services->each(function(Service $service) {
            if (trim(PKPString::html2text($service->description))) {
                $this->addField(new FieldHTML('serviceDescription' . $service->id, [
                    'label' => $service->name,
                    'description' => $service->description,
                    'showWhen' => ['publishingJournalServiceId', $service->id],
                ]), [FIELD_POSITION_AFTER, 'publishingJournalServiceId']);
            }
        });
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
                    'label' => __('plugins.generic.preprintToJournal.publishingJournal.service.select.button.title'),
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

    protected function getAuthorizedServices(Collection $services): array
    {
        return $services->map(function($service) {
            return [
                'label' => $service->name, 
                'value' => $service->id,
            ];
        })->toArray();
    }
}
