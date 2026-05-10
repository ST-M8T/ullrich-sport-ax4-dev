<?php

declare(strict_types=1);

namespace App\View\Composers\Identity;

use Illuminate\View\View;

/**
 * Identity User Edit View Composer
 * Bereitet User-Edit-Daten vor
 */
final class IdentityUserEditComposer
{
    public function compose(View $view): void
    {
        $data = $view->getData();

        if (! isset($data['user'])) {
            return;
        }

        $user = $data['user'];

        $isDisabled = $user->disabled();
        $mustChange = $user->requiresPasswordChange();
        $mustChangeRaw = old('must_change_password', $mustChange ? '1' : '0');
        $disabledRaw = old('disabled', $isDisabled ? '1' : '0');
        $mustChangeOld = is_scalar($mustChangeRaw) && (string) $mustChangeRaw === '1';
        $disabledOld = is_scalar($disabledRaw) && (string) $disabledRaw === '1';

        $view->with([
            'isDisabled' => $isDisabled,
            'mustChange' => $mustChange,
            'mustChangeOld' => $mustChangeOld,
            'disabledOld' => $disabledOld,
        ]);
    }
}
