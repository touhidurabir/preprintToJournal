<?php

namespace APP\plugins\generic\preprintToJournal\controllers;

use Throwable;
use APP\core\Request;
use APP\facades\Repo;
use APP\core\Services;
use PKP\config\Config;
use PKP\security\Role;
use PKP\facades\Locale;
use APP\handler\Handler;
use PKP\context\Context;
use APP\core\Application;
use Illuminate\Support\Str;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use PKP\security\authorization\UserRequiredPolicy;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use APP\plugins\generic\preprintToJournal\PreprintToJournalPlugin;
use APP\plugins\generic\preprintToJournal\classes\models\RemoteService;

class JournalSubmissionHandler extends Handler
{
    public static PreprintToJournalPlugin $plugin;

    public static function setPlugin(PreprintToJournalPlugin $plugin): void
    {
        static::$plugin = $plugin;
    }
    
    public function authorize($request, &$args, $roleAssignments)
    {
        // $this->addPolicy(new UserRequiredPolicy($request), true);
        // $this->addPolicy(new RoleBasedHandlerOperationPolicy($request, [Role::ROLE_ID_AUTHOR], ['confirmJournalTransfer']));

        return parent::authorize($request, $args, $roleAssignments);
    }

    public function verify(array $args, Request $request)
    {
        // checks
        //      - context exists
        //      - journal allow submission

        $context = $request->getContext(); /** @var \APP\journal\Journal|\PKP\context\Context $context */
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
            'data' => [
                'locales'               => $this->getLocaleOptions($context),
                'sections'              => $this->getSectionOptions($context),
                'submissionChecklists'  => $this->getSubmissionChecklistOptions($context),
                'privacyConcents'       => $this->getPrivacyConcentOptions($context),
                'primaryLocale'         => $context->getPrimaryLocale(),
            ],
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
            'context_id'        => $context->getId(),
            'remote_service_id' => $request->getUserVar('remote_service_id'),
            'name'              => $request->getUserVar('name'),
            'url'               => $request->getUserVar('url'),
            'ip'                => $request->getUserVar('ip'),
            'status'            => RemoteService::STATUS_PENDING,
        ]);

        return response()->json([
            'message' => 'Remote service registered successfully',
            'data' => [
                'service_id' => $remoteService->id
            ],
        ], Response::HTTP_OK)->send();
    }

    public function confirmJournalTransfer(array $args, Request $request)
    {
        // 1. check if user is logged in
        // 2. if not, redirect to login
        // 3. if logged in/after login, start moving the submission
        $remoteService = RemoteService::find($request->getUserVar('serviceId'));
        
        if (!$remoteService) {
            return response()->json([
                'message' => 'Service not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $contextService = Services::get('context'); /** @var \APP\services\ContextService $contextService */
        $context = $contextService->get((int)$remoteService->context_id); /** @var \App\server\Server $context */

        $serverPath = last(explode('/', $remoteService->url));
        $journalConfigDetailsUrl = Str::of(
            $request->getDispatcher()->url(
                $request,
                Application::ROUTE_COMPONENT,
                $serverPath,
                'plugins.generic.preprintToJournal.controllers.JournalPublishingHandler',
                'getTransferableJournalDetails',
                null,
                ['submissionId' => $request->getUserVar('uuid'), 'serviceId' => $remoteService->remote_service_id]
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
                $journalConfigDetailsUrl,
                [
                    'http_errors'   => false,
                    'headers'       => $header,
                ]
            );

            if ($response->getStatusCode() === Response::HTTP_OK) {

                $data = json_decode($response->getBody(), true)['data'];
            }

            return false;

        } catch(Throwable $exception) {

            ray($exception);
        }
        // 4. once the moving complete/failed, send a coar notification (LDN notification)
        // 5. May be notify OPS end via other means about the result if the LDN notification is not sufficient
        // 6. once submission done moving, redirect to submission wizard of transfered submission
    }

    protected function getLocaleOptions(Context $context): array
    {
        $options = [];

        foreach ($context->getSupportedSubmissionLocaleNames() as $locale => $name) {
            $options[] = [
                'value' => $locale,
                'label' => $name,
            ];
        }

        return $options;
    }

    protected function getSectionOptions(Context $context): array
    {
        $allSections = Repo::section()
            ->getCollector()
            ->filterByContextIds([$context->getId()])
            ->excludeInactive()
            ->getMany();

        $options = [];

        foreach ($allSections as $section) { /** @var Section $section */
            if ($section->getEditorRestricted()) {
                continue;
            }

            $options[] = [
                'value' => $section->getId(),
                'label' => $section->getLocalizedTitle(),
            ];
        }

        return $options;
    }

    protected function getSubmissionChecklistOptions(Context $context): array
    {
        return [
            'label' => __('submission.submit.submissionChecklist'),
            'description' => $context->getLocalizedData('submissionChecklist'),
            'options' => [
                [
                    'value' => true,
                    'label' => __('submission.submit.submissionChecklistConfirm'),
                ],
            ],
        ];
    }

    protected function getPrivacyConcentOptions(Context $context): array
    {
        $privacyStatement = Config::getVar('general', 'sitewide_privacy_statement')
            ? Application::get()
                ->getRequest()
                ->getSite()
                ->getData('privacyStatement')
            : $context->getData('privacyStatement');

        if (!$privacyStatement) {
            return [];
        }

        $privacyUrl = Application::get()
            ->getRequest()
            ->getDispatcher()
            ->url(
                Application::get()->getRequest(),
                Application::ROUTE_PAGE,
                null,
                'about',
                'privacy'
            );
        
        return [
            'label' => __('submission.wizard.privacyConsent'),
            'options' => [
                [
                    'value' => true,
                    'label' => __('user.register.form.privacyConsent', [
                        'privacyUrl' => $privacyUrl,
                    ]),
                ],
            ],
        ];
    }

}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\plugins\generic\preprintToJournal\controllers\JournalSubmissionHandler', '\JournalSubmissionHandler');
}
