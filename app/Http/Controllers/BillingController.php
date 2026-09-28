<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BillingController extends Controller
{
    public function index(Request $request): View
    {
        $company = $this->company($request);
        $attendeeCharges = $company->attendeeCharges()->with('event')->latest('finalized_at')->paginate(10);

        return view('billing.index', [
            'company' => $company,
            'attendeeCharges' => $attendeeCharges,
        ]);
    }

    public function updateContact(Request $request): RedirectResponse
    {
        $validated = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $this->company($request)->update(['email' => $validated['email']]);

        return back()->with('success', 'Billing contact updated.');
    }

    private function company(Request $request): Company
    {
        return Company::findOrFail($request->user()->company_id);
    }
}
