<?php

declare(strict_types=1);

namespace APP\plugins\generic\preprintToJournal;

use PKP\plugins\Hook;
use APP\core\Application;
use PKP\plugins\GenericPlugin;
use APP\plugins\generic\preprintToJournal\PreprintToJournalApiKeyHandler;
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
        
        if ($success && $this->getEnabled()) {
            if (self::isOJS()) {
                $this->callbackShowApiKeyTab();
                $this->setupCustomApiProfileComponentHandler();
                // Hook::add('LoadHandler', [$this, 'setApiKeyPageHandler']);
            }
        }

        return $success;
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

    public function setApiKeyPageHandler(string $hookName, array $args): bool
    {
        $page =& $args[0];
        $handler =& $args[3];

        if ($this->getEnabled() && strtolower($page) === strtolower(PreprintToJournalApiKeyHandler::URL_PAGE_HANDLER)) {
            $handler = new PreprintToJournalApiKeyHandler($this);
            return true;
        }

        return false;
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
