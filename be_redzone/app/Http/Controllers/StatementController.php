<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
// use PDF;

class StatementController extends Controller
{
    public function index(Request $request, $subscriptionId)
    {
        $subscription = Subscription::with(['payments', 'histories'])->findOrFail($subscriptionId);

        $data = [
            'subscription' => $subscription,
            'payments'     => $subscription->payments,
            'histories'    => $subscription->histories,
        ];

        $pdf = Pdf::loadView('pdf.statement', $data)->setPaper('A4');

        return $pdf->download("Statement-{$subscription->id}.pdf");
    }
}
