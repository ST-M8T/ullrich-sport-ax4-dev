<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Settings;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validiert die Eingaben des konsolidierten DHL Freight Settings-Formulars.
 *
 * Reine Eingabe-Validierung am Rand (Engineering-Handbuch §15, §22). Fachliche
 * Invarianten (z.B. URL-Format, ISO-3166-Country-Code) werden zusaetzlich vom
 * DhlConfiguration-Aggregate erzwungen — Defense in Depth.
 *
 * "nullable: nicht ändern wenn leer"-Logik fuer Secrets liegt im Controller
 * (read-modify-write), nicht hier — dieses Form Request weiss nichts ueber
 * den existierenden Konfigurationszustand.
 */
final class DhlFreightSettingsUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && $this->user()->can('settings.dhl_freight.manage');
    }

    /**
     * @return array<string,array<int,string>>
     */
    public function rules(): array
    {
        return [
            'auth_base_url' => ['required', 'url', 'max:200'],
            'auth_client_id' => ['required', 'string', 'max:100'],
            'auth_client_secret' => ['nullable', 'string', 'max:200'],

            'freight_base_url' => ['required', 'url', 'max:200'],
            'freight_api_key' => ['required', 'string', 'max:200'],
            'freight_api_secret' => ['nullable', 'string', 'max:200'],

            'default_account_number' => ['nullable', 'string', 'max:15'],

            'tracking_api_key' => ['nullable', 'string', 'max:200'],
            'tracking_default_service' => ['nullable', 'string', 'max:50'],
            'tracking_origin_country_code' => ['nullable', 'string', 'size:2', 'alpha'],
            'tracking_requester_country_code' => ['nullable', 'string', 'size:2', 'alpha'],

            'timeout_seconds' => ['required', 'integer', 'between:1,120'],
            'verify_ssl' => ['required', 'boolean'],

            'push_base_url' => ['nullable', 'url', 'max:200'],
            'push_api_key' => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * @return array<string,string>
     */
    public function messages(): array
    {
        return [
            'auth_base_url.required' => 'Bitte die Auth-Basis-URL angeben.',
            'auth_base_url.url' => 'Auth-Basis-URL muss eine gueltige URL sein (https://...).',
            'auth_base_url.max' => 'Auth-Basis-URL darf maximal 200 Zeichen lang sein.',
            'auth_client_id.required' => 'Bitte die Auth Client-ID angeben.',
            'auth_client_id.max' => 'Auth Client-ID darf maximal 100 Zeichen lang sein.',
            'auth_client_secret.max' => 'Auth Client-Secret darf maximal 200 Zeichen lang sein.',

            'freight_base_url.required' => 'Bitte die Freight-Basis-URL angeben.',
            'freight_base_url.url' => 'Freight-Basis-URL muss eine gueltige URL sein (https://...).',
            'freight_base_url.max' => 'Freight-Basis-URL darf maximal 200 Zeichen lang sein.',
            'freight_api_key.required' => 'Bitte den Freight API-Key angeben.',
            'freight_api_key.max' => 'Freight API-Key darf maximal 200 Zeichen lang sein.',
            'freight_api_secret.max' => 'Freight API-Secret darf maximal 200 Zeichen lang sein.',

            'default_account_number.max' => 'Standard-Account-Number darf maximal 15 Zeichen lang sein.',

            'tracking_api_key.max' => 'Tracking-API-Key darf maximal 200 Zeichen lang sein.',
            'tracking_default_service.max' => 'Tracking-Default-Service darf maximal 50 Zeichen lang sein.',
            'tracking_origin_country_code.size' => 'Tracking-Origin-Country muss genau 2 Zeichen lang sein (ISO-3166 Alpha-2).',
            'tracking_origin_country_code.alpha' => 'Tracking-Origin-Country darf nur Buchstaben enthalten.',
            'tracking_requester_country_code.size' => 'Tracking-Requester-Country muss genau 2 Zeichen lang sein (ISO-3166 Alpha-2).',
            'tracking_requester_country_code.alpha' => 'Tracking-Requester-Country darf nur Buchstaben enthalten.',

            'timeout_seconds.required' => 'Bitte ein Timeout (Sekunden) angeben.',
            'timeout_seconds.integer' => 'Timeout muss eine Ganzzahl sein.',
            'timeout_seconds.between' => 'Timeout muss zwischen 1 und 120 Sekunden liegen.',
            'verify_ssl.required' => 'Bitte angeben, ob SSL geprueft werden soll.',
            'verify_ssl.boolean' => 'SSL-Pruefung muss true oder false sein.',

            'push_base_url.url' => 'Push-Basis-URL muss eine gueltige URL sein (https://...).',
            'push_base_url.max' => 'Push-Basis-URL darf maximal 200 Zeichen lang sein.',
            'push_api_key.max' => 'Push-API-Key darf maximal 200 Zeichen lang sein.',
        ];
    }

    /**
     * Normalisiert leere Strings zu null und mappt 'verify_ssl' auf bool, damit
     * die boolean-Rule auch fuer Form-Inputs ('1'/'0', 'true'/'false') greift.
     */
    protected function prepareForValidation(): void
    {
        $payload = [];

        if ($this->has('verify_ssl')) {
            $payload['verify_ssl'] = $this->boolean('verify_ssl');
        }

        if ($this->has('tracking_origin_country_code')) {
            $value = $this->input('tracking_origin_country_code');
            $payload['tracking_origin_country_code'] = is_string($value) && trim($value) !== ''
                ? strtoupper(trim($value))
                : null;
        }

        if ($this->has('tracking_requester_country_code')) {
            $value = $this->input('tracking_requester_country_code');
            $payload['tracking_requester_country_code'] = is_string($value) && trim($value) !== ''
                ? strtoupper(trim($value))
                : null;
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }
}
