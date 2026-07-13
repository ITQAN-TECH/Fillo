<?php

use App\Console\Commands\DeleteStalePendingOrders;
use App\Console\Commands\SendSheduleNotificationFromAdmin;
use Illuminate\Support\Facades\Schedule;

Schedule::command(SendSheduleNotificationFromAdmin::class)->everyMinute();

// Safety net for abandoned checkouts — mirrors MyFatoorah's own invoice
// expiration window so unpaid orders/bookings don't stay "pending" forever
// if a webhook is ever missed.
Schedule::command(DeleteStalePendingOrders::class)->withoutOverlapping()->daily();
