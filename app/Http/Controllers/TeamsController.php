<?php

namespace App\Http\Controllers;

use App\Services\SavedTeamService;

class TeamsController extends Controller
{
    public function __construct(
        protected SavedTeamService $teams
    ) {}

    public function index()
    {
        return view('teams', [
            'teams' => $this->teams->list(),
        ]);
    }
}
