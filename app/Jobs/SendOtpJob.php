<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\SendOtpNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendOtpJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public User $admin, public $otp)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->admin->notify(new SendOtpNotification($this->otp));
    }
}
