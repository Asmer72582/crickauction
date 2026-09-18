<?php

namespace App\Services;

use App\Models\PlayerRegistration;
use App\Models\RegistrationFile;
use App\Models\RegistrationForm;
use App\Models\RegistrationFormVersion;
use App\Support\CricketStatsCalculator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PlayerRegistrationService
{
    public function __construct(
        protected RegistrationFormService $forms
    ) {}

    public function generateCode(RegistrationForm $form): string
    {
        $prefix = strtoupper(Str::limit(Str::slug($form->tournament?->name ?? 'player', ''), 2, ''));
        if (strlen($prefix) < 2) {
            $prefix = 'PL';
        }
        $year = now()->format('Y');
        $scope = $form->auction_id ?: $form->id;

        for ($i = 0; $i < 30; $i++) {
            $seq = PlayerRegistration::where('registration_form_id', $form->id)->count() + 1 + $i;
            $code = sprintf('%s%s-%s-%05d', $prefix, $scope, $year, $seq);
            if (strlen($code) > 32) {
                $code = sprintf('%s-%s-%s', $prefix, $year, Str::upper(Str::random(6)));
            }
            if (! PlayerRegistration::where('registration_code', $code)->exists()) {
                return $code;
            }
        }

        return sprintf('%s-%s-%s', $prefix, $year, Str::upper(Str::random(8)));
    }

    public function cloneOntoForm(PlayerRegistration $source, RegistrationForm $form): PlayerRegistration
    {
        $copy = $source->replicate();
        $copy->registration_form_id = $form->id;
        $copy->registration_code = $this->generateCode($form);
        $copy->save();

        foreach ($source->files as $file) {
            $copy->files()->create([
                'field_key' => $file->field_key,
                'disk_path' => $file->disk_path,
                'original_name' => $file->original_name,
                'mime' => $file->mime,
            ]);
        }

        return $copy;
    }

    public function submit(
        RegistrationForm $form,
        array $data,
        array $files = []
    ): PlayerRegistration {
        if (! $form->isOpen()) {
            throw ValidationException::withMessages(['form' => 'Registration is closed.']);
        }

        $version = $form->current_version;
        $schema = $this->forms->schemaForVersion($form, $version) ?? $this->forms->defaultSchema();
        $data = $this->applyComputedStats($data);

        $this->validateAgainstSchema($schema, $data, $files);

        $normalized = $this->normalizeData($schema, $data);

        $registration = PlayerRegistration::create([
            'registration_form_id' => $form->id,
            'form_version' => $version,
            'registration_code' => $this->generateCode($form),
            'data' => $normalized,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        foreach ($files as $fieldKey => $file) {
            if ($file instanceof UploadedFile) {
                $this->storeFile($registration, $fieldKey, $file);
            }
        }

        return $registration;
    }

    protected function validateAgainstSchema(array $schema, array $data, array $files): void
    {
        $errors = [];
        foreach ($this->forms->flattenFields($schema) as $field) {
            $id = $field['id'];
            $type = $field['type'];
            $required = (bool) ($field['required'] ?? false);
            $hasFile = isset($files[$id]) && $files[$id] instanceof UploadedFile;
            $value = $data[$id] ?? null;

            if ($id === 'playing_role') {
                $roles = is_array($value) ? array_values(array_filter($value)) : ($value ? [$value] : []);
                if ($required && count($roles) < 1) {
                    $errors[$id] = 'Select at least one playing role.';
                }
                if (count($roles) > 2) {
                    $errors[$id] = 'You can select maximum 2 playing roles.';
                }
                $allowed = ['Batter', 'Bowler', 'All-rounder', 'Wicketkeeper'];
                foreach ($roles as $r) {
                    if (! in_array($r, $allowed, true)) {
                        $errors[$id] = 'Invalid playing role selected.';
                        break;
                    }
                }
                continue;
            }

            if ($required && in_array($type, ['image', 'file'], true) && ! $hasFile) {
                $errors[$id] = ($field['label'] ?? $id).' is required.';
                continue;
            }
            if ($required && ! in_array($type, ['image', 'file'], true) && ($value === null || $value === '')) {
                $errors[$id] = ($field['label'] ?? $id).' is required.';
            }
            if ($type === 'email' && $value && ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[$id] = 'Invalid email address.';
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function applyComputedStats(array $data): array
    {
        $computed = CricketStatsCalculator::compute([
            'matches' => $data['matches'] ?? null,
            'runs' => $data['runs'] ?? null,
            'highest_score' => $data['highest_score'] ?? null,
            'wickets' => $data['wickets'] ?? null,
        ]);

        foreach ($computed as $key => $value) {
            if ($value !== null) {
                $data[$key] = $value;
            }
        }

        return $data;
    }

    protected function normalizeData(array $schema, array $data): array
    {
        $out = [];
        foreach ($this->forms->flattenFields($schema) as $field) {
            $id = $field['id'];
            if (! array_key_exists($id, $data) && ! CricketStatsCalculator::isComputedField($id)) {
                continue;
            }
            $val = $data[$id] ?? null;
            if (is_array($val)) {
                $val = implode(', ', $val);
            }
            if ($val !== null && $val !== '') {
                $out[$id] = $val;
            }
        }

        return $out;
    }

    public function attachProfilePhoto(PlayerRegistration $registration, UploadedFile $file): RegistrationFile
    {
        return $this->storeFile($registration, 'profile_photo', $file);
    }

    protected function storeFile(PlayerRegistration $registration, string $fieldKey, UploadedFile $file): RegistrationFile
    {
        $path = $file->store('registrations/'.$registration->id, 'public');

        return RegistrationFile::create([
            'player_registration_id' => $registration->id,
            'field_key' => $fieldKey,
            'disk_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
        ]);
    }

    public function approve(PlayerRegistration $registration, ?string $notes = null): PlayerRegistration
    {
        $registration->update([
            'status' => 'approved',
            'review_notes' => $notes,
            'reviewed_at' => now(),
        ]);

        return $registration->fresh();
    }

    public function reject(PlayerRegistration $registration, ?string $notes = null): PlayerRegistration
    {
        $registration->update([
            'status' => 'rejected',
            'review_notes' => $notes,
            'reviewed_at' => now(),
        ]);

        return $registration->fresh();
    }

    public function requestChanges(PlayerRegistration $registration, string $notes): PlayerRegistration
    {
        $registration->update([
            'status' => 'changes_requested',
            'review_notes' => $notes,
            'reviewed_at' => now(),
        ]);

        return $registration->fresh();
    }

    public function profileSections(PlayerRegistration $registration): array
    {
        $schema = $this->forms->schemaForVersion(
            $registration->form,
            $registration->form_version
        ) ?? $this->forms->defaultSchema();

        $skipFields = ['wicketkeeper', 'profile_photo', 'playing_role', 'batting_style', 'bowling_style'];
        $statsFields = ['matches', 'runs', 'highest_score', 'wickets', 'average', 'strike_rate', 'economy', 'best_bowling'];

        $sections = [];
        foreach ($schema['sections'] ?? [] as $section) {
            $sectionId = $section['id'] ?? '';

            if ($sectionId === 'statistics') {
                continue;
            }

            $rows = [];
            foreach ($section['fields'] ?? [] as $field) {
                $id = $field['id'];
                if (in_array($id, $skipFields, true) || in_array($id, $statsFields, true)) {
                    continue;
                }
                if (($field['visible'] ?? true) === false) {
                    continue;
                }

                $type = $field['type'];
                if (in_array($type, ['image', 'file'], true)) {
                    $file = $registration->files()->where('field_key', $id)->first();
                    $rows[] = [
                        'label' => $field['label'],
                        'value' => $file ? $file->original_name : '—',
                        'file_url' => $file ? '/storage/'.$file->disk_path : null,
                        'type' => $type,
                    ];
                } else {
                    $value = $registration->data[$id] ?? '—';
                    $rows[] = [
                        'label' => $field['label'],
                        'value' => $value === '' ? '—' : $value,
                        'type' => $type,
                    ];
                }
            }

            if ($rows) {
                $sections[] = [
                    'type' => 'fields',
                    'title' => $section['title'] ?? 'Details',
                    'rows' => $rows,
                ];
            }
        }

        return $sections;
    }
}
