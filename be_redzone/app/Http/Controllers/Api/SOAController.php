<?php 
// app/Http/Controllers/SOAController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Services\BillingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\SoaMail;

class SOAController extends Controller {
  public function download(Request $r, Subscription $subscription) {
    $month = Carbon::parse($r->get('month', now()->startOfMonth()));
    $billing = app(BillingService::class)->computeFor($subscription, $month);

    $pdf = Pdf::loadView('pdf.soa', [
      'subscription' => $subscription->load('subscriber','plan'),
      'month' => $month,
      'billing' => $billing,
      'bill_no' => $month->format('Ym').'-'.$subscription->id,
    ])->setPaper('a4');

    $filename = 'SOA-'.$subscription->id.'-'.$month->format('Y-m').'.pdf';
    return $pdf->download($filename);
  }

  public function email(Request $r, Subscription $subscription) {
    $month = Carbon::parse($r->get('month', now()->startOfMonth()));
    if (!$subscription->subscriber->email) return back()->with('error','No email on file.');

    Mail::to($subscription->subscriber->email)->send(new SoaMail($subscription, $month));
    return back()->with('success','SOA emailed.');
  }
}
