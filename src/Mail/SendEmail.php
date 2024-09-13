<?php

namespace Kkboranbay\BackpackExport\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SendEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $filePath
    ) {}

    public function build()
    {
        $mail = $this->view('backpack-export::export_mail')
                    ->subject('Exported Data');

        $mail->attach(storage_path($this->filePath));

        return $mail;
    }
}
