<?php

namespace APP\plugins\generic\preprintToJournal;

use APP\core\Request;
use PKP\plugins\Hook;
use APP\core\Application;
use PKP\core\JSONMessage;
use PKP\linkAction\LinkAction;
use PKP\plugins\GenericPlugin;
use APP\template\TemplateManager;
use PKP\submission\PKPSubmission;
use PKP\linkAction\request\AjaxModal;
use PKP\components\forms\FormComponent;
use APP\plugins\generic\preprintToJournal\PreprintToJournalSchemaMigration;
use APP\plugins\generic\preprintToJournal\controllers\JournalPublishingHandler;
use APP\plugins\generic\preprintToJournal\controllers\JournalSubmissionHandler;
use APP\plugins\generic\preprintToJournal\classes\components\JournalPublicationForm;
use APP\plugins\generic\preprintToJournal\controllers\tab\user\CustomApiProfileTabHandler;
use APP\plugins\generic\preprintToJournal\controllers\tab\service\PreprintToJournalServiceTabHandler;
use APP\plugins\generic\preprintToJournal\controllers\tab\service\PreprintToJournalServiceGridHandler;

class PreprintToJournalPlugin extends GenericPlugin
{
    public const OPS_JOURNAL_PUBLISH_ALLOW_PAGES = [
        'workflow',
        'authorDashboard',
    ];

    public function __construct()
    {

    }

    /**
     * Determine if running application is OJS or not
     * 
     * @return bool
     */
    public static function isOJS(): bool
    {
        return in_array(strtolower(Application::get()->getName()), ['ojs2', 'ojs']);
    }

    /**
     * Load and initialize the plug-in and register plugin hooks.
     *
     * @param string    $category       Name of category plugin was registered to
     * @param string    $path           The path the plugin was found in
     * @param int       $mainContextId  To identify if the plugin is enabled
     *
     * @return bool True/False value by which it's determined if plugin will be registered or not
     */
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);

        if (!$success || !$this->getEnabled()) {
            return $success;
        }

        if (self::isOJS()) {
            $this->setupJournalSubmissionHandler();
            return $success;
        }

        $this->setJournalPublicationStates();
        $this->setupJournalPublicationTab();
        $this->setupJournalPublishingHandler();
        $this->addJournalPubslishingComponent();

        $this->setupServiceSettingsComponents();
        $this->setupJournalServiceListComponent();

        return $success;
    }

    /**
     * Get a list of link actions for plugin management.
     *
     * @param \APP\core\Request $request    The PKPRequest object
     * @param array             $actionArgs The list of action args to be included in request URLs.
     *
     * @return array                        List of LinkActions
     */
    public function getActions($request, $actionArgs)
    {
        // Get the existing actions
        $actions = parent::getActions($request, $actionArgs);

        // Only add the settings action when the plugin is enabled
        if (!$this->getEnabled()) {
            return $actions;
        }

        // Create a LinkAction that will make a request to the
        // plugin's `manage` method with the `settings` verb.
        $settingsAction = new LinkAction(
            'settings',
            new AjaxModal(
                $request->getRouter()->url(
                    $request,
                    null,
                    null,
                    'manage',
                    null,
                    [
                        'verb' => 'settings',
                        'plugin' => $this->getName(),
                        'category' => 'generic'
                    ]
                ),
                $this->getDisplayName()
            ),
            __('manager.plugins.settings'),
            null
        );

        // Add the LinkAction to the existing actions.
        // Make it the first action to be consistent with
        // other plugins.
        array_unshift($actions, $settingsAction);

        return $actions;
    }

    /**
     * Load a form when the `settings` button is clicked and
     * save the form when the user saves it.
     *
     * @param array             $args       The list of action args to be included in request URLs.
     * @param \APP\core\Request $request    The PKPRequest object
     * 
     * @return \PKP\core\JSONMessage
     */
    public function manage($args, $request)
    {
        if ($request->getUserVar('verb') === 'settings') {
            
            $templateManager = TemplateManager::getManager();
            
            return new JSONMessage(
                true, 
                $templateManager->fetch($this->getTemplateResource('settings.tpl'))
            );
        }

        return parent::manage($args, $request);
    }

    public function setupServiceSettingsComponents(): void
    {
        Hook::add('LoadComponentHandler', function (string $hookName, array $args): bool {

            $component = $args[0];

            if ($component !== 'plugins.generic.preprintToJournal.controllers.tab.service.PreprintToJournalServiceTabHandler') {
                return false;
            }

            PreprintToJournalServiceTabHandler::setPlugin($this);

            return true;
        });
    }

    public function setupJournalServiceListComponent(): void
    {
        Hook::add('LoadComponentHandler', function (string $hookName, array $args): bool {

            $component = $args[0];

            if ($component !== 'plugins.generic.preprintToJournal.controllers.tab.service.PreprintToJournalServiceGridHandler') {
                return false;
            }

            PreprintToJournalServiceGridHandler::setPlugin($this);

            return true;
        });
    }

    public function getOjsJournalPath(): string 
    {
        $request = Application::get()->getRequest();

        return $request->getBaseUrl() . '/' . $request->getContext()->getData('urlPath');
    }

    public function setupJournalPublishingHandler(): void
    {
        Hook::add('LoadComponentHandler', function (string $hookName, array $args): bool {
            $component = $args[0];

            if ($component !== 'plugins.generic.preprintToJournal.controllers.JournalPublishingHandler') {
                return false;
            }

            JournalPublishingHandler::setPlugin($this);
    
            return true;
        });
    }

    public function setJournalPublicationStates(): void
    {
        $request = Application::get()->getRequest();
        
        Hook::add('TemplateManager::display', function (string $hookName, array $args) use ($request): bool {
            $templateMgr = & $args[0]; /** @var \APP\template\TemplateManager $templateMgr */
            $requestedPage = strtolower($templateMgr->getTemplateVars('requestedPage') ?? '');

            if (!$this->shouldShowJournalPublicationTabInOPS($requestedPage)) {
                return false;
            }

            /** @var \APP\submission\Submission $submission */
            $submission = $request
                ->getRouter()
                ->getHandler()
                ->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
            $publication = $submission?->getCurrentPublication(); /** @var \APP\publication\Publication $publication */

            if ($publication?->getData('status') !== PKPSubmission::STATUS_PUBLISHED) {
                return false;
            }

            $context = $request->getContext();
            $locales = $context->getSupportedSubmissionLocaleNames();
            $locales = array_map(
                fn (string $locale, string $name) => ['key' => $locale, 'label' => $name], 
                array_keys($locales), 
                $locales
            );

            $action = $request->getDispatcher()->url(
                $request,
                Application::ROUTE_COMPONENT,
                $context->getData('urlPath'),
                'plugins.generic.preprintToJournal.controllers.JournalPublishingHandler',
                'verify',
            );

            $journalPublicationForm = new JournalPublicationForm(
                action: $action, 
                // action: FormComponent::ACTION_EMIT,
                publication: $publication, 
                context: $context,
                locales: $locales
            );

            import('plugins.generic.preprintToJournal.classes.components.JournalPublicationForm'); // Constant import

            $templateMgr->setConstants([
                'FORM_JOURNAL_PUBLICATION' => FORM_JOURNAL_PUBLICATION,
            ]);

            $components = $templateMgr->getState('components');
            $components[FORM_JOURNAL_PUBLICATION] = $journalPublicationForm->getConfig();

            // $publicationFormIds = $templateMgr->getState('publicationFormIds');
            // $publicationFormIds[] = FORM_JOURNAL_PUBLICATION;

            $templateMgr->setState([
                'components' => $components,
                // 'publicationFormIds' => $publicationFormIds,
            ]);

            return false;
        });
    }

    public function setupJournalPublicationTab(): void
    {
        Hook::add('Template::Workflow::Publication', function (string $hookName, array $args): bool {
            $templateMgr = & $args[1]; /** @var \APP\template\TemplateManager $templateMgr */
            $output = & $args[2];
            
            $requestedPage = strtolower($templateMgr->getTemplateVars('requestedPage') ?? '');
            if (!$this->shouldShowJournalPublicationTabInOPS($requestedPage)) {
                return false;
            }

            $submission = $templateMgr->getTemplateVars('submission'); /** @var \App\submission\Submission $submission */
            if($submission?->getCurrentPublication()?->getData('status') !== PKPSubmission::STATUS_PUBLISHED) {
                return false;
            }

            // Had to set it here as it's getting replaced for resone, weird. need to check it back
            $templateMgr->assign([
                'journalPublishingUrl' => $templateMgr->getState('components')['journalPublication']['action']
            ]);

            $output .= $templateMgr->fetch($this->getTemplateResource('journalPublicationTab.tpl'));
            
            return false;
        });

    }

    public function setupJournalSubmissionHandler(Request $request = null): void
    {
        Hook::add('LoadComponentHandler', function (string $hookName, array $args): bool {
            
            $component = $args[0];

            if ($component !== 'plugins.generic.preprintToJournal.controllers.JournalSubmissionHandler') {
                return false;
            }

            JournalSubmissionHandler::setPlugin($this);

            return true;
        });
    }

    public function addJournalPubslishingComponent(): void
    {
        $templateMgr = TemplateManager::getManager(); /** @var \APP\template\TemplateManager $templateMgr */

        $templateMgr->addJavaScript(
            name: 'PreprintToJournalComponent',
            script: $this->getJavaScriptURL() . DIRECTORY_SEPARATOR . 'PreprintToJournal.js',
            args: [
                'inline' => false,
                'contexts' => ['backend'],
                'priority' => TemplateManager::STYLE_SEQUENCE_LAST
            ]
        );
    }

    /**
     * Provide a name for this plugin
     *
     * @return string
     */
    public function getDisplayName()
    {
        return __('plugins.generic.preprintToJournal.displayName');
    }

    /**
     * Provide a description for this plugin
     *
     * @return string
     */
    public function getDescription()
    {
        return __('plugins.generic.preprintToJournal.description');
    }

    /**
     * @copydoc Plugin::getInstallMigration()
     */
    public function getInstallMigration()
    {
        return new PreprintToJournalSchemaMigration;
    }

    /**
     * Get the JavaScript URL for this plugin.
     */
    public function getJavaScriptURL()
    {
        return Application::get()->getRequest()->getBaseUrl() . DIRECTORY_SEPARATOR . $this->getPluginPath() . DIRECTORY_SEPARATOR . 'js';
    }

    /**
     * Get the CSS URL for this plugin.
     */
    public function getCssURL()
    {
        return Application::get()->getRequest()->getBaseUrl() . DIRECTORY_SEPARATOR . $this->getPluginPath() . DIRECTORY_SEPARATOR . 'css';
    }

    protected function shouldShowJournalPublicationTabInOPS(string $requestedPage): bool
    {
        return in_array(
            $requestedPage, 
            collect(self::OPS_JOURNAL_PUBLISH_ALLOW_PAGES)
                ->map(fn ($page) => strtolower($page))
                ->filter()
                ->toArray()
        );
    }

}
