<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UiModeController extends Controller
{
    private const DEFAULT_MODE = 'normal';
    private const SENIOR_MODE = 'senior';

    public function toggleUiMode(Request $request): RedirectResponse
    {
        $currentMode = (string) $request->session()->get('ui_mode', self::DEFAULT_MODE);
        $newMode = $currentMode === self::SENIOR_MODE ? self::DEFAULT_MODE : self::SENIOR_MODE;

        $request->session()->put('ui_mode', $newMode);

        return redirect('/');
    }

    public function setUiMode(Request $request, string $mode): RedirectResponse
    {
        if (!in_array($mode, [self::DEFAULT_MODE, self::SENIOR_MODE], true)) {
            return redirect('/');
        }

        $request->session()->put('ui_mode', $mode);

        return redirect('/');
    }
}
