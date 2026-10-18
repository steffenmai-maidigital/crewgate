<?php

namespace App\Mail;

use App\Models\Onboarding;
use App\Models\Role;
use Illuminate\Mail\Mailable;

class StepNotification extends Mailable
{
    public function __construct(
        public Onboarding $onboarding,
        public Role $role,
    ) {}

    public function build(): self
    {
        return $this
            ->subject('Onboarding: ' . $this->onboarding->fullName() . ' – Aufgabe für ' . $this->role->name)
            ->view('mail.step-notification');
    }
}
