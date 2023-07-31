<?php

namespace APP\plugins\generic\preprintToJournal\controllers;

use stdClass;
use APP\core\Request;
use APP\facades\Repo;
use Firebase\JWT\Key;
use PKP\config\Config;
use PKP\security\Role;
use PKP\facades\Locale;
use APP\handler\Handler;
use PKP\core\PKPJwt as JWT;
use Illuminate\Http\Response;
use APP\plugins\generic\preprintToJournal\classes\models\ApiKey;
use APP\plugins\generic\preprintToJournal\PreprintToJournalPlugin;

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
            ], Response::HTTP_NOT_FOUND)->send();
        }

        if ((bool)$context->getData('disableSubmissions')) {
            return response()->json([
                'message' => __(
                    'plugins.generic.preprintToJournal.publishingJournal.response.contextSubmissionDisable', 
                    [
                        'journalName' => $context->getName('name')
                    ]
                ),
            ], Response::HTTP_NOT_ACCEPTABLE)->send();
        }

        $headers = getallheaders();
        
        if (!isset($headers['X-Api-Key'])) {
            return response()->json([
                'message' => __('plugins.generic.preprintToJournal.publishingJournal.response.missingApiKey'),
            ], Response::HTTP_UNAUTHORIZED)->send();
        }
        
        $secret = Config::getVar('security', 'api_key_secret', '');
        $headers = new stdClass;
        $apiKey = ApiKey::getByKey(
            ((Array)JWT::decode(
                $headers['X-Api-Key'], 
                new Key($secret, 'HS256'), 
                $headers)
            )[0]
        );

        if (!$apiKey) {
            return response()->json([
                'message' => __('plugins.generic.preprintToJournal.publishingJournal.response.invalidApiKey'),
            ], Response::HTTP_UNAUTHORIZED)->send();
        }

        $user = Repo::user()->get($apiKey->getUserId()); /** @var \PKP\user\User $user */

        if (!$user) {
            return response()->json([
                'message' => __('plugins.generic.preprintToJournal.publishingJournal.response.userNotFound'),
            ], Response::HTTP_NOT_FOUND)->send();
        }

        if($user->getDisabled()) {
            return response()->json([
                'message' => __('plugins.generic.preprintToJournal.publishingJournal.response.userDisable'),
            ], Response::HTTP_NOT_ACCEPTABLE)->send();
        }

        if(!$user->hasRole([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_AUTHOR], $context->getId())) {
            return response()->json([
                'message' => __(
                    'plugins.generic.preprintToJournal.publishingJournal.response.userSubmissionPermissionDenied', 
                    [
                        'journalName' => $context->getName('name')
                    ]
                ),
            ], Response::HTTP_NOT_ACCEPTABLE)->send();
        }

        return response()->json([
            'message' => __('plugins.generic.preprintToJournal.publishingJournal.response.success'),
        ], Response::HTTP_OK)->send();

    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\plugins\generic\preprintToJournal\controllers\JournalSubmissionHandler', '\JournalSubmissionHandler');
}
