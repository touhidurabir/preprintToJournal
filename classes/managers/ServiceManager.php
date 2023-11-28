<?php

namespace APP\plugins\generic\preprintToJournal\classes\managers;

use Throwable;
use Carbon\Carbon;
use APP\core\Services;
use PKP\facades\Locale;
use APP\core\Application;
use Illuminate\Support\Str;
use Illuminate\Http\Response;
use APP\plugins\generic\preprintToJournal\classes\models\Service;
use APP\plugins\generic\preprintToJournal\classes\models\RemoteService;

class ServiceManager
{
    public function register(Service $service): bool
    {
        if ($service->hasRegistered()) {
            return response()->json([
                'message' => 'Service Already Registered',
            ], Response::HTTP_OK);
        }

        $contextService = Services::get('context'); /** @var \APP\services\ContextService $contextService */
        $context = $contextService->get((int)$service->context_id); /** @var \App\server\Server $context */

        $request = Application::get()->getRequest();
        $journalPath = last(explode('/', $service->url));

        $journalRegistrationUrl = Str::of(
            $request->getDispatcher()->url(
                $request,
                Application::ROUTE_COMPONENT,
                $journalPath,
                'plugins.generic.preprintToJournal.controllers.JournalSubmissionHandler',
                'registerJournalService',
            )
        )->replace($request->getBaseUrl(), $service->url)->__toString();

        $httpClient = Application::get()->getHttpClient();
        $header = [
            'Accept'    => 'application/json',
        ];

        try {
            $response = $httpClient->request(
                'POST',
                $journalRegistrationUrl,
                [
                    'http_errors'   => false,
                    'headers'       => $header,
                    'form_params'   => [
                        'name'              => $context->getData('name', Locale::getPrimaryLocale()),
                        'remote_service_id' => $service->id,
                        'url'               => $request->getBaseUrl() . "/index.php/" . $context->getData('urlPath'),
                        'ip'                => gethostbyname(parse_url($request->getBaseUrl(), PHP_URL_HOST)),
                    ],
                ]
            );

            if ($response->getStatusCode() === Response::HTTP_OK) {

                $data = json_decode($response->getBody(), true)['data'];

                $service->update([
                    'registered_at'     => Carbon::now(),
                    'remote_service_id' => $data['service_id'],
                ]);
                
                return true;
            }

            return false;

        } catch(Throwable $exception) {
            
            // ray($exception);
        }

        return false;
    }

    public function respond(RemoteService $remoteService, int $statusResponse): bool
    {
        $contextService = Services::get('context'); /** @var \APP\services\ContextService $contextService */
        $context = $contextService->get((int)$remoteService->context_id); /** @var \App\server\Server $context */

        $request = Application::get()->getRequest();
        $serverPath = last(explode('/', $remoteService->url));

        $serverResponseUrl = Str::of(
            $request->getDispatcher()->url(
                $request,
                Application::ROUTE_COMPONENT,
                $serverPath,
                'plugins.generic.preprintToJournal.controllers.JournalPublishingHandler',
                'registerRemoteJournalServiceResponse',
            )
        )
        ->replace($request->getBaseUrl() . '/index.php/' . $context->getData('urlPath'), $remoteService->url)
        ->__toString();

        $httpClient = Application::get()->getHttpClient();
        $header = [
            'Accept'    => 'application/json',
        ];

        try {
            $response = $httpClient->request(
                'POST',
                $serverResponseUrl,
                [
                    'http_errors'   => false,
                    'headers'       => $header,
                    'form_params'   => [
                        'service_id'        => $remoteService->remote_service_id,
                        'statusResponse'    => $statusResponse
                    ],
                ]
            );

            if ($response->getStatusCode() === Response::HTTP_OK) {

                $remoteService->update([
                    'response_at'   => Carbon::now(),
                    'responder_id'  => Application::get()->getRequest()->getUser()->getId(),
                    'status'        => $statusResponse,
                ]);
                
                return true;
            }

            return false;

        } catch(Throwable $exception) {

            // ray($exception);
        }

        return false;
    }

    public function registerRemoteResponse(Service $service, int $statusResponse): void
    {
        $service->update([
            'response_at'   => Carbon::now(),
            'status'        => $statusResponse,
        ]);
    }
}