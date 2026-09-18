<?php

namespace App\Services;

use App\Models\Auction;
use App\Models\RegistrationForm;
use App\Models\RegistrationFormVersion;
use App\Models\Tournament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class RegistrationFormService
{
    public function defaultSchema(): array
    {
        return [
            'sections' => [
                [
                    'id' => 'personal',
                    'title' => 'PERSONAL DETAILS',
                    'fields' => [
                        $this->field('full_name', 'text', 'Full Name', true, ['placeholder' => 'Enter full name']),
                        $this->field('profile_photo', 'image', 'Profile Photo', false),
                        $this->field('date_of_birth', 'date', 'Date of Birth', false),
                        $this->field('mobile', 'phone', 'Mobile Number', true),
                        $this->field('whatsapp', 'phone', 'WhatsApp Number', false),
                        $this->field('email', 'email', 'Email', false),
                        $this->field('city', 'text', 'City', false),
                        $this->field('state', 'text', 'State', false),
                    ],
                ],
                [
                    'id' => 'cricket',
                    'title' => 'CRICKET DETAILS',
                    'fields' => [
                        $this->field('playing_role', 'dropdown', 'Playing Role', true, [
                            'help' => 'Select up to 2 roles — Batter, Bowler, All-rounder, or Wicketkeeper.',
                        ]),
                        $this->field('batting_style', 'dropdown', 'Batting Style', false, [
                            'options' => ['Right Hand', 'Left Hand'],
                        ]),
                        $this->field('bowling_style', 'dropdown', 'Bowling Style', false, [
                            'options' => ['Right Arm Fast', 'Right Arm Medium', 'Right Arm Spin', 'Left Arm Fast', 'Left Arm Medium', 'Left Arm Spin'],
                        ]),
                        $this->field('experience', 'text', 'Experience', false),
                        $this->field('previous_teams', 'long_text', 'Previous Teams', false),
                    ],
                ],
                [
                    'id' => 'statistics',
                    'title' => 'STATISTICS',
                    'fields' => [
                        $this->field('matches', 'number', 'Matches', false),
                        $this->field('runs', 'number', 'Runs', false),
                        $this->field('highest_score', 'number', 'Highest Score', false),
                        $this->field('wickets', 'number', 'Wickets', false),
                        $this->field('average', 'text', 'Average', false, ['computed' => true]),
                        $this->field('strike_rate', 'text', 'Strike Rate', false, ['computed' => true]),
                        $this->field('economy', 'text', 'Economy', false, ['computed' => true]),
                        $this->field('best_bowling', 'text', 'Best Bowling', false, ['computed' => true]),
                    ],
                ],
                [
                    'id' => 'auction_request',
                    'title' => 'AUCTION REQUEST',
                    'fields' => [
                        $this->field('requested_base_price', 'number', 'Requested Base Price (₹)', false, [
                            'help' => 'Your preferred base price — final auction price is set by the organizer.',
                        ]),
                    ],
                ],
                [
                    'id' => 'documents',
                    'title' => 'DOCUMENTS',
                    'fields' => [
                        $this->field('player_id_doc', 'file', 'Player ID', false),
                        $this->field('age_proof', 'file', 'Age Proof', false),
                    ],
                ],
            ],
        ];
    }

    public function field(
        string $id,
        string $type,
        string $label,
        bool $required = false,
        array $extra = []
    ): array {
        return array_merge([
            'id' => $id,
            'type' => $type,
            'label' => $label,
            'required' => $required,
            'visible' => true,
            'placeholder' => '',
            'help' => '',
            'options' => [],
        ], $extra);
    }

    public function getOrCreateForTournament(Tournament $tournament): RegistrationForm
    {
        $query = RegistrationForm::query()->where('tournament_id', $tournament->id);
        if (Schema::hasColumn('registration_forms', 'auction_id')) {
            $query->whereNull('auction_id');
        }

        $template = $query->first();
        if ($template) {
            return $template;
        }

        return $this->createBlankForm(
            $tournament,
            $tournament->name.' Player Registration',
            'draft'
        );
    }

    public function createForAuction(
        Tournament $tournament,
        string $name,
        ?RegistrationForm $source = null
    ): RegistrationForm {
        $source ??= $this->schemaSourceForTournament($tournament);

        $form = $this->createBlankForm($tournament, $name, 'open', $source);

        return $form;
    }

    public function schemaSourceForTournament(Tournament $tournament): ?RegistrationForm
    {
        $query = RegistrationForm::query()->where('tournament_id', $tournament->id);
        if (Schema::hasColumn('registration_forms', 'auction_id')) {
            $template = (clone $query)->whereNull('auction_id')->latest('id')->first();
            if ($template) {
                return $template;
            }
        }

        return $query->latest('id')->first();
    }

    /**
     * Bind each auction to its own registration form. Shared forms are cloned
     * so players registered on one auction cannot appear on another.
     */
    public function assignExclusiveAuctionForms(): void
    {
        if (! Schema::hasColumn('registration_forms', 'auction_id')) {
            return;
        }

        $grouped = Auction::query()
            ->with(['registrationForm.versions', 'players.registration.files'])
            ->orderBy('id')
            ->get()
            ->groupBy('registration_form_id');

        foreach ($grouped as $formId => $auctions) {
            $form = RegistrationForm::with('versions')->find($formId);
            if (! $form) {
                continue;
            }

            $keep = $auctions->first();
            RegistrationForm::where('auction_id', $keep->id)
                ->where('id', '!=', $form->id)
                ->update(['auction_id' => null]);
            if (! $form->auction_id) {
                $form->update(['auction_id' => $keep->id]);
            }

            foreach ($auctions->skip(1) as $auction) {
                DB::transaction(function () use ($form, $auction) {
                    $clone = $this->createForAuction(
                        $auction->tournament,
                        $auction->name.' Player Registration',
                        $form
                    );
                    RegistrationForm::where('auction_id', $auction->id)
                        ->where('id', '!=', $clone->id)
                        ->update(['auction_id' => null]);
                    $clone->update([
                        'auction_id' => $auction->id,
                        'status' => $form->status,
                        'deadline_at' => $form->deadline_at,
                    ]);
                    $auction->update(['registration_form_id' => $clone->id]);

                    $registrations = app(PlayerRegistrationService::class);
                    foreach ($auction->players as $player) {
                        $source = $player->registration;
                        if (! $source || $source->registration_form_id === $clone->id) {
                            continue;
                        }
                        $copy = $registrations->cloneOntoForm($source, $clone);
                        $player->update(['player_registration_id' => $copy->id]);
                    }
                });
            }
        }
    }

    protected function createBlankForm(
        Tournament $tournament,
        string $name,
        string $status,
        ?RegistrationForm $source = null
    ): RegistrationForm {
        $payload = [
            'tournament_id' => $tournament->id,
            'name' => $name,
            'status' => $status,
            'deadline_at' => $source?->deadline_at,
            'public_token' => Str::random(48),
            'current_version' => $source?->current_version ?: 1,
        ];
        if (Schema::hasColumn('registration_forms', 'auction_id')) {
            $payload['auction_id'] = null;
        }

        $form = RegistrationForm::create($payload);

        if ($source) {
            $versions = $source->relationLoaded('versions')
                ? $source->versions
                : $source->versions()->get();
            if ($versions->isEmpty()) {
                RegistrationFormVersion::create([
                    'registration_form_id' => $form->id,
                    'version' => 1,
                    'schema' => $this->defaultSchema(),
                    'published_at' => now(),
                ]);
                $form->update(['current_version' => 1]);
            } else {
                foreach ($versions as $version) {
                    RegistrationFormVersion::create([
                        'registration_form_id' => $form->id,
                        'version' => $version->version,
                        'schema' => $version->schema,
                        'published_at' => $version->published_at,
                    ]);
                }
            }
        } else {
            RegistrationFormVersion::create([
                'registration_form_id' => $form->id,
                'version' => 1,
                'schema' => $this->defaultSchema(),
                'published_at' => now(),
            ]);
        }

        return $form;
    }

    public function publishNewVersion(RegistrationForm $form, array $schema): RegistrationFormVersion
    {
        $next = (int) $form->current_version + 1;

        $version = RegistrationFormVersion::create([
            'registration_form_id' => $form->id,
            'version' => $next,
            'schema' => $schema,
            'published_at' => now(),
        ]);

        $form->update(['current_version' => $next]);

        return $version;
    }

    public function updateCurrentSchema(RegistrationForm $form, array $schema): RegistrationFormVersion
    {
        $hasSubmissions = $form->registrations()->exists();
        if ($hasSubmissions) {
            return $this->publishNewVersion($form, $schema);
        }

        $version = $form->currentVersionModel();
        if (! $version) {
            return RegistrationFormVersion::create([
                'registration_form_id' => $form->id,
                'version' => 1,
                'schema' => $schema,
                'published_at' => now(),
            ]);
        }

        $version->update(['schema' => $schema]);

        return $version->fresh();
    }

    public function schemaForVersion(RegistrationForm $form, int $version): ?array
    {
        $v = $form->versions()->where('version', $version)->first();

        return $v?->schema;
    }

    public function flattenFields(array $schema): array
    {
        $fields = [];
        foreach ($schema['sections'] ?? [] as $section) {
            foreach ($section['fields'] ?? [] as $field) {
                if (($field['visible'] ?? true) !== false) {
                    $fields[] = $field;
                }
            }
        }

        return $fields;
    }
}
