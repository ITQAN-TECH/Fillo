<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Models\Investor;
use App\Models\User;
use App\services\FCMService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The collection of recipients (User, Customer, Investor models, etc.).
     *
     * @var \Illuminate\Support\Collection
     */
    protected $recipients;

    /**
     * The specific Notification object (e.g., RejectBookingNotification, AcceptHouseRequestNotification).
     *
     * @var \Illuminate\Notifications\Notification|null
     */
    protected $notification;

    /**
     * The title of the FCM notification (translation key or text).
     *
     * @var string|null
     */
    protected $title;

    /**
     * The body/description of the FCM notification (translation key or text).
     *
     * @var string|null
     */
    protected $body;

    /**
     * Whether title and body are translation keys (true) or plain text (false).
     *
     * @var bool
     */
    protected $isTranslationKey;

    /**
     * Translation parameters for title and body.
     *
     * @var array
     */
    protected $translationParams;

    /**
     * The type of the notification.
     *
     * @var string|null
     */
    protected $FCMNotificationTypeData;

    /**
     * The topic of the FCM notification.
     *
     * @var string|null
     */
    protected $topic;

    /**
     * Create a new job instance.
     *
     * @param  \Illuminate\Notifications\Notification|null  $notification  (Optional: DB notification object)
     * @param  string|null  $title  (Optional: FCM notification title or translation key)
     * @param  string|null  $body  (Optional: FCM notification body or translation key)
     * @param  bool  $isTranslationKey  (Optional: Whether title/body are translation keys)
     * @param  array  $translationParams  (Optional: Parameters for translation placeholders)
     * @return void
     */
    public function __construct(
        ?Collection $recipients = null,
        $notification = null,
        ?string $title = null,
        ?string $body = null,
        bool $isTranslationKey = false,
        array $translationParams = [],
        array $FCMNotificationTypeData = [],
        ?string $topic = null // إضافة التوبيك هنا
    ) {
        $this->recipients = $recipients ?? collect();
        $this->notification = $notification;
        $this->title = $title;
        $this->body = $body;
        $this->isTranslationKey = $isTranslationKey;
        $this->translationParams = $translationParams;
        $this->FCMNotificationTypeData = $FCMNotificationTypeData;
        $this->topic = $topic;
    }

    /**
     * Execute the job.
     *
     * @param  \App\services\FCMService  $customerFCMService  (General/Customer FCM Service)
     * @return void
     */
    public function handle(FCMService $fcmService)
    {
        // 1. Send the database notification (if $notification object is provided)
        if ($this->notification) {
            Notification::send($this->recipients, $this->notification);
        }

        // 2. Send FCM notification (if $title and $body are provided)
        if ($this->title && $this->body) {

            if (! $this->topic || $this->topic == 'specific_user') {
                // التكرار على المستلمين وإرسال FCM حسب نوع المستخدم
                foreach ($this->recipients as $recipient) {
                    // إعادة تحميل الـ model من قاعدة البيانات لضمان تحميل العلاقات بشكل صحيح
                    if ($recipient instanceof Customer) {
                        $recipient = Customer::with('fcmTokens')->find($recipient->id);
                    } elseif ($recipient instanceof User) {
                        $recipient = User::find($recipient->id);
                    }

                    if (! $recipient) {
                        continue; // تخطي إذا لم يتم العثور على المستخدم
                    }

                    $shouldReceiveFCM = $recipient->receive_notifications ?? true;
                    // **تخطي إرسال الإشعار إذا كان المستخدم قد أوقف الإشعارات**
                    if ($recipient instanceof Customer) {
                        if (! $shouldReceiveFCM) {
                            continue;
                        }
                    }

                    // الحصول على جميع رموز FCM للمستخدم
                    $fcmTokens = [];
                    if ($recipient instanceof Customer) {
                        // استخدام العلاقة المحملة أو تحميلها من قاعدة البيانات
                        $fcmTokens = $recipient->fcmTokens()->pluck('token')->toArray();
                    } elseif ($recipient instanceof User) {
                        // للمستخدمين (Admins) - إذا كان لديهم fcm_token مباشرة (للتوافق مع الكود القديم)
                        $fcmToken = $recipient->fcm_token ?? null;
                        if ($fcmToken) {
                            $fcmTokens = [$fcmToken];
                        }
                    }

                    if (empty($fcmTokens)) {
                        continue; // تخطي إذا لم يكن هناك رموز FCM
                    }

                    // إرسال الإشعار لجميع الرموز
                    if ($recipient instanceof Customer) {
                        // الحصول على رموز FCM مع اللغة
                        $fcmTokensWithLanguage = $recipient->fcmTokens()->get(['token', 'language']);

                        foreach ($fcmTokensWithLanguage as $fcmTokenRecord) {
                            $fcmToken = $fcmTokenRecord->token;
                            if (empty($fcmToken)) {
                                continue;
                            }

                            // ترجمة العنوان والمحتوى حسب لغة كل توكن
                            $title = $this->title;
                            $body = $this->body;

                            // if ($this->isTranslationKey) {
                            $locale = $fcmTokenRecord->language ?? 'ar';
                            $title = __($this->title, $this->translationParams, $locale);
                            $body = __($this->body, $this->translationParams, $locale);
                            // }

                            try {
                                $result = $fcmService->sendNotification($fcmToken, $title, $body, $this->FCMNotificationTypeData);
                            } catch (\Exception $e) {
                                // Log::error('Error sending FCM notification: '.$e->getMessage(), [
                                //     'recipient_id' => $recipient->id,
                                //     'recipient_type' => get_class($recipient),
                                //     'token' => substr($fcmToken, 0, 20).'...',
                                // ]);
                            }
                        }
                    } else {
                        // للمستخدمين (Admins) - إرسال بدون ترجمة حسب اللغة
                        foreach ($fcmTokens as $fcmToken) {
                            if (empty($fcmToken)) {
                                continue;
                            }

                            try {
                                $title = $this->title;
                                $body = $this->body;

                                $locale = $fcmTokenRecord->language ?? 'ar';
                                $title = __($title, $this->translationParams, $locale);
                                $body = __($body, $this->translationParams, $locale);
                                $result = $fcmService->sendNotification($fcmToken, $title, $body, $this->FCMNotificationTypeData);
                            } catch (\Exception $e) {
                                // Log::error('Error sending FCM notification: '.$e->getMessage(), [
                                //     'recipient_id' => $recipient->id,
                                //     'recipient_type' => get_class($recipient),
                                //     'token' => substr($fcmToken, 0, 20).'...',
                                // ]);
                            }
                        }
                    }
                }
            } else {
                try {
                    if ($this->topic == 'customers') {
                        $fcmService->sendToTopic('customers', $this->title, $this->body, $this->FCMNotificationTypeData);
                    } else {
                        $fcmService->sendToTopic($this->topic, $this->title, $this->body, $this->FCMNotificationTypeData);
                    }
                } catch (\Exception $e) {
                    Log::error('Error sending FCM notification to topic: '.$e->getMessage(), [
                        'topic' => $this->topic,
                        'title' => $this->title,
                        'body' => $this->body,
                    ]);
                }
            }
        }
    }
}
