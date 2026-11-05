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
        abort_unless(app(BaselineProbe::class)->runtimeState(DB::connection())['requestsReady'], 503, 'Request intake needs guarded preparation.');
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
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->expectsJson() && isset($e->errors()['revision'])) return response()->json(['saved' => false, 'errors' => $e->errors()], 409);
            throw $e;
        } catch (QueryException|\PDOException) {
            if ($request->expectsJson()) return response()->json(['saved' => false, 'message' => 'The request could not be saved. Your entered values are retained.'], 409);
            return back()->withInput()->with('error', 'The request could not be saved. Your entered values are retained; reload the saved request before trying again.');
        }
    }
}
