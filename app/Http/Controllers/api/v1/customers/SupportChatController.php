<?php

namespace App\Http\Controllers\api\v1\customers;

use App\Events\SupportMessageSentFromCustomerEvent;
use App\Http\Controllers\Controller;
use App\Models\SupportChat;
use App\Models\User;
use App\Notifications\customers\SendSupportMessageNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class SupportChatController extends Controller
{
    public function index(Request $request)
    {
        $customerId = Auth::guard('customers')->id();
        // استعلامك الأصلي لجلب وقت آخر رسالة لكل مجموعة
        $groupedChatsData = SupportChat::where('customer_id', $customerId)
            ->select('customer_id', DB::raw('MAX(created_at) as last_message_time'))
            ->groupBy('customer_id')
            ->where('customer_id', '!=', null)
            ->orderBy('last_message_time', 'desc')
            ->get();

        $chatCollection = collect();
        foreach ($groupedChatsData as $group) {
            // ابحث عن الرسالة الفعلية التي تطابق الشروط ووقت آخر رسالة
            $latestMessage = SupportChat::where('customer_id', $group->customer_id)
                ->where('created_at', $group->last_message_time)
                ->latest('id') // في حال وجود رسالتين بنفس الـ created_at، اختر الأحدث بالـ ID
                ->first();
            if ($latestMessage) {
                $unreadAdminMessagesCount = SupportChat::where('customer_id', $group->customer_id)
                    ->where('sender_type', 'admin')
                    ->whereNull('read_at')
                    ->count();
                $latestMessage->unread_messages_count = $unreadAdminMessagesCount;
                $chatCollection->push($latestMessage);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'support chats',
            'chats' => $chatCollection,
        ]);
    }

    public function show()
    {
        $customer = Auth::guard('customers')->user();
        try {
            $chat = SupportChat::where('customer_id', $customer->id)
                ->orderBy('created_at', 'asc')
                ->get();

            $chat->each(function ($message) {
                if ($message->sender_type == 'admin' && $message->read_at == null) {
                    $message->update([
                        'read_at' => now(),
                    ]);
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'support chat between customer and admin',
                'chat' => $chat,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
            ], 400);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'message' => 'required_without_all:image,audio|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:7168',
            'audio' => 'nullable|mimes:mp3,wav,ogg,mpeg,m4a,mp4,webm|max:1024',
        ]);
        $customer = Auth::guard('customers')->user();
        try {
            DB::beginTransaction();
            // قبل إرسال الرسالة: نجعل كل الرسائل الغير مقروءة من المشرفين للعميل الحالي مقروءة
            SupportChat::where('customer_id', $customer->id)
                ->where('sender_type', 'admin')
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
            $message = SupportChat::create([
                'customer_id' => $customer->id,
                'sender_type' => 'customer',
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
                // $admins = User::whereHasPermission('show-support_chats')->get();
                // Notification::send($admins, new SendSupportMessageNotification($message->load('customer')));
                DB::commit();
                broadcast(new SupportMessageSentFromCustomerEvent($message))->toOthers();

                return response()->json([
                    'success' => true,
                    'message' => __('responses.message sent successfully'),
                    'message_data' => $message,
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

    public function readMessage($message_id)
    {
        $customer = Auth::guard('customers')->user();
        $message = SupportChat::where('customer_id', $customer->id)->findOrFail($message_id);
        $message->update([
            'read_at' => now(),
        ]);

        return response()->noContent();
    }
}
