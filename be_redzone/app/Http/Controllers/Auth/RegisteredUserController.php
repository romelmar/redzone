<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;

class RegisteredUserController extends Controller
{
    public function store()
    {
        abort(403, 'Public registration is disabled. Contact your administrator for an account.');
    }
}
