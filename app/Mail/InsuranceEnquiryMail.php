<?php

namespace App\Mail;

use App\Models\InsuranceEnquiry;
use App\Models\InsuranceProduct;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the insurance contact (Admin -> Services -> Insurance Products, insurance@example.com to start with) each time
 * someone sends "Get a call back" (Products) or "Join now" (Join As Advisor) on an Insurance page: all their details.
 */
class InsuranceEnquiryMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public InsuranceEnquiry $enquiry)
    {
    }

    public function envelope(): Envelope
    {
        $what = $this->enquiry->kind === 'adviser' ? 'Join As Advisor' : 'Get a call back';

        return new Envelope(subject: 'New insurance enquiry (' . $what . '): ' . $this->enquiry->name . ', ' . $this->enquiry->contact);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.insurance-enquiry', with: [
            'what' => $this->enquiry->kind === 'adviser' ? 'Join As Advisor' : 'Products: Get a call back',
            'insurance' => InsuranceProduct::INSURANCES[$this->enquiry->insurance] ?? $this->enquiry->insurance,
            'listUrl' => route('admin.insurance-enquiries.index'),
        ]);
    }
}
