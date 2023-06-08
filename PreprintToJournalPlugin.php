<?php

declare(strict_types=1);

namespace APP\plugins\generic\preprintToJournal;

use APP\core\Request;
use PKP\plugins\Hook;
use APP\core\Application;
use PKP\core\PKPContainer;
use PKP\plugins\GenericPlugin;
use APP\template\TemplateManager;
use PKP\submission\PKPSubmission;
use APP\plugins\generic\preprintToJournal\PreprintToJournalSchemaMigration;
use APP\plugins\generic\preprintToJournal\controllers\JournalPublishingHandler;
use APP\plugins\generic\preprintToJournal\controllers\JournalSubmissionHandler;
use APP\plugins\generic\preprintToJournal\classes\components\JournalPublicationForm;
use APP\plugins\generic\preprintToJournal\controllers\tab\user\CustomApiProfileTabHandler;

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

        $request = Application::get()->getRequest();

        $this->registerResponseBindings();

        if (self::isOJS()) {
            $this->callbackShowApiKeyTab();
            $this->setupCustomApiProfileComponentHandler();
            $this->setupJournalSubmissionHandler();

            return $success;
        }

        $this->setJournalPublicationStates();
        $this->setupJournalPublicationTab();
        $this->setupJournalPublishingHandler();

        return $success;
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

            $submission = $templateMgr->getTemplateVars('submission'); /** @var \APP\submission\Submission $submission */
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

            $publicationFormIds = $templateMgr->getState('publicationFormIds');
            $publicationFormIds[] = FORM_JOURNAL_PUBLICATION;

            $templateMgr->setState([
                'components' => $components,
                'publicationFormIds' => $publicationFormIds,
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

    public function callbackShowApiKeyTab(): void
    {
        Hook::add('Template::User::profile', function (string $hookName, array $args): bool {
            [, $templateMgr, &$output] = $args;

            $output .= $templateMgr->fetch($this->getTemplateResource('apiKeyTab.tpl'));
            
            return false;
        });
    }

    public function setupCustomApiProfileComponentHandler(): void
    {
        Hook::add('LoadComponentHandler', function (string $hookName, array $args): bool {
            $component = $args[0];
            if ($component !== 'plugins.generic.preprintToJournal.controllers.tab.user.CustomApiProfileTabHandler') {
                return false;
            }

            CustomApiProfileTabHandler::setPlugin($this);

            return true;
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
    public function getJavaScriptURL($request)
    {
        return $request->getBaseUrl() . '/' . $this->getPluginPath() . '/js';
    }

    /**
     * Get the CSS URL for this plugin.
     */
    public function getCssURL($request)
    {
        return $request->getBaseUrl() . '/' . $this->getPluginPath() . '/css';
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

    protected function registerResponseBindings(): void
    {
        $container = PKPContainer::getInstance();

        $container->bind(\Illuminate\Routing\RouteCollectionInterface::class, \Illuminate\Routing\RouteCollection::class);
        $container->bind(
            \Illuminate\View\ViewFinderInterface::class, 
            fn ($app) => new \Illuminate\View\FileViewFinder(app(\Illuminate\Filesystem\Filesystem::class), [])
        );
        $container->bind(\Illuminate\Contracts\View\Factory::class, \Illuminate\View\Factory::class);
        $container->bind(\Illuminate\Contracts\Routing\ResponseFactory::class, \Illuminate\Routing\ResponseFactory::class);
    }

}
