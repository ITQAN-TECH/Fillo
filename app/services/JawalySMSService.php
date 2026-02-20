<?php

namespace App\services;

class JawalySMSService
{
    public static function sendMessage($recipients, $message): array
    {
        $curl = curl_init();
        $app_id = config('services.jawaly.api_key');
        $app_sec = config('services.jawaly.secret');
        $sender_name = config('services.jawaly.sender_name');
        $app_hash = base64_encode("$app_id:$app_sec");
        //        $staticLink = "https://docs.google.com/forms/d/15mSleBxLyUWnAMMpcnvnjsK8o9p-MPrgpaJdSdIjzpY/edit?ts=654b3511";
        $messages = [];
        $messages['messages'] = [];
        $messages['messages'][0]['text'] = $message;
        //        $messages["messages"][0]["Evaluation"] = $staticLink;
        $messages['messages'][0]['numbers'][] = $recipients;
        $messages['messages'][0]['sender'] = $sender_name;

        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api-sms.4jawaly.com/api/v1/account/area/sms/send',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($messages),
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'Authorization: Basic '.$app_hash,
            ],
        ]);

        $response = curl_exec($curl);
        curl_close($curl);

        return ['success' => true, 'data' => $response];
        //        if ($response->successful()) {
        //            // Taqnyat API might return different success structures.
        //            // You'll need to inspect the actual successful response to tailor this.
        //            // Assuming it returns a JSON object with message status.
        //            return ['success' => true, 'data' => $response->json()];
        //        } else {
        //            //                \Log::error('Taqnyat SMS API Error: ' . $response->body());
        //            return ['success' => false, 'message' => 'Failed to send SMS', 'details' => $response->json()];
        //        }
    }
}
