<?php

namespace Yazilim360\MediaManager\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Blade component: <x-media-picker />
 *
 * Usage:
 *   <x-media-picker />
 *   <x-media-picker :multiple="true" :max="5" :sidebar="false" locale="tr" theme="dark" />
 *
 * Props are forwarded to window.MediaManager.open(); çeviriler locale ile yüklenir.
 */
class MediaPickerComponent extends Component
{
    public function __construct(
        public bool $multiple = false,
        public int $max = 1,
        public string $types = '["image","video","document"]',
        public string $buttonText = 'Choose Media',
        public string $buttonClass = 'btn btn-primary',
        public string $onSelect = '',
        public ?bool $sidebar = null,
        public ?string $locale = null,
        public ?string $theme = null,
    ) {}

    public function render(): View
    {
        $locale = $this->locale ?? app()->getLocale();
        $mmPickerTranslations = trans('media-manager::media-manager', [], $locale);

        if (! is_array($mmPickerTranslations)) {
            $mmPickerTranslations = [];
        }

        return view('media-manager::components.media-picker', [
            'mmPickerTranslations' => $mmPickerTranslations,
        ]);
    }
}
