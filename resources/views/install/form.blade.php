@extends('install.layout')

@section('title', __('install.title'))

@php
    $value = fn (string $key, mixed $default = null) => $old[$key] ?? $defaults[$key] ?? $default;
    $error = fn (string $key) => $errors->first($key);
    $selectedLanguages = (array) ($old['languages'] ?? ['en-GB']);
@endphp

@section('content')
    <p>{{ __('install.intro') }}</p>

    @if ($errors->isNotEmpty())
        <div class="alert" role="alert">
            <strong>{{ __('install.errors.title') }}</strong>
            <ul>
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="card">
        <h2>{{ __('install.sections.requirements') }}</h2>
        <table>
            @foreach ($checks as $check)
                <tr>
                    <td>{{ $check['label'] }}</td>
                    <td class="{{ $check['ok'] ? 'ok' : 'ko' }}">{{ $check['ok'] ? '✓' : '✗' }}</td>
                    <td>{{ $check['detail'] }}</td>
                </tr>
            @endforeach
        </table>
        @unless ($requirementsOk)
            <p class="field-error">{{ __('install.requirements_failed') }}</p>
        @endunless
    </section>

    <form method="post" action="{{ url('install') }}" novalidate>
        <section class="card">
            <h2>{{ __('install.sections.site') }}</h2>
            <label for="url">{{ __('install.fields.url') }}</label>
            <input type="url" id="url" name="url" value="{{ $value('url') }}" required>
            <p class="muted">{{ __('install.help.url') }}</p>
            @if ($error('url'))<p class="field-error">{{ $error('url') }}</p>@endif

            <label for="owner">{{ __('install.fields.owner') }}</label>
            <input type="text" id="owner" name="owner" value="{{ $value('owner') }}" required autocomplete="name">
            @if ($error('owner'))<p class="field-error">{{ $error('owner') }}</p>@endif

            <label for="default_language">{{ __('install.fields.default_language') }}</label>
            <select id="default_language" name="default_language">
                @foreach ($languages as $code => $name)
                    <option value="{{ $code }}" @selected($value('default_language') === $code)>{{ $name }}</option>
                @endforeach
            </select>

            <fieldset class="card languages-box">
                <legend class="muted">{{ __('install.fields.languages') }}</legend>
                <div class="langs">
                    @foreach ($languages as $code => $name)
                        <label class="check"><input type="checkbox" name="languages[]" value="{{ $code }}" @checked(in_array($code, $selectedLanguages, true))> {{ $name }}</label>
                    @endforeach
                </div>
            </fieldset>

            <label class="check"><input type="checkbox" name="demo" value="1" @checked($value('demo'))> {{ __('install.fields.demo') }}</label>
        </section>

        <section class="card">
            <h2>{{ __('install.sections.database') }}</h2>
            <p class="muted">{{ __('install.help.database') }}</p>
            <div class="grid">
                <div>
                    <label for="db_host">{{ __('install.fields.db_host') }}</label>
                    <input type="text" id="db_host" name="db_host" value="{{ $value('db_host', 'localhost') }}" required>
                </div>
                <div>
                    <label for="db_port">{{ __('install.fields.db_port') }}</label>
                    <input type="number" id="db_port" name="db_port" value="{{ $value('db_port', 3306) }}" required>
                </div>
            </div>
            <label for="db_database">{{ __('install.fields.db_database') }}</label>
            <input type="text" id="db_database" name="db_database" value="{{ $value('db_database') }}" required>
            @if ($error('db_database'))<p class="field-error">{{ $error('db_database') }}</p>@endif
            <div class="grid">
                <div>
                    <label for="db_username">{{ __('install.fields.db_username') }}</label>
                    <input type="text" id="db_username" name="db_username" value="{{ $value('db_username') }}" required autocomplete="off">
                </div>
                <div>
                    <label for="db_password">{{ __('install.fields.db_password') }}</label>
                    <input type="password" id="db_password" name="db_password" autocomplete="off">
                </div>
            </div>
        </section>

        <section class="card">
            <h2>{{ __('install.sections.admin') }}</h2>
            <label for="email">{{ __('install.fields.email') }}</label>
            <input type="email" id="email" name="email" value="{{ $value('email') }}" required autocomplete="username">
            @if ($error('email'))<p class="field-error">{{ $error('email') }}</p>@endif
            <div class="grid">
                <div>
                    <label for="password">{{ __('install.fields.password') }}</label>
                    <input type="password" id="password" name="password" required autocomplete="new-password">
                </div>
                <div>
                    <label for="password_confirmation">{{ __('install.fields.password_confirmation') }}</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
                </div>
            </div>
            @if ($error('password'))<p class="field-error">{{ $error('password') }}</p>@endif
            <label for="admin_path">{{ __('install.fields.admin_path') }}</label>
            <input type="text" id="admin_path" name="admin_path" value="{{ $value('admin_path', 'admin') }}" required>
            <p class="muted">{{ __('install.help.admin_path') }}</p>
            @if ($error('admin_path'))<p class="field-error">{{ $error('admin_path') }}</p>@endif
        </section>

        <section class="card">
            <h2>{{ __('install.sections.addresses') }}</h2>
            <label class="check"><input type="checkbox" name="force_https" value="1" @checked($value('force_https'))> {{ __('install.fields.force_https') }}</label>
            <label class="check"><input type="checkbox" name="strip_www" value="1" @checked($value('strip_www'))> {{ __('install.fields.strip_www') }}</label>
            <p class="muted">{{ __('install.help.addresses') }}</p>
        </section>

        <button type="submit" @disabled(! $requirementsOk)>{{ __('install.submit') }}</button>
    </form>
@endsection
