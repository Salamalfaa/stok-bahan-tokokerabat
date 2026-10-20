<?php

namespace App\Mail;

use App\Models\DisciplinaryAction;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AttendanceWarning extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public DisciplinaryAction $action) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->action->warning_subject ?? 'Surat Peringatan',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.attendance.warning',
            with: [
                'action' => $this->action,
            ],
        );
    }
}
