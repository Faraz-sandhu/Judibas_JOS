<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CommunicationInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $acceptanceUrl, public string $companyName) {}

    public function build()
    {
        return $this->subject('Join '.$this->companyName.' on Judibass Communication')->view('emails.communication-invitation');
    }
}
