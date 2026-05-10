<?php

namespace App\Http\Controllers\Configuration;

use App\Application\Configuration\MailTemplateService;
use App\Application\Configuration\Queries\ListMailTemplates;
use App\Domain\Configuration\MailTemplate;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

final class MailTemplateController
{
    public function __construct(
        private readonly ListMailTemplates $listTemplates,
        private readonly MailTemplateService $templateService,
        private readonly Redirector $redirector,
    ) {}

    public function index(): View
    {
        $templates = ($this->listTemplates)();

        return view('configuration.mail-templates.index', [
            'templates' => $templates,
        ]);
    }

    public function create(): View
    {
        return view('configuration.mail-templates.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'template_key' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'body_html' => ['nullable', 'string'],
            'body_text' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($this->findTemplate($data['template_key'])) {
            return $this->redirector
                ->route('configuration-settings', ['tab' => 'settings', 'settings_group' => 'mail'])
                ->with('error', sprintf('Mail-Template "%s" existiert bereits.', $data['template_key']));
        }

        $template = $this->templateService->upsert(
            null,
            $data['template_key'],
            $data['subject'],
            $data['body_html'] ?? null,
            $data['body_text'] ?? null,
            (bool) ($data['is_active'] ?? false),
            $data['description'] ?? null,
            ((int) Auth::id()) ?: null
        );

        return $this->redirector
            ->route('configuration-settings', ['tab' => 'settings', 'settings_group' => 'mail'])
            ->with('success', sprintf('Mail-Template "%s" wurde angelegt.', $template->templateKey()));
    }

    public function edit(string $templateKey): View|RedirectResponse
    {
        $key = trim($templateKey);
        $template = $this->findTemplate($key);

        if (! $template) {
            return $this->redirector
                ->route('configuration-settings', ['tab' => 'settings', 'settings_group' => 'mail'])
                ->with('error', sprintf('Mail-Template "%s" wurde nicht gefunden.', $key));
        }

        return view('configuration.mail-templates.edit', [
            'template' => $template,
        ]);
    }

    public function update(string $templateKey, Request $request): RedirectResponse
    {
        $key = trim($templateKey);
        $template = $this->findTemplate($key);

        if (! $template) {
            return $this->redirector
                ->route('configuration-settings', ['tab' => 'settings', 'settings_group' => 'mail'])
                ->with('error', sprintf('Mail-Template "%s" wurde nicht gefunden.', $key));
        }

        $data = $request->validate([
            'description' => ['nullable', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'body_html' => ['nullable', 'string'],
            'body_text' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $this->templateService->upsert(
            $template->id(),
            $template->templateKey(),
            $data['subject'],
            $data['body_html'] ?? null,
            $data['body_text'] ?? null,
            (bool) ($data['is_active'] ?? false),
            $data['description'] ?? null,
            ((int) Auth::id()) ?: null
        );

        return $this->redirector
            ->route('configuration-settings', ['tab' => 'settings', 'settings_group' => 'mail'])
            ->with('success', sprintf('Mail-Template "%s" wurde aktualisiert.', $template->templateKey()));
    }

    public function destroy(string $templateKey, Request $request): RedirectResponse
    {
        $key = trim($templateKey);
        $template = $this->findTemplate($key);

        if (! $template) {
            return $this->redirector
                ->route('configuration-settings', ['tab' => 'settings', 'settings_group' => 'mail'])
                ->with('error', sprintf('Mail-Template "%s" wurde nicht gefunden.', $key));
        }

        $this->templateService->delete($template->id());

        return $this->redirector
            ->route('configuration-settings', ['tab' => 'settings', 'settings_group' => 'mail'])
            ->with('success', sprintf('Mail-Template "%s" wurde gelöscht.', $template->templateKey()));
    }

    public function preview(string $templateKey): View|RedirectResponse
    {
        $key = trim($templateKey);
        $template = $this->findTemplate($key);

        if (! $template) {
            return $this->redirector
                ->route('configuration-settings', ['tab' => 'settings', 'settings_group' => 'mail'])
                ->with('error', sprintf('Mail-Template "%s" wurde nicht gefunden.', $key));
        }

        return view('configuration.mail-templates.preview', [
            'template' => $template,
        ]);
    }

    private function findTemplate(string $templateKey): ?MailTemplate
    {
        /** @var Collection<int,MailTemplate> $templates */
        $templates = collect(($this->listTemplates)());

        return $templates->first(
            fn (MailTemplate $template) => strcasecmp($template->templateKey(), trim($templateKey)) === 0
        );
    }
}
