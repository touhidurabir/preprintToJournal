<?php

namespace APP\plugins\generic\preprintToJournal\controllers;

use APP\handler\Handler;
use PKP\core\PKPRequest;
use APP\core\Application;
use Illuminate\Support\Str;
use PKP\security\authorization\UserRequiredPolicy;
use APP\plugins\generic\preprintToJournal\PreprintToJournalPlugin;
use PKP\facades\Locale;
use Throwable;

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
        $this->addPolicy(new UserRequiredPolicy($request));

        return parent::authorize($request, $args, $roleAssignments);
    }

    public function verify(array $args, PKPRequest $request)
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
                    'headers' => $header,
                    'form_params' => [
                        'preferredLocale' => Locale::getLocale()
                    ],
                ]
            );

            dump($response->getStatusCode());
            dump( json_decode($response->getBody(), true) );
        } catch(Throwable $exception) {
            // dump($exception);
        }
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\plugins\generic\preprintToJournal\controllers\JournalPublishingHandler', '\JournalPublishingHandler');
}
