<?php

namespace App\Http\Controllers;

use App\Services\ProviderStaffInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProviderStaffInvitationController extends Controller
{
    public function show(string $token): View
    {
        return view('provider-staff-invitations.accept', compact('token'));
    }

    public function accept(Request $request, string $token, ProviderStaffInvitationService $invitations): RedirectResponse
    {
        $membership = $invitations->accept($token, $request->user());

        return redirect('/provider')->with('status', 'Provider staff invitation accepted for '.$membership->providerCompany()->withoutGlobalScopes()->value('name').'.');
    }
}
