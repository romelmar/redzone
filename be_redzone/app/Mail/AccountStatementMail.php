<?php
namespace App\Mail;
use Illuminate\Mail\Mailable;

class AccountStatementMail extends Mailable
{
    public function __construct(public array $statement, public string $pdf) {}
    public function build()
    {
        return $this->subject('Statement of account - '.$this->statement['from'].' to '.$this->statement['to'])
            ->view('emails.account-statement')->with(['statement' => $this->statement])
            ->attachData($this->pdf, 'Statement-of-account-'.$this->statement['subscription_id'].'-'.$this->statement['to'].'.pdf', ['mime' => 'application/pdf']);
    }
}
