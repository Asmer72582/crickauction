<?php

namespace App\Http\Controllers;

use App\Models\RegistrationForm;
use App\Models\Tournament;
use App\Services\RegistrationFormService;
use Illuminate\Http\Request;

class RegistrationFormController extends Controller
{
    public function __construct(
        protected RegistrationFormService $forms
    ) {}

    public function edit(Tournament $tournament)
    {
        $form = $this->forms->getOrCreateForTournament($tournament);
        $version = $form->currentVersionModel();
        $schema = $version?->schema ?? $this->forms->defaultSchema();

        return view('auction.registration-form', [
            'tournament' => $tournament,
            'form' => $form,
            'schema' => $schema,
            'versions' => $form->versions()->orderByDesc('version')->get(),
            'theme' => $tournament->theme(),
        ]);
    }

    public function update(Request $request, Tournament $tournament)
    {
        $form = $this->forms->getOrCreateForTournament($tournament);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'status' => ['required', 'in:draft,open,closed'],
            'deadline_at' => ['nullable', 'date'],
            'schema' => ['required', 'array'],
            'schema.sections' => ['required', 'array'],
        ]);

        $form->update([
            'name' => $data['name'],
            'status' => $data['status'],
            'deadline_at' => $data['deadline_at'] ?? null,
        ]);

        $this->forms->updateCurrentSchema($form, $data['schema']);

        return response()->json(['ok' => true, 'version' => $form->fresh()->current_version]);
    }

    public function preview(Tournament $tournament)
    {
        $form = $this->forms->getOrCreateForTournament($tournament);
        $schema = $form->currentVersionModel()?->schema ?? $this->forms->defaultSchema();

        return view('auction.registration-preview', [
            'form' => $form,
            'schema' => $schema,
            'preview' => true,
        ]);
    }
}
