<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CustomerMailRouter extends Mailable
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
            // ORDER STATUS
            // =========================
            'order_accepted' =>
                $this->subject('Your Order Was Accepted')
                    ->view('emails.customer.order.accepted'),

            'order_rejected' =>
                $this->subject('Your Order Was Rejected')
                    ->view('emails.customer.order.rejected'),

            'order_ready' =>
                $this->subject('Your Order is Ready')
                    ->view('emails.customer.order.ready'),

            'order_completed' =>
                $this->subject('Order Completed')
                    ->view('emails.customer.order.completed'),

            'order_cancelled' =>
                $this->subject('Order Cancelled')
                    ->view('emails.customer.order.cancelled'),

            // =========================
            // REFUND
            // =========================
            'refund_processed' =>
                $this->subject('Refund Processed')
                    ->view('emails.customer.refund.processed'),

            // =========================
            // DEFAULT
            // =========================
            default =>
                $this->subject('Notification')
                    ->view('emails.generic'),
        };
    }
}