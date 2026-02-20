<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Notifications\ForgetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendOTPEmailJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public Customer $customer, public $otp, public $type = 'verify_email')
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->type == 'verify_email') {
            $this->customer->notify(new VerifyEmailNotification($this->otp));
        } elseif ($this->type == 'forget_password') {
            $this->customer->notify(new ForgetPasswordNotification($this->otp));
        }
    }
}
