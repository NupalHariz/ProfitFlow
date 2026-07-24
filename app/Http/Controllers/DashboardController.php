<?php

namespace App\Http\Controllers;

use App\Queries\Dashboard\DashboardOverviewQuery;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    public function __invoke(DashboardOverviewQuery $query): Response
    {
        return Inertia::render('dashboard', $query->get());
    }
}
