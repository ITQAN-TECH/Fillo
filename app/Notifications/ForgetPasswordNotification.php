<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ForgetPasswordNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public $otp)
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->line('أنت تحاول تغيير كلمة المرور الخاصة بك على موقعنا')
            ->line("كود التحقق الخاص بك هو {$this->otp}")
            ->line('شكرا لك على استخدامك موقعنا.')
            ->line('إذا لم تقم بطلب تغيير كلمة المرور، يرجى تجاهل هذه الرسالة.');
    }
}
