<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AdminMailRouter extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $type,
        public array $data = []
    ) {}

    public function build()
    {
        return match ($this->type) {

            // =========================
            // EKYC
            // =========================
            'ekyc_submitted' =>
                $this->subject('New EKYC Submitted')
                    ->view('emails.admin.ekyc.submitted'),

            'ekyc_resubmitted' =>
                $this->subject('EKYC Resubmitted')
                    ->view('emails.admin.ekyc.resubmitted'),

            'ekyc_review_needed' =>
                $this->subject('EKYC Review Required')
                    ->view('emails.admin.ekyc.review'),

            // =========================
            // DEFAULT
            // =========================
            default =>
                $this->subject('Admin Notification')
                    ->view('emails.admin.generic'),
        };
    }
}