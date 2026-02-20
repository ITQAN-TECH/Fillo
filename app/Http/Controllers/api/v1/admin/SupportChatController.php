<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Events\SupportMessageSentFromAdminEvent;
use App\Http\Controllers\Controller;
use App\Jobs\SendNotificationJob;
use App\Models\Customer;
use App\Models\SupportChat;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SupportChatController extends Controller
{
    public function customersSupportChats(Request $request)
    {
        if (! Auth::guard('admins')->user()->hasPermission('show-support_chats')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
        ]);
        // استعلامك الأصلي لجلب وقت آخر رسالة لكل مجموعة
        $groupedChatsData = SupportChat::select('customer_id', DB::raw('MAX(created_at) as last_message_time'))
            ->groupBy('customer_id')
            ->where('customer_id', '!=', null)
            ->when($request->customer_id, function ($query) use ($request) {
                $query->where('customer_id', $request->customer_id);
            })
            ->orderBy('last_message_time', 'desc')
            ->get();

        $chatCollection = collect();
        foreach ($groupedChatsData as $group) {
            // ابحث عن الرسالة الفعلية التي تطابق الشروط ووقت آخر رسالة
            $latestMessage = SupportChat::where('customer_id', $group->customer_id)
                ->where('created_at', $group->last_message_time)
                ->with('customer') // قم بتحميل العلاقات هنا
                ->latest('id') // في حال وجود رسالتين بنفس الـ created_at، اختر الأحدث بالـ ID
                ->first();
            if ($latestMessage) {
                $unreadAdminMessagesCount = SupportChat::where('customer_id', $group->customer_id)
                    ->where('sender_type', 'customer')
                    ->whereNull('read_at')
                    ->count();
                $latestMessage->unread_messages_count = $unreadAdminMessagesCount;
                $chatCollection->push($latestMessage);
            }
        }

        $report = [];

        $report['total_chats_count'] = SupportChat::distinct('customer_id')
            ->whereNotNull('customer_id')
            ->count('customer_id');
        $report['total_unread_chats_count'] = SupportChat::whereNull('read_at')
            ->where('sender_type', 'customer')
            ->distinct('customer_id')
            ->whereNotNull('customer_id')
            ->count('customer_id');
        $report['today_chats_count'] = SupportChat::whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()])
            ->where('sender_type', 'customer')
            ->distinct('customer_id')
            ->whereNotNull('customer_id')
            ->count('customer_id');

        return response()->json([
            'success' => true,
            'message' => __('responses.support chats with customers'),
            'report' => $report,
            'chats' => $chatCollection,
        ]);
    }

    public function showCustomerSupportChat(Request $request)
    {
        if (! Auth::guard('admins')->user()->hasPermission('show-support_chats')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
        ]);
        $customer = Customer::findorFail($request->customer_id);
        $chat = SupportChat::where('customer_id', $customer->id)
            ->orderBy('created_at', 'asc')
            ->get();
        $chat->each(function ($message) {
            if ($message->sender_type == 'customer' && $message->read_at == null) {
                $message->update([
                    'read_at' => now(),
                ]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => __('responses.support chat with customer'),
            'customer' => $customer,
            'chat' => $chat,
        ]);
    }

    public function createCustomerSupportChat(Request $request)
    {
        if (! Auth::guard('admins')->user()->hasPermission('create-support_chats')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'message' => 'required_without_all:image,audio',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:7168',
            'audio' => 'nullable|file|mimes:mp3,wav,ogg,mpeg,m4a,mp4,webm|max:1024',
        ]);
        $customer = Customer::findOrFail($request->customer_id);
        try {
            DB::beginTransaction();
            SupportChat::where('customer_id', $customer->id)
                ->where('sender_type', 'customer')
                ->whereNull('read_at')
                ->update(['read_at' => now()]);

            $message = SupportChat::create([
                'customer_id' => $customer->id,
                'sender_type' => 'admin',
                'message' => $request->message,
            ]);
            if ($message) {
                if ($request->hasFile('image') && $request->image != null) {
                    $file = $request->image;
                    $name = $file->hashName();
                    $filename = time().'_'.uniqid().'_'.$name;
                    $file->storeAs('public/media/', $filename);
                    $message->update([
                        'image' => $filename,
                    ]);
                    $message->save();
                }
                if ($request->hasFile('audio') && $request->audio != null) {
                    $file = $request->audio;
                    $name = $file->hashName();
                    $filename = time().'.'.$name;
                    $file->storeAs('public/media/', $filename);
                    $message->update([
                        'audio' => $filename,
                    ]);
                    $message->save();
                }
                $recipients = Collection::make([$customer]);

                if ($recipients->isNotEmpty()) {
                    // كائن الإشعار الخاص بـ Laravel
                    // $notification = new SendSupportMessageNotification($message);

                    // مفاتيح الترجمة لـ FCM (سيتم ترجمتها حسب لغة كل مستقبِل)
                    $fcmTitleKey = 'responses.Customer Service Chat';
                    if (! empty($message->message)) {
                        $fcmBodyKey = $message->message;
                    } elseif (! empty($message->image)) {
                        $fcmBodyKey = 'responses.You have received a new image';
                    } elseif (! empty($message->audio)) {
                        $fcmBodyKey = 'responses.You have received a new audio';
                    } else {
                        $fcmBodyKey = ''; // fallback
                    }

                    $fcmNotificationTypeData = [
                        'type' => 'support_chat_message',
                        'customer_id' => $customer->id,
                    ];

                    // إرسال Job العام: المعامل الأخير true يعني أن title و body هما مفاتيح ترجمة
                    dispatch(new SendNotificationJob($recipients, null, $fcmTitleKey, $fcmBodyKey, true, [], $fcmNotificationTypeData));
                }
                broadcast(new SupportMessageSentFromAdminEvent($message))->toOthers();
                DB::commit();

                return response()->json([
                    'success' => true,
                    'created_at' => $message->created_at,
                    'user_name' => $customer->name,
                    'message' => $message->message,
                    'image' => $message->image,
                    'audio' => $message->audio,
                    'chat' => $message,
                ], 201);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => __('responses.error happened'),
                ], 400);
            }
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
            ], 400);
        }
    }
}
