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
        //      - context exists
        //      - journal allow submission

        $context = $request->getContext(); /** @var \APP\journal\Journal $context */
        Locale::setLocale($request->getUserVar('preferredLocale') ?? Locale::getPrimaryLocale());

        if (!$context) {
            return response()->json([
                'message' => __('plugins.generic.preprintToJournal.publishingJournal.response.contextMissing'),
            ], Response::HTTP_NOT_FOUND)->send();
        }

        if ((bool)$context->getData('disableSubmissions')) {
            return response()->json([
                'message' => __('plugins.generic.preprintToJournal.publishingJournal.response.contextSubmissionDisable', [
                    'journalName' => $context->getName('name')
                ]),
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

        RemoteService::create([
            'context_id'        => $context->getId(),
            'remote_service_id' => $request->getUserVar('remote_service_id'),
            'name'              => $request->getUserVar('name'),
            'url'               => $request->getUserVar('url'),
            'ip'                => $request->getUserVar('ip'),
            'status'            => RemoteService::STATUS_PENDING,
        ]);

        return response()->json([
            'message' => 'Remote service registered successfully',
        ], Response::HTTP_OK)->send();
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\plugins\generic\preprintToJournal\controllers\JournalSubmissionHandler', '\JournalSubmissionHandler');
}
