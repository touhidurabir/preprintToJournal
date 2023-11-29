<?php

namespace APP\plugins\generic\preprintToJournal\controllers;

use Throwable;
use Carbon\Carbon;
use APP\core\Request;
use APP\facades\Repo;
use PKP\plugins\Hook;
use APP\core\Services;
use PKP\facades\Locale;
use APP\handler\Handler;
use APP\core\Application;
use Illuminate\Support\Str;
use Illuminate\Http\Response;
use APP\submission\Submission;
use APP\template\TemplateManager;
use Illuminate\Http\JsonResponse;
use PKP\security\authorization\UserRequiredPolicy;
use APP\plugins\generic\preprintToJournal\classes\models\Service;
use APP\plugins\generic\preprintToJournal\PreprintToJournalPlugin;
use APP\plugins\generic\preprintToJournal\classes\managers\ServiceManager;
use APP\plugins\generic\preprintToJournal\classes\managers\LDNNotificationManager;
use APP\plugins\generic\preprintToJournal\classes\components\JournalSubmissionForm;
use APP\plugins\generic\preprintToJournal\classes\models\Submission as TransferableSubmission;

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
        $context = $contextService->get((int)$service->context_id); /** @var \PKP\context\Context|\App\server\Server $context */

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
            
            // dump($exception);
        }

        if ($response && $response->getStatusCode() === Response::HTTP_OK) {

            $submission = Repo::submission()->get($request->getUserVar('submissionId')); /** @var \APP\submission\Submission $submission */
            $publication = $submission->getCurrentPublication(); /** @var \APP\publication\Publication $publication */

            $locales = $context->getSupportedSubmissionLocaleNames();
            $locales = array_map(
                fn (string $locale, string $name) => ['key' => $locale, 'label' => $name], 
                array_keys($locales), 
                $locales
            );

            $action = $request->getDispatcher()->url(
                $request,
                Application::ROUTE_COMPONENT,
                $context->getData('urlPath'),
                'plugins.generic.preprintToJournal.controllers.JournalPublishingHandler',
                'submitPreprintToJournal',
            );

            $jounalSubmissionForm = new JournalSubmissionForm(
                action: $action, 
                publication: $publication, 
                context: $context,
                service: $service,
                locales: $locales,
                values: json_decode($response->getBody(), true)['data'] ?? [],
            );

            return response()->json([
                'message' => 'Verified successfully',
                'data'      => [
                    'service_id' => $service->id,
                    'form_component' => $jounalSubmissionForm->getConfig(),
                ],
                'form' => '',
            ], Response::HTTP_OK)->send();
        }

        return response()->json([
            'message' => 'Verification failed.', 
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

    public function submitPreprintToJournal(array $args, Request $request): JsonResponse
    {
        $data = $request->getUserVars();

        // TODO : first run the validation to check if all details required

        $transferableSubmission = TransferableSubmission::create([
            'uuid' => Str::uuid(),
            'submission_id' => $data['submissionId'],
            'service_id'    => $data['serviceId'],
            'payload'       => json_encode([
                'locale'                    => $data['journalLocale'],
                'sectionId'                 => $data['journalSectionId'],
                'submissionRequirements'    => true,
                'privacyConsent'            => true,
                'title'                     => [
                    $data['journalLocale'] => $data['preprintTitle']
                ],
                'abstract'                  => [
                    $data['journalLocale'] => $data['preprintAbstract']
                ]
            ]),
        ]);

        $service = Service::find($data['serviceId']);

        $contextService = Services::get('context'); /** @var \APP\services\ContextService $contextService */
        $context = $contextService->get((int)$service->context_id); /** @var \PKP\context\Context|\App\server\Server $context */
        
        $journalPath = last(explode('/', $service->url));
        $articleConfirmationUrl = Str::of(
            $request->getDispatcher()->url(
                $request,
                Application::ROUTE_COMPONENT,
                $journalPath,
                'plugins.generic.preprintToJournal.controllers.JournalSubmissionHandler',
                'confirmJournalTransfer',
                null,
                ['uuid' => $transferableSubmission->uuid, 'serviceId' => $service->remote_service_id]
            )
        )
        ->replace($request->getBaseUrl() . '/index.php/' . $context->getData('urlPath'), $service->url)
        ->__toString();

        return response()->json([
            'message'   => 'Transferring of preprint to journal article has been initiated successfully.',
            'data'      => [
                'articleConfirmationUrl' => $articleConfirmationUrl,
            ],
        ], Response::HTTP_OK)->send();
    }

    public function getTransferableJournalDetails(array $args, Request $request): JsonResponse
    {
        $transferableSubmission = TransferableSubmission::where('service_id', $request->getUserVar('serviceId'))
            ->where('uuid', $request->getUserVar('submissionId'))
            ->first();
        
        if (!$transferableSubmission) {
            return response()->json([
                'message'   => 'Not found.',
            ], Response::HTTP_NOT_FOUND)->send();
        }

        $service = Service::find($transferableSubmission->service_id);

        $contextService = Services::get('context'); /** @var \APP\services\ContextService $contextService */
        $context = $contextService->get((int)$service->context_id); /** @var \PKP\context\Context|\App\server\Server $context */

        $submission = Repo::submission()->get($transferableSubmission->submission_id);

        return response()->json([
            'message'       => 'Found',
            'data'          => json_decode($transferableSubmission->payload, true),
            'resourceUrl'   => $request->getDispatcher()->url(
                $request,
                Application::ROUTE_PAGE,
                $context->getData('urlPath'),
                'preprint',
                'view',
                ['id' => $submission->getId()]
            )
        ], Response::HTTP_OK)->send();
    }

    public function confirmTransferAccept(array $args, Request $request): JsonResponse
    {
        $transferableSubmission = TransferableSubmission::where('service_id', $request->getUserVar('serviceId'))
            ->where('uuid', $request->getUserVar('submissionId'))
            ->first();
        
        if (!$transferableSubmission) {
            return response()->json([
                'message'   => 'Not found.',
            ], Response::HTTP_NOT_FOUND)->send();
        }

        $transferableSubmission->update([
            'remote_submission_id'  => $request->getUserVar('remoteSubmissionId'),
            'transfered_at'         => Carbon::now()
        ]);

        $this->sendCoarNotifyRelationAnnounceNotification($transferableSubmission->refresh());

        return response()->json([
            'message'   => 'success',
        ], Response::HTTP_OK)->send();
    }

    protected function sendCoarNotifyRelationAnnounceNotification(TransferableSubmission $transferableSubmission): void
    {
        $request = Application::get()->getRequest();

        $service = Service::where('id', $transferableSubmission->service_id)->first();

        $contextService = Services::get('context'); /** @var \APP\services\ContextService $contextService */
        $context = $contextService->get((int)$service->context_id); /** @var \PKP\context\Context|\App\server\Server $context */

        $submission = Repo::submission()->get($transferableSubmission->submission_id);

        $submissionUrl = $request->getDispatcher()->url(
            $request,
            Application::ROUTE_PAGE,
            $context->getData('urlPath'),
            'preprint',
            'view',
            ['id' => $submission->getId()]
        );

        $ldnNotificationManager = new LDNNotificationManager;

        $ldnNotificationManager
            ->addNotificationProperty('id', "urn:uuid:" . Str::uuid())
            ->addNotificationProperty('@context', [
                'https://www.w3.org/ns/activitystreams',
                'https://purl.org/coar/notify',
            ])
            ->addNotificationProperty('type', [
                'Announce',
                'coar-notify:RelationshipAction',
            ])
            ->addNotificationProperty('actor', [
                'id'    => PreprintToJournalPlugin::getContextBaseUrl(),
                'name'  => $context->getData('name', Locale::getPrimaryLocale()),
                'type'  => 'Service',
            ])
            ->addNotificationProperty('object', [
                'id' => $submissionUrl,
                'type' => 'sorg:Preprint',
            ])
            ->addNotificationProperty('context', [
                'id' => $submissionUrl,
                'type' => 'sorg:Preprint',
            ])
            ->addNotificationProperty('origin', [
                'id'    => PreprintToJournalPlugin::getContextBaseUrl(),
                'inbox' => PreprintToJournalPlugin::getLDNInboxUrl(),
                'type'  => 'Service',
            ])
            ->addNotificationProperty('target', [
                'id'    => $service->url,
                'inbox' =>  PreprintToJournalPlugin::getLDNInboxUrl($service->url),
                'type'  => 'Service',
            ]);
        
        $notificationSendStatus = $ldnNotificationManager->sendNotification(
            PreprintToJournalPlugin::getLDNInboxUrl($service->url),
            $ldnNotificationManager->getNotification(),
            ['submissionId' => $submission->getId()]
        );    

        if ($ldnNotificationManager->sendNotification(PreprintToJournalPlugin::getLDNInboxUrl($service->url))) {
            $ldnNotificationManager->storeNotification(
                LDNNotificationManager::DIRECTION_OUTBOUND, 
                $ldnNotificationManager->getNotification(),
                (int)$submission->getId()
            );
        }
    }
    
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\plugins\generic\preprintToJournal\controllers\JournalPublishingHandler', '\JournalPublishingHandler');
}
