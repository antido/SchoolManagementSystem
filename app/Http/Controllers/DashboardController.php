<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the dashboard for the signed-in user's kind of account.
     */
    public function __invoke(Request $request, DashboardService $dashboards): Response
    {
        return Inertia::render('Dashboard', $dashboards->build($request->user()));
    }
}
