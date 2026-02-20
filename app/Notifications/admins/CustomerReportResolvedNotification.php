<?php

namespace App\Notifications\admins;

use App\Models\CustomerReport;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CustomerReportResolvedNotification extends Notification
{
    use Queueable;

    public function __construct(public CustomerReport $customerReport)
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
            'ar_message' => "تحذير من الإدارة :({$this->customerReport->resolution_reason})",
            'en_message' => "Warning from management :({$this->customerReport->resolution_reason})",
        ];
    }
}
