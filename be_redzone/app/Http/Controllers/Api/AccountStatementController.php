<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Services\AccountStatementService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\AccountStatementMail;

class AccountStatementController extends Controller
{
    private function statement(Request $request, Subscription $subscription, AccountStatementService $service): array
    {
        $dates = $request->validate(['from' => 'required|date_format:Y-m-d|before_or_equal:to', 'to' => 'required|date_format:Y-m-d|before_or_equal:today']);
        $from = Carbon::parse($dates['from'])->startOfDay();
        $to = Carbon::parse($dates['to'])->endOfDay();
        if ($from->copy()->addYears(10)->lt($to->copy()->startOfDay())) {
            throw \Illuminate\Validation\ValidationException::withMessages(['from' => 'Select a statement period of ten years or less.']);
        }
        return $service->build($subscription, $from, $to);
    }
    public function preview(Request $request, Subscription $subscription, AccountStatementService $service)
    {
        return response()->json($this->statement($request, $subscription, $service));
    }
    public function download(Request $request, Subscription $subscription, AccountStatementService $service)
    {
        $statement = $this->statement($request, $subscription, $service);
        return Pdf::loadView('pdf.account-statement', compact('statement'))->setPaper('a4')->download('Statement-of-account-'.$subscription->id.'-'.$statement['to'].'.pdf');
    }
    public function email(Request $request, Subscription $subscription, AccountStatementService $service)
    {
        $statement = $this->statement($request, $subscription, $service);
        validator(['email' => $statement['email']], ['email' => 'required|email'])->validate();
        $pdf = Pdf::loadView('pdf.account-statement', compact('statement'))->setPaper('a4')->output();
        Mail::to($statement['email'])->send(new AccountStatementMail($statement, $pdf));
        return response()->json(['message' => 'Statement of account emailed successfully.']);
    }
}
