<?php

namespace App\Http\Controllers;


class BrowserMemoryDemoController
{
    public function index()
    {
        return response()->view('design.phase-a.browser-memory-demo')
            ->header('Cache-Control', 'private, no-store');
    }

    public function data()
    {
        return app(CatalogSnapshotController::class)->data();
    }
}
