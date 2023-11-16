<?php

namespace APP\plugins\generic\preprintToJournal\controllers;

use Throwable;
use APP\core\Request;
use APP\core\Services;
use PKP\facades\Locale;
use APP\handler\Handler;
use APP\core\Application;
use Illuminate\Support\Str;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use PKP\security\authorization\UserRequiredPolicy;
use APP\plugins\generic\preprintToJournal\classes\models\Service;
use APP\plugins\generic\preprintToJournal\PreprintToJournalPlugin;
use APP\plugins\generic\preprintToJournal\controllers\tab\service\ServiceManager;

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
        $service = Service::find($request->getUserVar('publishingJournalServiceId'));
        
        if (!$service) {
            return response()->json([
                'message' => 'Remote journal service not found',
            ], Response::HTTP_NOT_FOUND);
        }

        if (!$service->hasAuthorized()) {
            return response()->json([
                'message' => 'Remote journal has not authorized yet',
            ], Response::HTTP_NOT_ACCEPTABLE);
        }

        $contextService = Services::get('context'); /** @var \APP\services\ContextService $contextService */
        $context = $contextService->get((int)$service->context_id); /** @var \App\server\Server $context */

        $journalPath = last(explode('/', $service->url));
        $journalVerifyUrl = Str::of(
            $request->getDispatcher()->url(
                $request,
                Application::ROUTE_COMPONENT,
                $journalPath,
                'plugins.generic.preprintToJournal.controllers.JournalSubmissionHandler',
                'verify',
            )
        )->replace($request->getBaseUrl() . '/index.php/' . $context->getData('urlPath') , $service->url)->__toString();

        $httpClient = Application::get()->getHttpClient();

        try {
            $response = $httpClient->request(
                'POST',
                $journalVerifyUrl,
                [
                    'http_errors'   => false,
                    'headers'       => [
                        'Accept'    => 'application/json'
                    ],
                    'form_params'   => [
                        'preferredLocale' => Locale::getLocale()
                    ],
                ]
            );

        } catch(Throwable $exception) {
            
            ray($exception);
        }

        if ($response && $response->getStatusCode() === Response::HTTP_OK) {
            
            return response()->json([
                'message' => 'Verified successfully',
                'data'      => [
                    'service_id' => $service->id,
                ],
            ], Response::HTTP_OK)->send();
        }

        return response()->json([
            'message' => 'Verified successfully', 
        ], Response::HTTP_NOT_ACCEPTABLE)->send();
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
