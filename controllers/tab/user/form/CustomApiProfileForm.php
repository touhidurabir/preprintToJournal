<?php

namespace APP\plugins\generic\preprintToJournal\controllers\tab\user\form;

use PKP\user\User;
use Firebase\JWT\JWT;
use PKP\config\Config;
use APP\core\Application;
use APP\template\TemplateManager;
use PKP\user\form\BaseProfileForm;
use PKP\notification\PKPNotification;
use APP\notification\NotificationManager;
use APP\plugins\generic\preprintToJournal\classes\models\ApiKey;
use APP\plugins\generic\preprintToJournal\PreprintToJournalPlugin;

class CustomApiProfileForm extends BaseProfileForm
{
    public const API_KEY_NEW = 1;
    public const API_KEY_DELETE = 0;

    protected PreprintToJournalPlugin $plugin;

    /**
     * Constructor.
     *
     * @param User      $user
     * @param string    $template
     * 
     * @return void
     */
    public function __construct(User $user, PreprintToJournalPlugin $plugin)
    {
        $this->plugin = $plugin;
        parent::__construct($this->plugin->getTemplateResource('apiKeyForm.tpl'), $user);
    }

    /**
     * @copydoc Form::initData()
     */
    public function initData()
    {
        $request = Application::get()->getRequest();
        $user = $this->getUser();

        $this->setData('apiKeyEnabled', (bool) $user->getData('apiKeyEnabled'));
        $this->setData('journalPath', $this->plugin->getOjsJournalPath());
    }

    /**
     * Assign form data to user-submitted data.
     */
    public function readInputData()
    {
        parent::readInputData();

        $this->readUserVars([
            'apiKeyEnabled',
            'generateApiKey',
            'apiKeyAction',
        ]);
    }

    /**
     * Fetch the form to edit user's API key settings.
     *
     * @see BaseProfileForm::fetch
     *
     * @param null|mixed $template
     *
     * @return string JSON-encoded form contents.
     */
    public function fetch($request, $template = null, $display = false)
    {
        $user = $request->getUser();
        $secret = Config::getVar('security', 'api_key_secret', '');
        $templateMgr = TemplateManager::getManager($request);

        $templateMgr->assign([
            'journalPath' => $this->plugin->getOjsJournalPath(),
        ]);

        if ($secret === '') {
            $this->handleOnMissingAPISecret($templateMgr, $user);
            return parent::fetch($request, $template, $display);
        }

        $apiKey = ApiKey::where('user_id', $user->getId())->first()?->getApiKey();

        $templateMgr->assign(
            $apiKey ? [
                'apiKey' => JWT::encode($apiKey, $secret, 'HS256'),
                'apiKeyAction' => self::API_KEY_DELETE,
                'apiKeyActionTextKey' => 'user.apiKey.remove',
            ] : [
                'apiKeyAction' => self::API_KEY_NEW,
                'apiKeyActionTextKey' => 'user.apiKey.generate',
            ]
        );

        return parent::fetch($request, $template, $display);
    }

    /**
     * @copydoc Form::execute()
     */
    public function execute(...$functionArgs)
    {
        $request = Application::get()->getRequest();
        $user = $request->getUser();
        $templateMgr = TemplateManager::getManager($request);

        if (Config::getVar('security', 'api_key_secret', '') === '') {
            $this->handleOnMissingAPISecret($templateMgr, $user);
            parent::execute(...$functionArgs);
        }

        $apiKeyAction = (int)$this->getData('apiKeyAction');

        $apiKeyAction === self::API_KEY_NEW
            ? ApiKey::create([
                'user_id'   => (int)$user->getId(),
                'api_key'   => ApiKey::generate(),
            ]) 
            : ApiKey::where('user_id', (int)$user->getId())->delete();

        $this->setData('apiKeyAction', (int)!$apiKeyAction);

        parent::execute(...$functionArgs);
    }

    /**
     * Handle on missing API secret
     *
     *
     */
    protected function handleOnMissingAPISecret(TemplateManager $templateMgr, User $user): void
    {
        $notificationManager = new NotificationManager();
        $notificationManager->createTrivialNotification(
            $user->getId(),
            PKPNotification::NOTIFICATION_TYPE_WARNING,
            [
                'contents' => __('user.apiKey.secretRequired'),
            ]
        );
        $templateMgr->assign([
            'apiSecretMissing' => true,
        ]);
    }
}