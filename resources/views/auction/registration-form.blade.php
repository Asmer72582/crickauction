<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Form Builder — {{ $tournament->name }}</title>
  <link rel="stylesheet" href="/fonts.css">
  <link rel="stylesheet" href="/dashboard.css">
  <link rel="stylesheet" href="/app-shell.css">
  <link rel="stylesheet" href="/auction.css?v=23">
</head>
<body class="dash-body au-desk">
@php $pageTitle = 'Player Registration Form'; $pageSub = $tournament->name; @endphp
@include('auction.partials.operator-shell', compact('tournament', 'theme', 'pageTitle', 'pageSub'))

      <div class="au-builder-card" id="form-builder"
        data-save-url="{{ route('tournaments.registration-form.update', $tournament) }}"
        data-preview-url="{{ route('tournaments.registration-form.preview', $tournament) }}">
        <div class="au-builder-toolbar">
          <label class="au-field-label">Form name
            <input type="text" id="form-name" value="{{ $form->name }}" />
          </label>
          <label class="au-field-label">Status
            <select id="form-status">
              <option value="draft" @selected($form->status === 'draft')>Draft</option>
              <option value="open" @selected($form->status === 'open')>Open</option>
              <option value="closed" @selected($form->status === 'closed')>Closed</option>
            </select>
          </label>
          <label class="au-field-label">Deadline
            <input type="datetime-local" id="form-deadline" value="{{ optional($form->deadline_at)->format('Y-m-d\TH:i') }}" />
          </label>
          <button type="button" class="au-btn au-btn-primary" id="btn-save-form">Save</button>
        </div>
        <div class="au-versions-bar">
          <strong>Versions</strong>
          @foreach ($versions as $v)
            <span class="au-version-chip">v{{ $v->version }} · {{ $v->published_at?->format('d M Y') }}</span>
          @endforeach
          <span class="au-muted">New version auto-created after players submit.</span>
        </div>
        <div id="sections-root"></div>
        <div class="au-builder-actions">
          <button type="button" class="au-btn au-btn-secondary au-btn-sm" id="btn-add-section">+ Section</button>
          <button type="button" class="au-btn au-btn-ghost au-btn-sm" id="btn-add-field">+ Field</button>
          <a class="au-btn au-btn-ghost au-btn-sm" href="{{ route('tournaments.registration-form.preview', $tournament) }}" target="_blank">Preview form</a>
        </div>
      </div>

@include('auction.partials.operator-shell-end')
<script>window.FORM_SCHEMA = @json($schema);</script>
<script src="/auction-form-builder.js"></script>
</body>
</html>
