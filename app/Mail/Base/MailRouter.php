<?php

namespace App\Mail\Base;

use Illuminate\Mail\Mailable;

class MailRouter extends Mailable
{
    public function __construct(
        public string $type,
        public array $data = []
    ) {}

    public function build()
    {
        return $this
            ->subject($this->getSubject())
            ->view('emails.layouts.base')
            ->with([
                'title' => $this->getSubject(),
                'content' => $this->renderContent(),
            ]);
    }

    private function getSubject(): string
    {
        return match ($this->type) {

            // EKYC
            'ekyc_submitted' => 'New EKYC Submitted',
            'ekyc_resubmitted' => 'EKYC Resubmitted',
            'ekyc_approved' => 'EKYC Approved',
            'ekyc_rejected' => 'EKYC Rejected',
            'account_suspended' => 'Pharmacy Account Suspended',

            // OWNER ORDERS
            'new_order' => 'New Order Received',
            'order_cancelled' => 'Order Cancelled',

            // CUSTOMER
            'order_accepted' => 'Order Accepted',
            'order_declined' => 'Order Declined',

            default => 'Notification',
        };
    }

    private function renderContent(): string
    {
        return match ($this->type) {

            // ================= EKYC =================
            'ekyc_submitted' => "
                <p>A new EKYC has been submitted.</p>
                <p><b>Owner:</b> " . ($this->data['owner_name'] ?? '-') . "</p>
                <p><b>Pharmacy:</b> " . ($this->data['pharmacy_name'] ?? '-') . "</p>
            ",

            'ekyc_approved' => "
                <p>Your EKYC has been approved.</p>
                <p><b>message:</b> " . ($this->data['message'] ?? '-') . "</p>
            ",

            'ekyc_rejected' => "
                <p>Your EKYC was rejected.</p>
                <p><b>Reason:</b> " . ($this->data['reason'] ?? '-') . "</p>
                <p><b>message:</b> " . ($this->data['message'] ?? '-') . "</p>
            ",

            'account_suspended' => "
                <p>Your Pharmacy account was suspended by an admin vatana.</p>
                <p><b>Reason:</b> " . ($this->data['reason'] ?? '-') . "</p>
                <p><b>message:</b> " . ($this->data['message'] ?? '-') . "</p>
            ",

            // ================= ORDERS =================
            'new_order' => "
                <p>You received a new order.</p>
                <p><b>message:</b> " . ($this->data['message'] ?? '-') . "</p>
                <p><b> Number:</b> " . ($this->data['order_number'] ?? '-') . "</p>
                <p><b> ID:</b> " . ($this->data['order_id'] ?? '-') . "</p>
                <p><b>Total:</b> $" . ($this->data['total'] ?? 0) . "</p>
                
            ",

            'order_accepted' => "<p>Your order has been accepted.</p>",

            'order_declined' => "<p>Your order was declined.</p>",

            default => "<p>You have a new notification.</p>",
        };
    }
}