<?php

namespace APP\plugins\generic\preprintToJournal\controllers;

use APP\core\Request;
use APP\handler\Handler;
use Illuminate\Http\JsonResponse;
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

    public function verify(array $args, Request $request): JsonResponse
    {
        $headers = getallheaders();

        if (!isset($headers['x-api-key'])) {
            return response()->json([
                'message' => 'Missing API Key'
            ], 401);
        }

        $apiKey = ApiKey::getByKey($headers['x-api-key']);

        if (!$apiKey) {
            return response()->json([
                'message' => 'Invalid API Key',
            ], 401);
        }

        return response()->json([
            'message' => 'Successful communication done',
        ], 200);

        // checks
        //      - journal allow submission
        //      - user exists
        //      - user bloacked
        //      - user has permission for new submission
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\plugins\generic\preprintToJournal\controllers\JournalSubmissionHandler', '\JournalSubmissionHandler');
}
