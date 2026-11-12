<?php

namespace App\Http\Controllers;

use App\Demo\HeroPick;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    /**
     * The landing page, with a sample pick from the demo library for the hero.
     */
    public function __invoke(HeroPick $hero): Response
    {
        return Inertia::render('Welcome', ['hero' => $hero()]);
    }
}
