{{--
    Eine einzelne Repeater-Zeile fuer den DHL Package Editor.

    Engineering-Handbuch §75.1 (keine doppelte UI-Struktur):
    Diese Zeile ist die EINZIGE Quelle der Wahrheit fuer das Markup einer
    Paket-Zeile — wird sowohl serverseitig (Pre-Filling) als auch als
    Template fuer das JS-Modul (data-package-editor-row-template) genutzt.

    Erwartete Variablen:
      - $rowIndex          int (0-basiert; bei JS-Templates per Replace ersetzt)
      - $row               array<string,string|int>
      - $defaultPackageType string
--}}
@php
    $rowIndex = $rowIndex ?? 0;
    $row = $row ?? [];
    $row['number_of_pieces'] = $row['number_of_pieces'] ?? 1;
    $row['package_type'] = $row['package_type'] ?? ($defaultPackageType ?? 'PAL');
    $row['weight'] = $row['weight'] ?? '';
    $row['length'] = $row['length'] ?? '';
    $row['width'] = $row['width'] ?? '';
    $row['height'] = $row['height'] ?? '';
    $row['marks_and_numbers'] = $row['marks_and_numbers'] ?? '';
    $rowLabel = 'Paket ' . ($rowIndex + 1);
@endphp
<tr
    data-package-editor-row
    role="group"
    aria-label="{{ $rowLabel }}"
>
    <td>
        <input
            type="number"
            name="pieces[{{ $rowIndex }}][number_of_pieces]"
            class="form-control form-control-sm"
            min="1"
            max="999"
            step="1"
            value="{{ $row['number_of_pieces'] }}"
            aria-label="{{ $rowLabel }}: Anzahl"
            required
        >
    </td>
    <td>
        <input
            type="text"
            name="pieces[{{ $rowIndex }}][package_type]"
            class="form-control form-control-sm text-uppercase"
            maxlength="4"
            minlength="1"
            pattern="[A-Z0-9]{1,4}"
            value="{{ $row['package_type'] }}"
            aria-label="{{ $rowLabel }}: Pakettyp"
        >
    </td>
    <td>
        <input
            type="number"
            name="pieces[{{ $rowIndex }}][weight]"
            class="form-control form-control-sm"
            min="0.01"
            max="99999"
            step="0.01"
            value="{{ $row['weight'] }}"
            aria-label="{{ $rowLabel }}: Gewicht in Kilogramm"
            required
        >
    </td>
    <td>
        <input
            type="number"
            name="pieces[{{ $rowIndex }}][length]"
            class="form-control form-control-sm"
            min="1"
            max="999"
            step="1"
            value="{{ $row['length'] }}"
            aria-label="{{ $rowLabel }}: Länge in Zentimetern"
        >
    </td>
    <td>
        <input
            type="number"
            name="pieces[{{ $rowIndex }}][width]"
            class="form-control form-control-sm"
            min="1"
            max="999"
            step="1"
            value="{{ $row['width'] }}"
            aria-label="{{ $rowLabel }}: Breite in Zentimetern"
        >
    </td>
    <td>
        <input
            type="number"
            name="pieces[{{ $rowIndex }}][height]"
            class="form-control form-control-sm"
            min="1"
            max="999"
            step="1"
            value="{{ $row['height'] }}"
            aria-label="{{ $rowLabel }}: Höhe in Zentimetern"
        >
    </td>
    <td>
        <input
            type="text"
            name="pieces[{{ $rowIndex }}][marks_and_numbers]"
            class="form-control form-control-sm"
            maxlength="35"
            value="{{ $row['marks_and_numbers'] }}"
            aria-label="{{ $rowLabel }}: Marks/Numbers"
        >
    </td>
    <td class="text-end">
        <button
            type="button"
            class="btn btn-outline-danger btn-sm"
            data-package-editor-remove
            aria-label="{{ $rowLabel }} entfernen"
        >Entfernen</button>
    </td>
</tr>
