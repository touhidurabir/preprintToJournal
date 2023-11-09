<?php

namespace APP\plugins\generic\preprintToJournal\controllers\tab\service;

use Throwable;
use APP\core\Services;
use APP\core\Application;
use Illuminate\Support\Str;
use Illuminate\Http\Response;
use APP\plugins\generic\preprintToJournal\classes\models\Service;
use APP\plugins\generic\preprintToJournal\PreprintToJournalPlugin;

class JournalRegistration
{
    public static PreprintToJournalPlugin $plugin;

    public static function setPlugin(PreprintToJournalPlugin $plugin): void
    {
        static::$plugin = $plugin;
    }

    public function register(Service $service): bool
    {
        if ($service->isRegisterToJournal()) {
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
                        'remote_service_id' => $service->id,
                        'url'               => $request->getBaseUrl() . "/index.php/" . $context->getData('path'),
                        'ip'                => gethostbyname(parse_url($request->getBaseUrl(), PHP_URL_HOST)),
                    ],
                ]
            );

            return $response->getStatusCode() === Response::HTTP_OK;

        } catch(Throwable $exception) {
            
            ray($exception);

            return false;
        }
    }
}