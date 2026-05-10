<?php

declare(strict_types=1);

namespace Tests\Feature\Forms;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

/**
 * Sichert die ARIA-Attribute der Form-Komponenten ab (a11y-Audit Wave 6).
 *
 * Prüft:
 * - Labels sind via for=/id= an Eingabefelder gebunden.
 * - Required-Felder erhalten aria-required="true".
 * - Bei Validation-Errors werden aria-invalid="true" und
 *   aria-describedby="<id>-error" gesetzt.
 * - Tabs nutzen aria-current statt aria-pressed.
 */
final class FormAccessibilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Blade-Komponenten erwarten einen $errors-View-Share.
        view()->share('errors', new ViewErrorBag);
    }

    public function test_input_renders_label_for_and_input_id(): void
    {
        $html = Blade::render(
            '<x-forms.input name="subject" label="Betreff" />'
        );

        $this->assertStringContainsString('for="subject"', $html);
        $this->assertStringContainsString('id="subject"', $html);
    }

    public function test_input_with_id_suffix_produces_unique_ids(): void
    {
        $html = Blade::render(
            '<x-forms.input name="username" label="Username" id-suffix="edit_42" />'
        );

        $this->assertStringContainsString('for="username_edit_42"', $html);
        $this->assertStringContainsString('id="username_edit_42"', $html);
    }

    public function test_required_input_marks_aria_required(): void
    {
        $html = Blade::render(
            '<x-forms.input name="email" label="E-Mail" :required="true" />'
        );

        $this->assertStringContainsString('aria-required="true"', $html);
    }

    public function test_input_with_error_renders_aria_invalid_and_described_by(): void
    {
        $errorBag = new ViewErrorBag;
        $errorBag->put('default', new MessageBag([
            'email' => ['Ungültige E-Mail-Adresse.'],
        ]));
        view()->share('errors', $errorBag);

        $html = Blade::render(
            '<x-forms.input name="email" label="E-Mail" />'
        );

        $this->assertStringContainsString('aria-invalid="true"', $html);
        $this->assertStringContainsString('aria-describedby="email-error"', $html);
        $this->assertStringContainsString('id="email-error"', $html);
    }

    public function test_select_binds_label_via_for(): void
    {
        $html = Blade::render(
            '<x-forms.select name="role" label="Rolle" :options="$opts" />',
            ['opts' => ['admin' => 'Admin', 'viewer' => 'Viewer']]
        );

        $this->assertStringContainsString('for="role"', $html);
        $this->assertStringContainsString('id="role"', $html);
    }

    public function test_textarea_binds_label_via_for(): void
    {
        $html = Blade::render(
            '<x-forms.textarea name="body_html" label="Inhalt" />'
        );

        $this->assertStringContainsString('for="body_html"', $html);
        $this->assertStringContainsString('id="body_html"', $html);
    }

    public function test_checkbox_renders_switch_role_when_switch_prop_set(): void
    {
        $html = Blade::render(
            '<x-forms.checkbox name="is_active" label="Aktiv" :switch="true" />'
        );

        $this->assertStringContainsString('for="is_active"', $html);
        $this->assertStringContainsString('id="is_active"', $html);
        $this->assertStringContainsString('role="switch"', $html);
        $this->assertStringContainsString('form-check form-switch', $html);
    }

    public function test_tabs_use_aria_current_instead_of_aria_pressed(): void
    {
        // Komponente wird via Blade-Tag gerendert; der TabsComposer berechnet
        // processedTabs aus dem :tabs-Array.
        $html = Blade::render(
            '<x-tabs :tabs="$tabs" active-tab="a" base-url="/foo" />',
            ['tabs' => ['a' => 'Aktiv', 'b' => 'Inaktiv']]
        );

        $this->assertStringNotContainsString('aria-pressed', $html);
        $this->assertStringNotContainsString('role="tab"', $html);
        $this->assertStringNotContainsString('role="tablist"', $html);
        $this->assertStringContainsString('aria-current="page"', $html);
    }
}
