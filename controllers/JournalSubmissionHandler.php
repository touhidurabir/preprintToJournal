<?php

namespace APP\plugins\generic\preprintToJournal\controllers;

use APP\core\Application;
use APP\handler\Handler;
use APP\core\Request;
use APP\facades\Repo;
use APP\plugins\generic\preprintToJournal\PreprintToJournalPlugin;
use APP\plugins\generic\preprintToJournal\classes\models\RemoteService;
use PKP\config\Config;
use PKP\facades\Locale;
use PKP\context\Context;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;

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
