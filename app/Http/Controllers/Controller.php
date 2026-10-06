<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    // Gives every controller $this->authorize('update', $student).
    use AuthorizesRequests;
}
