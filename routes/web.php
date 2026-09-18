<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Api\AuctionApiController;
use App\Http\Controllers\Api\MatchController;
use App\Http\Controllers\Api\OverlayPublicController;
use App\Http\Controllers\Api\SavedTeamController;
use App\Http\Controllers\AuctionController;
use App\Http\Controllers\AuctionHubController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BroadcastController;
use App\Http\Controllers\ControlController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PlayerRegistrationAdminController;
use App\Http\Controllers\PublicRegistrationController;
use App\Http\Controllers\RegistrationFormController;
use App\Http\Controllers\TeamsController;
use App\Http\Controllers\TournamentMatchController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.store');
    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// Public player registration (no admin)
Route::get('/registration/{token}', [PublicRegistrationController::class, 'show'])->name('registration.show');
Route::post('/registration/{token}', [PublicRegistrationController::class, 'submit'])->name('registration.submit');
Route::get('/registration/thanks/{code}', [PublicRegistrationController::class, 'thanks'])->name('registration.thanks');

// Token-gated overlays for OBS / owners (no login)
Route::get('/overlay/auction/{auction}', [AuctionController::class, 'overlay'])->name('overlay.auction');
Route::get('/owners/auction/{auction}', [AuctionController::class, 'owners'])->name('owners.auction');
Route::get('/overlay/match/{match}', [OverlayPublicController::class, 'page'])->name('overlay.match');
Route::get('/api/overlay/match/{match}', [OverlayPublicController::class, 'data'])->name('overlay.match.data');
Route::get('/api/overlay/match/{match}/poll', [OverlayPublicController::class, 'poll'])->name('overlay.match.poll');
Route::get('/overlay', [ControlController::class, 'overlay'])->name('overlay');
Route::get('/overlay.html', [ControlController::class, 'overlay']);

Route::get('/api/auctions/{auction}/state', [AuctionApiController::class, 'state']);
Route::get('/api/auctions/{auction}/poll', [AuctionApiController::class, 'poll']);
Route::get('/api/matches/{room}', [MatchController::class, 'show']);
Route::get('/api/matches/{room}/poll', [MatchController::class, 'poll']);
Route::get('/api/matches/{room}/stream', [MatchController::class, 'stream']);

Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/account', [AccountController::class, 'show'])->name('account.show');
    Route::patch('/account', [AccountController::class, 'update'])->name('account.update');
    Route::patch('/account/password', [AccountController::class, 'updatePassword'])->name('account.password');
    Route::post('/account/operators', [AccountController::class, 'storeOperator'])->name('account.operators');

    Route::post('/tournaments', [DashboardController::class, 'store'])->name('tournaments.store');
    Route::get('/teams', [TeamsController::class, 'index'])->name('teams.index');
    Route::get('/wallets', [DashboardController::class, 'wallets'])->name('wallets.index');
    Route::get('/auctions', [AuctionController::class, 'index'])->name('auctions.index');
    Route::get('/auctions/create', [AuctionController::class, 'createStandalone'])->name('auctions.create');
    Route::post('/auctions', [AuctionController::class, 'storeStandalone'])->name('auctions.store');
    Route::get('/auctions/{auction}', [AuctionController::class, 'show'])->name('auctions.show');
    Route::patch('/auctions/{auction}', [AuctionController::class, 'update'])->name('auctions.update');
    Route::post('/auctions/{auction}/players', [AuctionController::class, 'addPlayer'])->name('auctions.players.store');
    Route::patch('/auctions/{auction}/teams/{team}', [AuctionController::class, 'updateTeam'])->name('auctions.teams.update');
    Route::patch('/auctions/{auction}/pool/{player}', [AuctionController::class, 'updatePoolPlayer'])->name('auctions.pool.update');
    Route::post('/auctions/{auction}/registrations/{registration}/approve', [AuctionController::class, 'approveRegistration'])->name('auctions.registrations.approve');
    Route::post('/auctions/{auction}/registrations/{registration}/reject', [AuctionController::class, 'rejectRegistration'])->name('auctions.registrations.reject');
    Route::post('/auctions/{auction}/pool/sync', [AuctionController::class, 'syncApprovedPool'])->name('auctions.pool.sync');
    Route::post('/auctions/{auction}/publish', [AuctionController::class, 'publishWorkspace'])->name('auctions.publish');
    Route::get('/tournaments/{tournament}/matches', [TournamentMatchController::class, 'index'])->name('tournaments.matches');
    Route::post('/tournaments/{tournament}/matches', [TournamentMatchController::class, 'store'])->name('tournaments.matches.store');

    Route::get('/tournaments/{tournament}/auction', [AuctionHubController::class, 'index'])->name('tournaments.auction');
    Route::post('/tournaments/{tournament}/auction/demo-data', [AuctionHubController::class, 'seedDemo'])->name('tournaments.auction.demo');
    Route::get('/tournaments/{tournament}/auction/registration-form', [RegistrationFormController::class, 'edit'])->name('tournaments.registration-form.edit');
    Route::post('/tournaments/{tournament}/auction/registration-form', [RegistrationFormController::class, 'update'])->name('tournaments.registration-form.update');
    Route::get('/tournaments/{tournament}/auction/registration-form/preview', [RegistrationFormController::class, 'preview'])->name('tournaments.registration-form.preview');
    Route::get('/tournaments/{tournament}/auction/players', [PlayerRegistrationAdminController::class, 'index'])->name('tournaments.registered-players');
    Route::get('/tournaments/{tournament}/auction/players/{registration}', [PlayerRegistrationAdminController::class, 'show'])->name('tournaments.registered-players.show');
    Route::post('/tournaments/{tournament}/auction/players/{registration}/approve', [PlayerRegistrationAdminController::class, 'approve'])->name('tournaments.registered-players.approve');
    Route::post('/tournaments/{tournament}/auction/players/{registration}/reject', [PlayerRegistrationAdminController::class, 'reject'])->name('tournaments.registered-players.reject');
    Route::post('/tournaments/{tournament}/auction/players/{registration}/changes', [PlayerRegistrationAdminController::class, 'requestChanges'])->name('tournaments.registered-players.changes');
    Route::get('/tournaments/{tournament}/auctions/create', [AuctionController::class, 'create'])->name('tournaments.auctions.create');
    Route::post('/tournaments/{tournament}/auctions', [AuctionController::class, 'store'])->name('tournaments.auctions.store');
    Route::get('/tournaments/{tournament}/auctions/{auction}/pool', [AuctionController::class, 'pool'])->name('tournaments.auctions.pool');
    Route::get('/tournaments/{tournament}/auctions/{auction}/live', [AuctionController::class, 'live'])->name('tournaments.auctions.live');
    Route::get('/tournaments/{tournament}/auctions/{auction}/results', [AuctionController::class, 'results'])->name('tournaments.auctions.results');

    Route::prefix('api/auctions/{auction}')->group(function () {
        Route::post('/start', [AuctionApiController::class, 'start']);
        Route::post('/pause', [AuctionApiController::class, 'pause']);
        Route::post('/resume', [AuctionApiController::class, 'resume']);
        Route::post('/next', [AuctionApiController::class, 'nextPlayer']);
        Route::post('/bid', [AuctionApiController::class, 'bid']);
        Route::post('/undo-bid', [AuctionApiController::class, 'undoBid']);
        Route::post('/sold', [AuctionApiController::class, 'sold']);
        Route::post('/unsold', [AuctionApiController::class, 'unsold']);
        Route::post('/complete', [AuctionApiController::class, 'complete']);
        Route::patch('/pool/{playerId}', [AuctionApiController::class, 'updatePoolPlayer']);
        Route::patch('/rules', [AuctionApiController::class, 'updateRules']);
        Route::post('/broadcast', [AuctionApiController::class, 'setBroadcast']);
        Route::patch('/teams/{team}', [AuctionApiController::class, 'updateTeam']);
        Route::patch('/queue', [AuctionApiController::class, 'reorderQueue']);
    });

    Route::get('/api/themes', [DashboardController::class, 'themes'])->name('themes.index');

    Route::get('/tournaments/{tournament}/broadcast', [BroadcastController::class, 'index'])->name('broadcast.index');
    Route::get('/broadcast/match/{match}', [BroadcastController::class, 'show'])->name('broadcast.show');
    Route::post('/broadcast/match/{match}', [BroadcastController::class, 'update'])->name('broadcast.update');

    Route::get('/control', [ControlController::class, 'index'])->name('control');
    Route::get('/control/knockout', [ControlController::class, 'knockout'])->name('control.knockout');
    Route::get('/control.html', fn () => redirect('/control?'.request()->getQueryString()));

    Route::prefix('api/matches/{room}')->group(function () {
        Route::post('/patch', [MatchController::class, 'patch']);
        Route::post('/upload-logo', [MatchController::class, 'uploadLogo']);
        Route::post('/players', [MatchController::class, 'setPlayers']);
        Route::post('/batting', [MatchController::class, 'setBatting']);
        Route::post('/action', [MatchController::class, 'action']);
        Route::post('/animate', [MatchController::class, 'animate']);
    });

    Route::prefix('api/teams')->group(function () {
        Route::get('/', [SavedTeamController::class, 'index']);
        Route::post('/', [SavedTeamController::class, 'store']);
        Route::get('/{team}', [SavedTeamController::class, 'show']);
        Route::put('/{team}', [SavedTeamController::class, 'update']);
        Route::delete('/{team}', [SavedTeamController::class, 'destroy']);
    });
});
