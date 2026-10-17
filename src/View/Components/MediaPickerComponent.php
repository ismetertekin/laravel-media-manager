<?php

namespace Yazilim360\MediaManager\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;
use Yazilim360\MediaManager\Support\ClientConfig;

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
        public array $variants = [],
        public ?string $folder = null,
        public ?bool $crop = null,
    ) {}

    public function render(): View
    {
        $locale = $this->locale ?? app()->getLocale();

        return view('media-manager::components.media-picker', [
            'mmPickerTranslations' => ClientConfig::translations($locale),
        ]);
    }
}
