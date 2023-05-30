<?php

declare(strict_types=1);

namespace APP\plugins\generic\preprintToJournal;

use PKP\plugins\Hook;
use APP\core\Application;
use APP\core\Request;
use APP\plugins\generic\preprintToJournal\classes\components\JournalPublicationForm;
use PKP\plugins\GenericPlugin;
use APP\plugins\generic\preprintToJournal\PreprintToJournalApiHandler;
use APP\plugins\generic\preprintToJournal\PreprintToJournalSchemaMigration;
use APP\plugins\generic\preprintToJournal\controllers\tab\user\CustomApiProfileTabHandler;

class PreprintToJournalPlugin extends GenericPlugin
{
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
            Hook::add('LoadHandler', [$this, 'setApiRequestHandler']);
            $this->setApiRequestHandler();
            $this->callbackShowApiKeyTab();
            $this->setupCustomApiProfileComponentHandler();
            // $this->setUpUser();

            return $success;
        }

        $this->setupJournalPublicationTab();

        return $success;
    }

    public function setupJournalPublicationTab(Request $request = null): void
    {
        $request ??= Application::get()->getRequest();

        Hook::add('Template::Workflow::Publication', function (string $hookName, array $args) use ($request): bool {
            $templateMgr = & $args[1]; /** @var \APP\template\TemplateManager $templateMgr */
            $output = & $args[2];
            
            $submission = $templateMgr->getTemplateVars('submission');
            $context = $request->getContext();
            $locales = $context->getSupportedSubmissionLocaleNames();
            $locales = array_map(
                fn (string $locale, string $name) => ['key' => $locale, 'label' => $name], 
                array_keys($locales), 
                $locales
            );

            $journalPublicationForm = new JournalPublicationForm(
                action: '#', 
                publication: $submission->getLatestPublication(), 
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
            
            $output .= $templateMgr->fetch($this->getTemplateResource('journalPublicationTab.tpl'));
            
            // Permit other plugins to continue interacting with this hook
            return false;
        });

    }

    public function setUpUser(Request $request = null): void
    {
        $request ??= Application::get()->getRequest();

        Hook::add('Request::getUser', function(string $hookName, array $args) use ($request): bool {
            return false;
        });
    }

    public function callbackShowApiKeyTab(): void
    {
        Hook::add('Template::User::profile', function (string $hookName, array $args): bool {
            [, $templateMgr, &$output] = $args;
            $output .= $templateMgr->fetch($this->getTemplateResource('apiKeyTab.tpl'));
            
            // Permit other plugins to continue interacting with this hook
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

    public function setApiRequestHandler(): void
    {
        Hook::add('LoadHandler', function (string $hookName, array $args): bool {
            $page =& $args[0];
            $handler =& $args[3];

            if ($this->getEnabled() && strtolower($page) === strtolower(PreprintToJournalApiHandler::URL_PAGE_HANDLER)) {
                $handler = new PreprintToJournalApiHandler($this);
                return true;
            }
    
            return false;
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
}
