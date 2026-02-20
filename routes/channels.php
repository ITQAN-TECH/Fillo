<?php

use Illuminate\Support\Facades\Broadcast;

// ----------------------------------- For Customers --------------------------------- //

// Customer Channel
Broadcast::channel('customer.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
}, ['guards' => ['customers']]);

// ----------------------------------- For Admin --------------------------------- //

// Support Channel
Broadcast::channel('support.message.channel', function ($user) {
    if ($user && $user->hasPermission('show-support_chats')) {
        return true;
    }
    return false;
}, ['guards' => ['admins']]);
