<?php

namespace App\Http\Controllers;

use App\Support\BaselineProbe;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

trait RequestPageSupport
{
    private function ready(): void
    {
        abort_unless(app(BaselineProbe::class)->schemaState(DB::connection())['requestsReady'], 503, 'Request intake needs guarded preparation.');
    }

    private function identity(Request $request): void
    {
        $request->validate(['owner_id' => ['prohibited'], 'status' => ['prohibited'], 'actor_id' => ['prohibited']]);
    }

    private function write(Request $request, callable $callback)
    {
        $this->ready();
        $this->identity($request);
        try {
            return $callback();
        } catch (QueryException|\PDOException) {
            return back()->withInput()->with('error', 'The request could not be saved. Your entered values are retained; reload the saved request before trying again.');
        }
    }
}
