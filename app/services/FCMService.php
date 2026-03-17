<?php

namespace App\services;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FCMService
{
    protected $credentialsPath;

    protected $projectId;

    public function __construct()
    {
        $this->credentialsPath = storage_path(config('services.fcm.credentialsPath'));
        $this->projectId = config('services.fcm.project_id');
    }

    /**
     * جلب التوكن مع التخزين المؤقت لمدة ساعة
     */
    protected function getAccessToken()
    {
        return Cache::remember('fcm_access_token', 3500, function () {
            $credentials = new ServiceAccountCredentials(
                'https://www.googleapis.com/auth/firebase.messaging',
                $this->credentialsPath
            );
            $token = $credentials->fetchAuthToken();

            return $token['access_token'];
        });
    }

    public function sendNotification($to, $title, $body, $FCMNotificationTypeData = [])
    {
        $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";

        $typeDataString = is_array($FCMNotificationTypeData) ? json_encode($FCMNotificationTypeData) : $FCMNotificationTypeData;

        $payload = [
            'message' => [
                'token' => $to,
                'data' => [
                    'title' => (string) $title,
                    'body' => (string) $body,
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    'type_data' => $typeDataString,
                ],
                'notification' => [
                    'title' => (string) $title,
                    'body' => (string) $body,
                ],
                'android' => ['priority' => 'high'],
                'apns' => [
                    'headers' => ['apns-priority' => '10'],
                    'payload' => [
                        'aps' => ['content-available' => 1, 'badge' => 0, 'sound' => 'default'],
                    ],
                ],
            ],
        ];

        return $this->executeRequest($url, $payload);
    }

    public function sendToTopic($topicName, $title, $body, $FCMNotificationTypeData = [])
    {
        $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";
        $typeDataString = is_array($FCMNotificationTypeData) ? json_encode($FCMNotificationTypeData) : $FCMNotificationTypeData;

        $payload = [
            'message' => [
                'topic' => $topicName,
                'data' => [
                    'title' => (string) $title,
                    'body' => (string) $body,
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    'type_data' => $typeDataString,
                ],
                'notification' => [
                    'title' => (string) $title,
                    'body' => (string) $body,
                ],
                'android' => ['priority' => 'high'],
                'apns' => [
                    'headers' => ['apns-priority' => '10'],
                    'payload' => [
                        'aps' => ['content-available' => 1, 'badge' => 0, 'sound' => 'default'],
                    ],
                ],
            ],
        ];

        return $this->executeRequest($url, $payload);
    }

    protected function executeRequest($url, $payload)
    {
        try {
            $response = Http::withToken($this->getAccessToken())
                ->asJson()
                ->post($url, $payload);

            return $response->json();
        } catch (\Exception $e) {
            Log::error('FCM Error: '.$e->getMessage());

            return null;
        }
    }
}
