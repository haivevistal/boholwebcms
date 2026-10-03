<?php

namespace App\Http\Controllers;

abstract class Controller
{
    /**
     * Abort with 403 unless the current user has the capability.
     */
    protected function authorizeCap(string $capability, mixed ...$args): void
    {
        abort_unless(current_user_can($capability, ...$args), 403, 'Sorry, you are not allowed to do that.');
    }
}
