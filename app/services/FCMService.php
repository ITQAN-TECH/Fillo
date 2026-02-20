<?php

namespace App\services;

use Google\Auth\Credentials\ServiceAccountCredentials;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Http;

class FCMService
{
    protected $client;

    protected $credentialsPath;

    protected $projectId;

    public function __construct()
    {
        $this->credentialsPath = storage_path(config('services.fcm.credentialsPath'));
        $this->projectId = config('services.fcm.project_id');
    }

    public function sendNotification($to, $title, $body, $FCMNotificationTypeData = [])
    {
        try {
            // Load Service Account credentials from JSON file
            $credentials = new ServiceAccountCredentials(
                'https://www.googleapis.com/auth/firebase.messaging',
                $this->credentialsPath
            );

            // Get an OAuth 2.0 token
            $authToken = $credentials->fetchAuthToken()['access_token'];

            $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";

            // تحويل type_data إلى string إذا كان array
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
                    'android' => [
                        'priority' => 'high',
                    ],
                    'apns' => [
                        'headers' => [
                            'apns-priority' => '10',
                        ],
                        'payload' => [
                            'aps' => [
                                'content-available' => 1,
                                'badge' => 0,
                                'priority' => 'high',
                                'sound' => 'default',
                            ],
                        ],
                    ],
                ],
            ];

            // Send POST request with raw JSON
            $response = Http::withToken($authToken)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->withBody(json_encode($payload), 'application/json')
                ->post($url);

            $responseBody = $response->getBody()->getContents();

            return $responseBody;
        } catch (RequestException $e) {
            return $e->getMessage();
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function sendToTopic($topicName, $title, $body, $FCMNotificationTypeData = [])
    {
        $typeDataString = is_array($FCMNotificationTypeData) ? json_encode($FCMNotificationTypeData) : $FCMNotificationTypeData;
        $payload = [
            'message' => [
                'topic' => $topicName,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => [
                    'title' => $title,
                    'body' => $body,
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    'type_data' => $typeDataString,
                ],
                'android' => ['priority' => 'high'],
                'apns' => [
                    'payload' => [
                        'aps' => ['sound' => 'default', 'content-available' => 1],
                    ],
                ],
            ],
        ];

        return $this->executeSend($payload);
    }

    protected function executeSend($payload)
    {
        $credentials = new ServiceAccountCredentials(
            'https://www.googleapis.com/auth/firebase.messaging',
            $this->credentialsPath
        );
        $authToken = $credentials->fetchAuthToken()['access_token'];
        $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";

        try {
            $response = Http::withToken($authToken)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->withBody(json_encode($payload), 'application/json')
                ->post($url);

            return $response->json();
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }
}
