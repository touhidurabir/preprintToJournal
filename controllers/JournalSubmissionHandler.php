<?php

namespace APP\plugins\generic\preprintToJournal\controllers;

use Throwable;
use APP\core\Request;
use APP\facades\Repo;
use Firebase\JWT\JWT;
use PKP\config\Config;
use PKP\security\Role;
use APP\handler\Handler;
use PKP\core\JSONMessage;
use Illuminate\Http\JsonResponse;
use Slim\Http\Response as SlimResponse;
use APP\plugins\generic\preprintToJournal\classes\models\ApiKey;
use APP\plugins\generic\preprintToJournal\PreprintToJournalPlugin;
use PKP\facades\Locale;

class JournalSubmissionHandler extends Handler
{
    public static PreprintToJournalPlugin $plugin;

    public static function setPlugin(PreprintToJournalPlugin $plugin): void
    {
        static::$plugin = $plugin;
    }
    
    public function authorize($request, &$args, $roleAssignments)
    {
        return parent::authorize($request, $args, $roleAssignments);
    }

    public function verify(array $args, Request $request)
    {
        // checks
        //      - contect exists
        //      - API KEY available and valid
        //      - journal allow submission
        //      - user exists and not bloacked
        //      - user has permission to submit in the journal

        $context = $request->getContext(); /** @var \APP\journal\Journal $context */
        Locale::setLocale($request->getUserVar('preferredLocale') ?? Locale::getPrimaryLocale());

        if (!$context) {
            return response()->json([
                'message' => __('plugins.generic.preprintToJournal.publishingJournal.response.contextMissing'),
            ], 404)->send();
        }

        if ((bool)$context->getData('disableSubmissions')) {
            return response()->json([
                'message' => __(
                    'plugins.generic.preprintToJournal.publishingJournal.response.contextSubmissionDisable', 
                    [
                        'journalName' => $context->getName('name')
                    ]
                ),
            ], 406)->send();
        }

        $headers = getallheaders();
        
        if (!isset($headers['X-Api-Key'])) {
            return response()->json([
                'message' => __('plugins.generic.preprintToJournal.publishingJournal.response.missingApiKey'),
            ], 401)->send();
        }
        
        $apiKey = ApiKey::getByKey(
            (string) JWT::decode($headers['X-Api-Key'], Config::getVar('security', 'api_key_secret', ''), ['HS256'])
        );

        if (!$apiKey) {
            return response()->json([
                'message' => __('plugins.generic.preprintToJournal.publishingJournal.response.invalidApiKey'),
            ], 401)->send();
        }

        $user = Repo::user()->get($apiKey->getUserId()); /** @var \PKP\user\User $user */

        if (!$user) {
            return response()->json([
                'message' => __('plugins.generic.preprintToJournal.publishingJournal.response.userNotFound'),
            ], 404)->send();
        }

        if($user->getDisabled()) {
            return response()->json([
                'message' => __('plugins.generic.preprintToJournal.publishingJournal.response.userDisable'),
            ], 406)->send();
        }

        if(!$user->hasRole([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_AUTHOR], $context->getId())) {
            return response()->json([
                'message' => __(
                    'plugins.generic.preprintToJournal.publishingJournal.response.userSubmissionPermissionDenied', 
                    [
                        'journalName' => $context->getName('name')
                    ]
                ),
            ], 406)->send();
        }

        return response()->json([
            'message' => __('plugins.generic.preprintToJournal.publishingJournal.response.success'),
        ], 200)->send();

    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\plugins\generic\preprintToJournal\controllers\JournalSubmissionHandler', '\JournalSubmissionHandler');
}
