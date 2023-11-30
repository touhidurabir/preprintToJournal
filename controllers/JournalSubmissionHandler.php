<?php

namespace APP\plugins\generic\preprintToJournal\controllers;

use Throwable;
use APP\core\Request;
use APP\facades\Repo;
use APP\core\Services;
use PKP\config\Config;
use PKP\security\Role;
use PKP\db\DAORegistry;
use PKP\facades\Locale;
use APP\handler\Handler;
use PKP\context\Context;
use APP\core\Application;
use APP\plugins\generic\preprintToJournal\classes\managers\LDNNotificationManager;
use Illuminate\Support\Str;
use PKP\userGroup\UserGroup;
use Illuminate\Http\Response;
use APP\submission\Submission;
use Illuminate\Http\JsonResponse;
use PKP\stageAssignment\StageAssignmentDAO;
use PKP\security\authorization\UserRequiredPolicy;
use PKP\security\authorization\PKPSiteAccessPolicy;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use APP\plugins\generic\preprintToJournal\PreprintToJournalPlugin;
use APP\plugins\generic\preprintToJournal\classes\models\RemoteService;
use APP\plugins\generic\preprintToJournal\classes\models\Submission as TransferableSubmission;
use PKP\security\Validation;

class JournalSubmissionHandler extends Handler
{
    public static PreprintToJournalPlugin $plugin;

    public static function setPlugin(PreprintToJournalPlugin $plugin): void
    {
        static::$plugin = $plugin;
    }

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();

        // $this->addRoleAssignment(
        //     [Role::ROLE_ID_MANAGER, Role::ROLE_ID_AUTHOR,],
        //     ['confirmJournalTransfer']
        // );
    }
    
    public function authorize($request, &$args, $roleAssignments)
    {
        // $this->addPolicy(new PKPSiteAccessPolicy(
        //     $request, 
        //     ['confirmJournalTransfer'], 
        //     $roleAssignments
        // ));
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
        $context = $request->getContext(); /** @var \PKP\context\Context|\APP\journal\Journal $context */

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

    public function confirmJournalTransfer(array $args, Request $request): mixed
    {
        $remoteService = RemoteService::find($request->getUserVar('serviceId'));

        if (!$remoteService) {
            return response()->json([
                'message' => 'Service not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $contextService = Services::get('context'); /** @var \APP\services\ContextService $contextService */
        $context = $contextService->get((int)$remoteService->context_id); /** @var \PKP\context\Context|\APP\journal\Journal $context */

        // 1. check if user is logged in
        if (!Validation::isLoggedIn()) {
            
            // 2. if not, redirect to login
            return $request->redirectUrl(
                $request->getDispatcher()->url(
                    $request,
                    Application::ROUTE_PAGE,
                    $context->getData('urlPath'),
                    'login',
                    null,
                    null,
                    ['source' => $_SERVER['REQUEST_URI']],
                )
            );
        }

        // 3. if logged in/after login, start moving the submission
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

                $responseBody = json_decode($response->getBody(), true);
                $data = $responseBody['data'];
                $resourceUrl = $responseBody['resourceUrl'];
                $remoteSubmissionId = $responseBody['submissionId'];

                $submission = $this->storeSubmission($data, $remoteService, $remoteSubmissionId);
                
                // 4. once the moving complete/failed, send a coar notification (LDN notification)
                $this->sendCoarNotifyRequestIngestNotification($context, $submission, $remoteService, $resourceUrl);

                // 5. Notify OPS end via other means about the result if the LDN notification is not sufficient
                $this->confirmSubmissionAcceptance($context, $submission, $remoteService);

                // 6. once submission done moving, redirect to submission wizard of transfered submission
                return $request->redirect($context->getPath(), 'submission', null, null, ['id' => $submission->getId()], 'details');
            }

            return false;

        } catch(Throwable $exception) {

            // ray($exception);
        }
    }

    protected function confirmSubmissionAcceptance(Context $context, Submission $submission, RemoteService $remoteService): void
    {
        $request = Application::get()->getRequest();

        $serverPath = last(explode('/', $remoteService->url));
        $confirmUrl = Str::of(
            $request->getDispatcher()->url(
                $request,
                Application::ROUTE_COMPONENT,
                $serverPath,
                'plugins.generic.preprintToJournal.controllers.JournalPublishingHandler',
                'confirmTransferAccept',
                null,
                [
                    'submissionId'          => $request->getUserVar('uuid'), 
                    'serviceId'             => $remoteService->remote_service_id,
                    'remoteSubmissionId'    => $submission->getId(),
                ]
            )
        )
        ->replace($request->getBaseUrl() . '/index.php/' . $context->getData('urlPath'), $remoteService->url)
        ->__toString();

        $httpClient = Application::get()->getHttpClient();
        $header = [
            'Accept'    => 'application/json',
        ];

        $response = $httpClient->request('POST', $confirmUrl, [
            'http_errors'   => false,
            'headers'       => $header,
            'form_params'   => [
                'submissionId'          => $request->getUserVar('uuid'), 
                'serviceId'             => $remoteService->remote_service_id,
                'remoteSubmissionId'    => $submission->getId(),
            ]
        ]);
    }

    protected function sendCoarNotifyRequestIngestNotification(Context $context, Submission $submission, RemoteService $remoteService, string $resourceUrl): void
    {
        $request = Application::get()->getRequest();

        $transferableSubmission = TransferableSubmission::where('submission_id', $submission->getId())->first();

        $ldnNotificationManager = new LDNNotificationManager;
        
        $ldnNotificationManager
            ->addNotificationProperty('id', "urn:uuid:" . Str::uuid())
            ->addNotificationProperty('@context', [
                'https://www.w3.org/ns/activitystreams',
                'https://purl.org/coar/notify',
            ])
            ->addNotificationProperty('type', [
                'Offer',
                'coar-notify:IngestAction',
            ])
            ->addNotificationProperty('actor', [
                'id'    => PreprintToJournalPlugin::getContextBaseUrl(),
                'name'  => $context->getData('name', Locale::getPrimaryLocale()),
                'type'  => 'Service',
            ])
            ->addNotificationProperty('object', [
                'id'    => $resourceUrl,
                'type'  => 'sorg:Acticle',
            ])
            ->addNotificationProperty('origin', [
                'id'    => PreprintToJournalPlugin::getContextBaseUrl(),
                'inbox' => PreprintToJournalPlugin::getLDNInboxUrl(),
                'type'  => 'Service',
            ])
            ->addNotificationProperty('target', [
                'id'    => $remoteService->url,
                'inbox' => PreprintToJournalPlugin::getLDNInboxUrl($remoteService->url),
                'type'  => 'Service',
            ]);
        
        $notificationSendStatus = $ldnNotificationManager->sendNotification(
            PreprintToJournalPlugin::getLDNInboxUrl($remoteService->url),
            $ldnNotificationManager->getNotification(),
            ['submissionId' => $transferableSubmission->remote_submission_id]
        );

        if ($notificationSendStatus) {
            $ldnNotificationManager->storeNotification(
                LDNNotificationManager::DIRECTION_OUTBOUND, 
                $ldnNotificationManager->getNotification(),
                (int)$submission->getId()
            );
        }
    }

    protected function storeSubmission(array $data, RemoteService $remoteService, int $remoteSubmissionId): Submission
    {
        $request    = Application::get()->getRequest();
        $context    = $request->getContext();
        $user       = $request->getUser();

        $params = [
            'contextId'                 => $context->getId(),
            'locale'                    => $data['locale'],
            'sectionId'                 => $data['sectionId'],
            'submissionRequirements'    => true,
            'privacyConsent'            => true,
        ];

        $submitterUserGroups = Repo::userGroup()
            ->getCollector()
            ->filterByContextIds([$context->getId()])
            ->filterByUserIds([$user->getId()])
            ->filterByRoleIds([Role::ROLE_ID_MANAGER, Role::ROLE_ID_AUTHOR])
            ->getMany();
        
        if ($submitterUserGroups->count()) {

            $submitAsUserGroup = $submitterUserGroups
                ->sort(function (UserGroup $a, UserGroup $b) {
                    return $a->getRoleId() === Role::ROLE_ID_AUTHOR ? 1 : -1;
                })
                ->first();
        } else {
            $submitAsUserGroup = Repo::userGroup()->getFirstSubmitAsAuthorUserGroup($context->getId());
            Repo::userGroup()->assignUserToGroup($user->getId(), $submitAsUserGroup->getId());
        }

        $publicationProps = [];
        $publicationProps['sectionId'] = $params['sectionId'];
        unset($params['sectionId']);

        $params = (new \PKP\submission\Sanitizer())->sanitize($params, ['title', 'subtitle']);

        $submission = Repo::submission()->newDataObject($params);
        $publication = Repo::publication()->newDataObject($publicationProps);
        $submissionId = Repo::submission()->add($submission, $publication, $context);

        $submission = Repo::submission()->get($submissionId);

        // Assign submitter to submission
        /** @var StageAssignmentDAO $stageAssignmentDao */
        $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
        $stageAssignmentDao->build(
            $submission->getId(),
            $submitAsUserGroup->getId(),
            $request->getUser()->getId(),
            $submitAsUserGroup->getRecommendOnly(),
            // Authors can always edit metadata before submitting
            $submission->getData('submissionProgress')
                ? true
                : $submitAsUserGroup->getPermitMetadataEdit()
        );

        // Create an author record from the submitter's user account
        if ($submitAsUserGroup->getRoleId() === Role::ROLE_ID_AUTHOR) {
            $author = Repo::author()->newAuthorFromUser($user);
            $author->setData('publicationId', $publication->getId());
            $author->setUserGroupId($submitAsUserGroup->getId());
            $authorId = Repo::author()->add($author);
            Repo::publication()->edit($publication, ['primaryContactId' => $authorId]);
        }

        // $publication = Repo::publication()->get((int) $submission->getCurrentPublication()->getId());
        Repo::publication()->edit($publication, [
            'title'     => $data['title'],
            'abstract'  => $data['abstract'],
            'id'        => $publication->getId(),
        ]);

        TransferableSubmission::create([
            'service_id'            => $remoteService->id,
            'submission_id'         => $submission->getId(),
            'remote_submission_id'  => $remoteSubmissionId,
        ]);

        return $submission;
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
