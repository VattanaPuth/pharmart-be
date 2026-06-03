<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OwnerMailRouter extends Mailable
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
                $this->subject('New EKYC Submission')
                    ->view('emails.owner.ekyc.submitted'),

            'ekyc_resubmitted' =>
                $this->subject('EKYC Resubmitted')
                    ->view('emails.owner.ekyc.resubmitted'),

            'ekyc_approved' =>
                $this->subject('EKYC Approved 🎉')
                    ->view('emails.owner.ekyc.approved'),

            'ekyc_rejected' =>
                $this->subject('EKYC Rejected')
                    ->view('emails.owner.ekyc.rejected'),

            'ekyc_suspended' =>
                $this->subject('EKYC Suspended')
                    ->view('emails.owner.ekyc.suspended'),

            // =========================
            // ORDERS
            // =========================
            'new_order' =>
                $this->subject('New Order Received')
                    ->view('emails.owner.order.new'),

            'order_cancelled' =>
                $this->subject('Order Cancelled')
                    ->view('emails.owner.order.cancelled'),

            'order_completed' =>
                $this->subject('Order Completed')
                    ->view('emails.owner.order.completed'),

            // =========================
            // DEFAULT
            // =========================
            default =>
                $this->subject('Notification')
                    ->view('emails.generic'),
        };
    }
}