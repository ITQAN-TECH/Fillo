<?php

namespace App\Notifications\admins;

use App\Models\CustomerIdentity;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SendCustomerIdentityRejectedNotification extends Notification
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
            'ar_message' => " نم رفض توثيق الحساب الخاص بك بسبب {$this->customerIdentity->reject_reason}",
            'en_message' => "Your account verification has been rejected because {$this->customerIdentity->reject_reason}",
            'type_data' => [
                'type' => 'customer_identity',
                'id' => $this->customerIdentity->id,
            ],
        ];
    }
}
