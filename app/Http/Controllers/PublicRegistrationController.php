<?php

namespace App\Http\Controllers;

use App\Models\RegistrationForm;
use App\Services\PlayerRegistrationService;
use App\Services\RegistrationFormService;
use Illuminate\Http\Request;

class PublicRegistrationController extends Controller
{
    public function __construct(
        protected RegistrationFormService $forms,
        protected PlayerRegistrationService $registrations
    ) {}

    public function show(string $token)
    {
        $form = RegistrationForm::where('public_token', $token)->with('auction')->firstOrFail();
        $schema = $form->currentVersionModel()?->schema ?? $this->forms->defaultSchema();

        return view('auction.public-registration', [
            'form' => $form,
            'schema' => $schema,
            'open' => $form->isOpen(),
        ]);
    }

    public function submit(Request $request, string $token)
    {
        $form = RegistrationForm::where('public_token', $token)->with('auction')->firstOrFail();

        $fields = $this->forms->flattenFields(
            $form->currentVersionModel()?->schema ?? $this->forms->defaultSchema()
        );

        $data = [];
        $files = [];
        foreach ($fields as $field) {
            $id = $field['id'];
            $type = $field['type'];
            if (in_array($type, ['image', 'file'], true)) {
                if ($request->hasFile($id)) {
                    $files[$id] = $request->file($id);
                }
            } elseif ($type === 'checkbox') {
                $data[$id] = $request->boolean($id) ? 'Yes' : 'No';
            } elseif ($id === 'playing_role') {
                $data[$id] = $request->input('playing_role', []);
            } else {
                $data[$id] = $request->input($id);
            }
        }

        if (! $request->boolean('terms')) {
            return back()->withErrors(['terms' => 'You must accept the terms.'])->withInput();
        }

        $registration = $this->registrations->submit($form, $data, $files);

        return redirect()->route('registration.thanks', $registration->registration_code)
            ->with('registration_code', $registration->registration_code);
    }

    public function thanks(string $code)
    {
        return view('auction.registration-thanks', ['code' => $code]);
    }
}
