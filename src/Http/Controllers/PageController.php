<?php

namespace Yazilim360\MediaManager\Http\Controllers;

use Illuminate\Routing\Controller;

class PageController extends Controller
{
    /**
     * Serve the standalone media manager demo/development page.
     *
     * GET /media-manager
     */
    public function index()
    {
        return view('media-manager::index');
    }
}
