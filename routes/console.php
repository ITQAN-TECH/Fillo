<?php

use App\Console\Commands\SendSheduleNotificationFromAdmin;
use Illuminate\Support\Facades\Schedule;

Schedule::command(SendSheduleNotificationFromAdmin::class)->everyMinute();
