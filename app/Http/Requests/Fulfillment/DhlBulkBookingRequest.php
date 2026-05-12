<?php

declare(strict_types=1);

namespace App\Http\Requests\Fulfillment;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validiert eine DHL-Freight-Bulk-Buchungsanfrage am Rand der Anwendung.
 *
 * Engineering-Handbuch §15 (Validierung) + §22 (API): Eingabe wird hier hart
 * validiert. Die Domain (DhlPayerCode, DhlProductCode, DhlPackageType) erzwingt
 * fachliche Invarianten zusaetzlich (Defense in Depth, §19).
 *
 * Die Pflichtfelder sind identisch zur Single-Booking (DhlBookingRequest) —
 * pro Bulk-Aufruf gilt EIN Produkt/EIN PayerCode/EIN DefaultPackageType fuer
 * alle Bestellungen. Pro-Bestellung-Overrides sind bewusst nicht vorgesehen
 * (KISS, §62: Bulk = einheitliches Set).
 */
final class DhlBulkBookingRequest extends FormRequest
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
        return [
            'order_ids' => ['required', 'array', 'min:1', 'max:500'],
            'order_ids.*' => ['required', 'integer', 'min:1', 'distinct', 'exists:shipment_orders,id'],

            'product_code' => ['required', 'string', 'size:3', 'regex:/^[A-Z0-9]{3}$/'],
            'payer_code' => ['required', 'string', 'in:DAP,DDP,EXW,CIP'],
            'default_package_type' => ['required', 'string', 'alpha_num', 'max:4', 'min:1'],

            // BC: legacy product_id darf zusaetzlich/alternativ uebergeben werden.
            'product_id' => ['nullable', 'string', 'max:64'],

            'additional_services' => ['nullable', 'array'],
            'additional_services.*' => ['string', 'max:50'],

            'pickup_date' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }

    /**
     * @return array<string,string>
     */
    public function messages(): array
    {
        return [
            'order_ids.required' => 'Bitte mindestens eine Bestell-ID angeben.',
            'order_ids.array' => 'Bestell-IDs muessen als Liste uebergeben werden.',
            'order_ids.min' => 'Bitte mindestens eine Bestell-ID angeben.',
            'order_ids.max' => 'Pro Bulk-Buchung sind maximal 500 Bestellungen erlaubt.',
            'order_ids.*.required' => 'Bestell-ID darf nicht leer sein.',
            'order_ids.*.integer' => 'Jede Bestell-ID muss eine Ganzzahl sein.',
            'order_ids.*.min' => 'Jede Bestell-ID muss mindestens 1 sein.',
            'order_ids.*.distinct' => 'Bestell-IDs duerfen nicht doppelt vorkommen.',
            'order_ids.*.exists' => 'Mindestens eine Bestell-ID existiert nicht.',

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
        ];
    }

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

        if ($payload !== []) {
            $this->merge($payload);
        }
    }
}
