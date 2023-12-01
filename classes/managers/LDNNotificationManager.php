<?php

namespace APP\plugins\generic\preprintToJournal\classes\managers;

use Exception;
use APP\facades\Repo;
use APP\core\Application;
use Illuminate\Http\Response;
use APP\plugins\generic\preprintToJournal\classes\models\LDNNotification;
use APP\publication\Publication;

class LDNNotificationManager
{
    public const DIRECTION_INBOUND = 'INBOUND';
    public const DIRECTION_OUTBOUND = 'OUTBOUND';

    protected array $notification = [];

    public function addNotificationProperty(string $propertyName, string|array $propertyValue, bool $allowOverride = false): self
    {
        if (!$allowOverride && isset($this->notification[$propertyName])) {
            throw new Exception('property already exist');
        }

        $this->notification[$propertyName] = $propertyValue;

        return $this;
    }

    public function getNotification(): string
    {
        return json_encode($this->notification);
    }

    public function sendNotification(string $inboxUrl, string $notification = null, array $params = []): bool
    {
        if (!$notification && empty($this->notification)) {
            throw new Exception('Can not sent notification with empty notification body');
        }

        $notification ??= json_encode($this->notification);

        $httpClient = Application::get()->getHttpClient();
        $header = [
            'Accept'    => 'application/json',
        ];

        $response = $httpClient->request('POST', $inboxUrl,[
            'http_errors'   => false,
            'headers'       => $header,
            // 'json'          => $notification,
            'form_params'   => array_merge($params, [
                'notification' => $notification
            ]),
        ]);

        return $response->getStatusCode() === Response::HTTP_OK;
        
    }

    public function storeNotification(string $direction, string $notification = null, int $submissionId = null): LDNNotification
    {
        if (!$notification && empty($this->notification)) {
            throw new Exception('Can not sent notification with empty notification body');
        }

        $notification = $notification ? json_decode($notification, true) : $this->notification;

        return LDNNotification::create([
            'submission_id'             => $submissionId,
            'notification_identifier'   => $notification['id'],
            'from_identifier'           => $notification['actor']['id'],
            'told_to'                   => $notification['target']['id'],
            'in_reply_told'             => $notification['inReplyTo'] ?? null,
            'payload'                   => json_encode($notification),
            'direction'                 => $direction
        ]);
    }

    public function executeActionBasedOnNotification(LDNNotification $notification): void
    {
        $notification = json_decode($notification->payload, true);
        $submission = Repo::submission()->get($notification->submission_id);

        $type = $notification['type'];

        if (in_array('coar-notify:EndorsementAction', $type)) {
            Repo::publication()->relate(
                $submission->getCurrentPublication(),
                Publication::PUBLICATION_RELATION_PUBLISHED,
                $notification['object']['ietf:cite-as']
            );
        }
    }
}