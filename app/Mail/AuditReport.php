<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class AuditReport extends Mailable
{
    public function __construct(
        private string $pdf,
        private string $filename,
        private string $company,
    ) {}

    public function build(): self
    {
        return $this
            ->subject('Monatlicher Audit-Bericht – ' . $this->company . ' (' . now()->format('m/Y') . ')')
            ->view('mail.audit-report')
            ->attachData($this->pdf, $this->filename, ['mime' => 'application/pdf']);
    }
}
