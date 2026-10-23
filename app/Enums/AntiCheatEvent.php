<?php

declare(strict_types=1);

namespace App\Enums;

use App\Contracts\HasLabel;

/**
 * Client-reported exam integrity events (logged, never trusted for grading).
 */
enum AntiCheatEvent: string implements HasLabel
{
    case TabHidden = 'tab_hidden';
    case WindowBlur = 'window_blur';
    case Copy = 'copy';
    case Paste = 'paste';
    case FullscreenExit = 'fullscreen_exit';
    case DevtoolsSuspected = 'devtools_suspected';
    /** Recorded by the server (autosave from a different session), never accepted from clients. */
    case SessionChanged = 'session_changed';

    public function label(): string
    {
        return match ($this) {
            self::TabHidden => __('Tab hidden'),
            self::WindowBlur => __('Window lost focus'),
            self::Copy => __('Copied text'),
            self::Paste => __('Pasted text'),
            self::FullscreenExit => __('Left fullscreen'),
            self::DevtoolsSuspected => __('Developer tools suspected'),
            self::SessionChanged => __('Continued in another session'),
        };
    }
}
