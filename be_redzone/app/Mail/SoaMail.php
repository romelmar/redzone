<?php 
// app/Mail/SoaMail.php
namespace App\Mail;

use App\Models\Subscription;
use App\Services\BillingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SoaMail extends Mailable {
  use Queueable, SerializesModels;
  public function __construct(public Subscription $subscription, public Carbon $month) {}

  public function build() {
    $billing = app(BillingService::class)->computeFor($this->subscription, $this->month);

    $pdf = Pdf::loadView('pdf.soa', [
      'subscription' => $this->subscription->load('subscriber','plan'),
      'month' => $this->month,
      'billing' => $billing,
      'bill_no' => $this->month->format('Ym').'-'.$this->subscription->id,
    ])->setPaper('a4');

    $filename = 'SOA-'.$this->subscription->id.'-'.$this->month->format('Y-m').'.pdf';

    return $this->subject('Statement of Account')
      ->view('emails.soa', ['subscription'=>$this->subscription,'month'=>$this->month,'billing'=>$billing])
      ->attachData($pdf->output(), $filename, ['mime'=>'application/pdf']);
  }
}
