<?php

namespace APP\plugins\generic\preprintToJournal\controllers;

use stdClass;
use APP\core\Request;
use APP\facades\Repo;
use APP\plugins\generic\preprintToJournal\classes\models\RemoteService;
use Firebase\JWT\Key;
use PKP\config\Config;
use PKP\security\Role;
use PKP\facades\Locale;
use APP\handler\Handler;
use PKP\core\PKPJwt as JWT;
use Illuminate\Http\Response;
use APP\plugins\generic\preprintToJournal\classes\models\ApiKey;
use APP\plugins\generic\preprintToJournal\PreprintToJournalPlugin;
use Illuminate\Http\JsonResponse;

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
        $jwtHeaders = new stdClass;
        $apiKey = ApiKey::getByKey(
            ((Array)JWT::decode(
                $headers['X-Api-Key'], 
                new Key($secret, 'HS256'), 
                $jwtHeaders)
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

    public function registerJournalService(array $args, Request $request): JsonResponse
    {
        $context = $request->getContext(); /** @var \APP\journal\Journal $context */

        if (!$context) {
            return response()->json([
                'message' => __('plugins.generic.preprintToJournal.publishingJournal.response.contextMissing'),
            ], Response::HTTP_NOT_FOUND)->send();
        }

        $remoteService = RemoteService::create([
            'remote_service_id' => $request->getUserVar('remote_service_id'),
            'url'   => $request->getUserVar('url'),
            'ip'    => $request->getUserVar('ip'),
            'status' => RemoteService::STATUS_UNAUTHORIZED,
        ]);

        return response()->json([
            'message' => 'Remote service registered successfully',
        ], Response::HTTP_OK)->send();
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\plugins\generic\preprintToJournal\controllers\JournalSubmissionHandler', '\JournalSubmissionHandler');
}
