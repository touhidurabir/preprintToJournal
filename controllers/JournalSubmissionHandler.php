<?php

namespace APP\plugins\generic\preprintToJournal\controllers;

use APP\core\Request;
use Firebase\JWT\JWT;
use PKP\config\Config;
use APP\handler\Handler;
use Illuminate\Http\JsonResponse;
use APP\plugins\generic\preprintToJournal\classes\models\ApiKey;
use APP\plugins\generic\preprintToJournal\PreprintToJournalPlugin;
use PKP\core\JSONMessage;
use Throwable;
use Slim\Http\Response as SlimResponse;

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
        //      - user has permission for new submission

        $context = $request->getContext(); /** @var \APP\journal\Journal $context */

        if (!$context) {
            return response()->json([
                'message' => 'Journal context not available',
            ], 404)->send();
        }

        $headers = getallheaders();
        
        if (!isset($headers['X-Api-Key'])) {
            return response()->json([
                'message' => 'Missing API Key',
            ], 401)->send();
        }
        
        $apiKey = ApiKey::getByKey(
            (string) JWT::decode($headers['X-Api-Key'], Config::getVar('security', 'api_key_secret', ''), ['HS256'])
        );

        if (!$apiKey) {
            return response()->json([
                'message' => 'Invalid API Key',
            ], 401)->send();
        }

        return response()->json([
            'message' => 'Successful communication done',
        ], 200)->send();

    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\plugins\generic\preprintToJournal\controllers\JournalSubmissionHandler', '\JournalSubmissionHandler');
}
