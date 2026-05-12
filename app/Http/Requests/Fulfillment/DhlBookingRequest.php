<?php

declare(strict_types=1);

namespace App\Http\Requests\Fulfillment;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validiert eine DHL-Freight-Buchungsanfrage am Rand der Anwendung.
 *
 * Engineering-Handbuch §15 (Validierung): Technische Eingabevalidierung gehoert
 * an den Rand. Fachliche Invarianten (z.B. erlaubte PayerCodes als Enum,
 * Format-Garantien des ProductCodes) werden zusaetzlich von den Value Objects
 * der Domain (DhlPayerCode, DhlProductCode, DhlPackageType) erzwungen — Defense
 * in Depth (§19). Controller bleiben duenn (§22): validieren → Use Case
 * aufrufen → Response mappen.
 *
 * KEIN Default fuer payer_code: Die Wahl des Frachtzahlers (DAP/DDP/EXW/CIP) ist
 * eine bewusste fachliche Entscheidung — UI/API muessen sie zwingend erfassen.
 *
 * Felder rund um pieces/freight_profile_id/sender_profile_id sind aktuell
 * optional, weil der DhlShipmentBookingService Pakete und Sender aus der
 * persistierten ShipmentOrder hydratisiert. Sobald ein Use Case ein Override
 * benoetigt, sind die Strukturregeln hier bereits scharf — kein nachtraeglicher
 * Validierungs-Workaround noetig (YAGNI bleibt gewahrt: nur Struktur, keine
 * Pflichtfelder ohne Konsumenten).
 */
final class DhlBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && $this->user()->can('fulfillment.orders.manage');
    }

    /**
     * @return array<string,array<int,string>>
     */
    public function rules(): array
    {
        // order_id ist Pflicht im API-Pfad (POST /api/admin/dhl/booking).
        // Im Web-Pfad (POST /fulfillment/orders/{order}/dhl/book) liefert
        // die Route den Identifier — dort entfaellt order_id im Body.
        // Heuristik: wenn die Route einen {order}-Parameter hat, ist order_id
        // optional; sonst Pflicht. Das verhindert eine fehlerhafte 422 im
        // Web-Pfad und erzwingt sie im API-Pfad.
        $hasRouteOrder = $this->route('order') !== null;
        $orderIdRules = $hasRouteOrder
            ? ['sometimes', 'required', 'integer', 'min:1', 'exists:shipment_orders,id']
            : ['required', 'integer', 'min:1', 'exists:shipment_orders,id'];

        return [
            'order_id' => $orderIdRules,

            // Pflicht: typed Domain-VOs (siehe DhlProductCode/DhlPayerCode/DhlPackageType).
            'product_code' => ['required', 'string', 'size:3', 'regex:/^[A-Z0-9]{3}$/'],
            'payer_code' => ['required', 'string', 'in:DAP,DDP,EXW,CIP'],
            'default_package_type' => ['required', 'string', 'alpha_num', 'max:4', 'min:1'],

            // BC-Feld: legacy product_id darf optional weiter mitgegeben werden.
            'product_id' => ['nullable', 'string', 'max:64'],

            // Optionale Service-Optionen
            'additional_services' => ['nullable', 'array'],
            'additional_services.*' => ['string', 'max:50'],

            // Optionales Abholdatum (heute oder spaeter)
            'pickup_date' => ['nullable', 'date', 'after_or_equal:today'],

            // Optionale Pieces-Override-Struktur (siehe Klassen-Doc).
            'pieces' => ['sometimes', 'array', 'min:1'],
            'pieces.*.number_of_pieces' => ['required_with:pieces', 'integer', 'min:1', 'max:999'],
            'pieces.*.package_type' => ['nullable', 'string', 'alpha_num', 'max:4'],
            'pieces.*.weight' => ['required_with:pieces', 'numeric', 'min:0.01', 'max:99999'],
            'pieces.*.width' => ['nullable', 'numeric', 'min:1', 'max:999'],
            'pieces.*.height' => ['nullable', 'numeric', 'min:1', 'max:999'],
            'pieces.*.length' => ['nullable', 'numeric', 'min:1', 'max:999'],
            'pieces.*.marks_and_numbers' => ['nullable', 'string', 'max:35'],

            // Optional: Override fuer Profile (sonst aus ShipmentOrder/Settings)
            'freight_profile_id' => ['nullable', 'integer', 'exists:fulfillment_freight_profiles,id'],
            'sender_profile_id' => ['nullable', 'integer', 'exists:fulfillment_sender_profiles,id'],

            // Optionale Web-Redirect-Hilfsangabe (nur fuer den Web-Endpoint)
            'redirect_to' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string,string>
     */
    public function messages(): array
    {
        return [
            'order_id.required' => 'Bitte eine Bestell-ID angeben.',
            'order_id.integer' => 'Bestell-ID muss eine Ganzzahl sein.',
            'order_id.min' => 'Bestell-ID muss mindestens 1 sein.',
            'order_id.exists' => 'Die angegebene Bestellung existiert nicht.',

            'product_code.required' => 'Bitte einen DHL-Produkt-Code angeben.',
            'product_code.size' => 'DHL-Produkt-Code muss genau 3 Zeichen lang sein.',
            'product_code.regex' => 'DHL-Produkt-Code darf nur Grossbuchstaben und Ziffern enthalten (3 Stellen).',

            'payer_code.required' => 'Bitte den Frachtzahler (PayerCode) angeben.',
            'payer_code.in' => 'PayerCode muss DAP, DDP, EXW oder CIP sein.',

            'default_package_type.required' => 'Bitte einen Standard-Pakettyp angeben.',
            'default_package_type.alpha_num' => 'Standard-Pakettyp darf nur Buchstaben und Ziffern enthalten.',
            'default_package_type.max' => 'Standard-Pakettyp darf maximal 4 Zeichen lang sein.',
            'default_package_type.min' => 'Standard-Pakettyp darf nicht leer sein.',

            'product_id.max' => 'Legacy product_id darf maximal 64 Zeichen lang sein.',

            'additional_services.array' => 'Zusatzleistungen muessen als Liste uebergeben werden.',
            'additional_services.*.string' => 'Jede Zusatzleistung muss ein String sein.',
            'additional_services.*.max' => 'Eine Zusatzleistung darf maximal 50 Zeichen lang sein.',

            'pickup_date.date' => 'Abholdatum muss ein gueltiges Datum sein.',
            'pickup_date.after_or_equal' => 'Abholdatum darf nicht in der Vergangenheit liegen.',

            'pieces.array' => 'Pieces muss eine Liste sein.',
            'pieces.min' => 'Pieces muss mindestens ein Element enthalten.',
            'pieces.*.number_of_pieces.required_with' => 'Bitte Anzahl der Packstuecke angeben.',
            'pieces.*.number_of_pieces.integer' => 'Anzahl der Packstuecke muss eine Ganzzahl sein.',
            'pieces.*.number_of_pieces.min' => 'Anzahl der Packstuecke muss mindestens 1 sein.',
            'pieces.*.number_of_pieces.max' => 'Anzahl der Packstuecke darf maximal 999 sein.',
            'pieces.*.package_type.alpha_num' => 'Pakettyp darf nur Buchstaben und Ziffern enthalten.',
            'pieces.*.package_type.max' => 'Pakettyp darf maximal 4 Zeichen lang sein.',
            'pieces.*.weight.required_with' => 'Bitte ein Gewicht angeben.',
            'pieces.*.weight.numeric' => 'Gewicht muss numerisch sein.',
            'pieces.*.weight.min' => 'Gewicht muss groesser als 0 sein.',
            'pieces.*.weight.max' => 'Gewicht darf maximal 99999 sein.',
            'pieces.*.width.numeric' => 'Breite muss numerisch sein.',
            'pieces.*.width.min' => 'Breite muss mindestens 1 sein.',
            'pieces.*.width.max' => 'Breite darf maximal 999 sein.',
            'pieces.*.height.numeric' => 'Hoehe muss numerisch sein.',
            'pieces.*.height.min' => 'Hoehe muss mindestens 1 sein.',
            'pieces.*.height.max' => 'Hoehe darf maximal 999 sein.',
            'pieces.*.length.numeric' => 'Laenge muss numerisch sein.',
            'pieces.*.length.min' => 'Laenge muss mindestens 1 sein.',
            'pieces.*.length.max' => 'Laenge darf maximal 999 sein.',
            'pieces.*.marks_and_numbers.max' => 'Kennzeichnung darf maximal 35 Zeichen lang sein.',

            'freight_profile_id.integer' => 'Freight-Profile-ID muss eine Ganzzahl sein.',
            'freight_profile_id.exists' => 'Das angegebene Freight-Profil existiert nicht.',
            'sender_profile_id.integer' => 'Sender-Profile-ID muss eine Ganzzahl sein.',
            'sender_profile_id.exists' => 'Das angegebene Sender-Profil existiert nicht.',
        ];
    }

    /**
     * Normalisiert string-basierte Eingaben (Uppercase fuer Codes/Pakettypen).
     */
    protected function prepareForValidation(): void
    {
        $payload = [];

        if ($this->has('product_code')) {
            $value = $this->input('product_code');
            if (is_string($value) && trim($value) !== '') {
                $payload['product_code'] = strtoupper(trim($value));
            }
        }

        if ($this->has('payer_code')) {
            $value = $this->input('payer_code');
            if (is_string($value) && trim($value) !== '') {
                $payload['payer_code'] = strtoupper(trim($value));
            }
        }

        if ($this->has('default_package_type')) {
            $value = $this->input('default_package_type');
            if (is_string($value) && trim($value) !== '') {
                $payload['default_package_type'] = strtoupper(trim($value));
            }
        }

        if ($this->has('pieces') && is_array($this->input('pieces'))) {
            $pieces = [];
            foreach ((array) $this->input('pieces') as $key => $piece) {
                if (! is_array($piece)) {
                    $pieces[$key] = $piece;

                    continue;
                }
                if (isset($piece['package_type']) && is_string($piece['package_type']) && trim($piece['package_type']) !== '') {
                    $piece['package_type'] = strtoupper(trim($piece['package_type']));
                }
                $pieces[$key] = $piece;
            }
            $payload['pieces'] = $pieces;
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }
}
