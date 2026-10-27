<?php

declare(strict_types=1);

namespace App\Http\Controllers\Install;

use App\Http\Controllers\Controller;
use App\Support\Install\Installer;
use App\Support\Install\InstallException;
use App\Support\Install\InstallState;
use App\Support\Install\Requirements;
use App\Support\Languages\LanguagePacks;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpFoundation\Response;

/**
 * Web installer: a single page with the requirements and the configuration form.
 * It works without a session (there is no app key yet): errors are shown in the same response.
 */
class InstallController extends Controller
{
    public function __construct(
        private readonly InstallState $state,
        private readonly Requirements $requirements,
        private readonly LanguagePacks $packs,
    ) {}

    public function show(Request $request): View
    {
        abort_if($this->state->isInstalled(), 404);

        return $this->form($request);
    }

    public function store(Request $request, Installer $installer): View|RedirectResponse|Response
    {
        abort_if($this->state->isInstalled(), 404);

        $this->useLocale($request);

        if (! $this->requirements->passes()) {
            return response($this->form($request), 422);
        }

        $validator = Validator::make($request->all(), [
            'url' => ['required', 'url:http,https', 'max:255'],
            'owner' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(10)],
            'admin_path' => ['required', 'regex:/^[a-z0-9][a-z0-9-]{1,40}$/', Rule::notIn(['install', 'uploads', 'themes', 'core', 'storage', 'livewire', 'filament'])],
            'default_language' => ['required', Rule::in($this->packs->available())],
            'languages' => ['array'],
            'languages.*' => [Rule::in($this->packs->available())],
            'db_host' => ['required', 'string', 'max:255'],
            'db_port' => ['required', 'integer', 'between:1,65535'],
            'db_database' => ['required', 'string', 'max:64'],
            'db_username' => ['required', 'string', 'max:255'],
            'db_password' => ['nullable', 'string', 'max:255'],
        ], [], trans('install.fields'));

        if ($validator->fails()) {
            return response($this->form($request, $validator->errors()), 422);
        }

        /** @var array<string, mixed> $valid */
        $valid = $validator->validated();

        try {
            $existing = $installer->install([
                'url' => (string) $valid['url'],
                'owner' => (string) $valid['owner'],
                'email' => (string) $valid['email'],
                'password' => (string) $valid['password'],
                'admin_path' => (string) $valid['admin_path'],
                'admin_locale' => app()->getLocale(),
                'default_language' => (string) $valid['default_language'],
                'languages' => array_values(array_map('strval', (array) ($valid['languages'] ?? []))),
                'db_host' => (string) $valid['db_host'],
                'db_port' => (int) $valid['db_port'],
                'db_database' => (string) $valid['db_database'],
                'db_username' => (string) $valid['db_username'],
                'db_password' => (string) ($valid['db_password'] ?? ''),
                'force_https' => $request->boolean('force_https'),
                'strip_www' => $request->boolean('strip_www'),
                'demo' => $request->boolean('demo'),
            ]);
        } catch (InstallException $exception) {
            return response($this->form($request, new MessageBag(['install' => $exception->getMessage()])), 422);
        }

        return view('install.done', [
            'existing' => $existing,
            'adminUrl' => rtrim((string) $valid['url'], '/').'/'.$valid['admin_path'],
            'siteUrl' => rtrim((string) $valid['url'], '/'),
        ]);
    }

    private function form(Request $request, ?MessageBag $errors = null): View
    {
        $this->useLocale($request);

        return view('install.form', [
            'checks' => $this->requirements->checks(),
            'requirementsOk' => $this->requirements->passes(),
            'languages' => $this->packs->options(),
            'errors' => $errors ?? new MessageBag,
            'old' => $request->except(['password', 'password_confirmation', 'db_password']),
            'defaults' => [
                'url' => $request->getSchemeAndHttpHost().$request->getBaseUrl(),
                'default_language' => $request->getPreferredLanguage(['it-IT', 'en-GB', 'en-US', 'de-DE', 'fr-FR', 'es-ES', 'pt-PT', 'pt-BR', 'nl-NL']) ?? 'it-IT',
                'force_https' => $request->isSecure(),
                'strip_www' => str_starts_with($request->getHost(), 'www.') === false,
            ],
        ]);
    }

    /**
     * Installer language (Italian or English) from the browser; it also becomes the admin language.
     */
    private function useLocale(Request $request): void
    {
        app()->setLocale($request->getPreferredLanguage(['it', 'en']) === 'it' ? 'it' : 'en');
    }
}
