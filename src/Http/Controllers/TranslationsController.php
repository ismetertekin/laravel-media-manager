<?php

namespace Yazilim360\MediaManager\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class TranslationsController extends Controller
{
    /**
     * JSON çeviri satırları (Vue / open({ locale: 'tr' }) için).
     *
     * GET /media-manager/api/translations?locale=tr
     */
    public function __invoke(Request $request): JsonResponse
    {
        $allowed = config('media-manager.allowed_locales', ['en', 'tr']);
        $raw = (string) $request->query('locale', app()->getLocale());
        $locale = strtolower(explode('-', str_replace('_', '-', $raw))[0] ?? 'en');

        if (! in_array($locale, $allowed, true)) {
            $locale = in_array('en', $allowed, true) ? 'en' : ($allowed[0] ?? 'en');
        }

        $lines = trans('media-manager::media-manager', [], $locale);

        return response()->json(is_array($lines) ? $lines : []);
    }
}
