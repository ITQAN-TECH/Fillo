<?php

namespace App\Notifications\admins;

use App\Models\CustomerIdentity;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SendCustomerIdentityAcceptedNotification extends Notification
{
    use Queueable;

    public function __construct(public CustomerIdentity $customerIdentity)
    {
        //
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'ar_message' => ' تم قبول توثيق الحساب الخاص بك',
            'en_message' => 'Your account verification has been accepted',
            'type_data' => [
                'type' => 'customer_identity',
                'customer_identity_id' => $this->customerIdentity->id,
            ],
        ];
    }
}
