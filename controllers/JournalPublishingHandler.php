<?php

namespace APP\plugins\generic\preprintToJournal\controllers;

use Throwable;
use APP\core\Request;
use PKP\facades\Locale;
use APP\handler\Handler;
use APP\core\Application;
use Illuminate\Support\Str;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use PKP\security\authorization\UserRequiredPolicy;
use APP\plugins\generic\preprintToJournal\classes\models\Service;
use APP\plugins\generic\preprintToJournal\controllers\tab\service\ServiceManager;
use APP\plugins\generic\preprintToJournal\PreprintToJournalPlugin;

class JournalPublishingHandler extends Handler
{
    public static PreprintToJournalPlugin $plugin;

    public static function setPlugin(PreprintToJournalPlugin $plugin): void
    {
        static::$plugin = $plugin;
    }
    
    public function authorize($request, &$args, $roleAssignments)
    {
        // User must be logged in
        // $this->addPolicy(new UserRequiredPolicy($request));

        return parent::authorize($request, $args, $roleAssignments);
    }

    public function verify(array $args, Request $request)
    {
        // we need to apply some validation here
        $data = $request->getUserVars();
        $journalPath = Str::of($data['publishingJournalUrl'])->explode('/')->filter()->last();
        $journalBaseUrl = Str::of($data['publishingJournalUrl'])->replace("/{$journalPath}", '')->__toString();

        $journalVerifyUrl = Str::of(
            $request->getDispatcher()->url(
                $request,
                Application::ROUTE_COMPONENT,
                $journalPath,
                'plugins.generic.preprintToJournal.controllers.JournalSubmissionHandler',
                'verify',
            )
        )->replace($request->getBaseUrl(), $journalBaseUrl)->__toString();

        $httpClient = Application::get()->getHttpClient();
        $header = [
            'Accept'    => 'application/json',
            'X-Api-Key' => $data['apiKey'],
        ];

        try {
            $response = $httpClient->request(
                'POST',
                $journalVerifyUrl,
                [
                    'http_errors'   => false,
                    'headers'       => $header,
                    'form_params'   => [
                        'preferredLocale' => Locale::getLocale()
                    ],
                ]
            );

            return response()->json([
                'data'      => json_decode($response->getBody(), true),
                'status'    => $response->getStatusCode(),
            ], 200)->send();

        } catch(Throwable $exception) {
            
            // dump($exception);
        }
    }

    public function registerRemoteJournalServiceResponse(array $args, Request $request): JsonResponse
    {
        $service = Service::find($request->getUserVar('service_id'));

        if (!$service) {
            return response()->json([
                'message' => 'Service resource not found',
            ], Response::HTTP_NOT_FOUND);
        }

        (new ServiceManager)
            ->registerRemoteResponse($service, $request->getUserVar('statusResponse'));
        
        return response()->json([
            'message'      => 'Remote journal service response store successfully',
        ], Response::HTTP_OK)->send();
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\plugins\generic\preprintToJournal\controllers\JournalPublishingHandler', '\JournalPublishingHandler');
}
