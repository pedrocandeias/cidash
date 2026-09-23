<?php

namespace App\Mail;

use App\Models\Briefing;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * A briefing by email. Sent synchronously by the scheduler, right after it is generated.
 */
class BriefingMail extends Mailable
{
    public function __construct(public Briefing $briefing, public string $workspaceName) {}

    public function envelope(): Envelope
    {
        $date = $this->briefing->period_start->format('d/m/Y');

        return new Envelope(subject: $this->briefing->kind === 'weekly'
            ? __('Weekly briefing of :date', ['date' => $date]).' · '.$this->workspaceName
            : __('Briefing of :date', ['date' => $date]).' · '.$this->workspaceName);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.briefing', with: [
            'sections' => $this->briefing->content,
            'url' => route('briefings.show', $this->briefing),
        ]);
    }
}
