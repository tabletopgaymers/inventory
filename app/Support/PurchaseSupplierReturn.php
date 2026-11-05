<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Optional Catalog context, restricted to this actor's live Purchase workspace. */
class PurchaseSupplierReturn
{
    public static function context(Request $request, string $kind): array
    {
        $empty = ['purchaseReturnUrl' => null, 'purchaseReturnQuery' => ''];
        $token = $request->query('purchase_work');
        if ($kind !== 'suppliers' || !is_string($token) || !Str::isUuid($token)
            || !$request->user() || !RequestValues::manages($request->user(), 'procurement')) return $empty;
        $work = $request->session()->get('purchase_workspace.'.$token);
        if (!is_array($work) || ($work['actor'] ?? null) !== (int)$request->user()->id || empty($work['id'])) return $empty;

        return ['purchaseReturnUrl' => '/purchase/work/'.$token.'?tab=order',
            'purchaseReturnQuery' => '?'.http_build_query(['purchase_work' => $token])];
    }
}
