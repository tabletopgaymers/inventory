<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Purchase-specific contact snapshots and auditable vendor replacement. */
class PurchaseProcurement
{
    public const FIELDS = ['delivery_choice','delivery_name','delivery_address','delivery_email','delivery_phone'];

    public static function delivery(array $basics): array
    {
        $choice = $basics['delivery_choice'] ?? '';
        $values = array_intersect_key($basics, array_flip(self::FIELDS));
        if (str_starts_with($choice, 'location:')) {
            $row = app(CatalogRecords::class)->records('storage_locations')->firstWhere('id', (int) substr($choice,9));
            $values += ['delivery_name'=>$row?->name ?? '', 'delivery_address'=>$row?->mailing_address ?? ''];
            $values['delivery_name'] = $row?->name ?? '';
            $values['delivery_address'] = $row?->mailing_address ?? '';
        } elseif (str_starts_with($choice, 'user:')) {
            $row = User::find((int) substr($choice,5));
            $values = array_replace($values, ['delivery_name'=>$row?->displayName() ?? '', 'delivery_address'=>$row?->mailing_address ?? '',
                'delivery_email'=>$row?->personal_email ?: ($row?->contact_email ?? ''), 'delivery_phone'=>$row?->mobile_number ?? '']);
        }
        return array_replace(array_fill_keys(self::FIELDS,''), $values);
    }

    public static function vendorSnapshot(?int $id): array
    {
        $row = $id ? DB::table('catalog_references')->where('kind','suppliers')->find($id) : null;
        return $row ? array_intersect_key((array)$row, array_flip(['id','name','website','contact_name','email','phone','address','notes','state'])) : [];
    }

    public static function draftVendor(?int $id, ?int $retained): void
    {
        if (!$id) return;
        $row = DB::table('catalog_references')->where('kind','suppliers')->where('id',$id)->lockForUpdate()->first();
        if (!$row || (!in_array($row->state,['active','unverified'],true) && $id !== $retained)) throw ValidationException::withMessages(['supplier_id'=>'Choose an active or Unverified vendor.']);
    }

    public static function complete(object $row, array $values): array
    {
        $supplier=RequestValues::id($values['supplier_id'] ?? null,'supplier_id');
        if (!$supplier) throw ValidationException::withMessages(['supplier_id'=>'Choose and verify the actual vendor in Procurement.']);
        RequestValues::association('suppliers',$supplier,null,'supplier_id',true);
        $delivery=[];
        foreach (self::FIELDS as $field) $delivery[$field]=RequestValues::text($values[$field] ?? null,$field,$field==='delivery_address'?5000:255,in_array($field,['delivery_choice','delivery_name','delivery_address'],true)) ?? '';
        $choice=$delivery['delivery_choice']; $type=null; $source=null;
        if (preg_match('/\Alocation:([1-9][0-9]*)\z/D',$choice,$match)) {
            $type='storage';$source=RequestValues::id($match[1],'delivery_choice');
            RequestValues::association('storage_locations',$source,$row->receiving_location_id,'delivery_choice');
        } elseif (preg_match('/\Auser:([1-9][0-9]*)\z/D',$choice,$match)) {
            $type='user';$source=RequestValues::id($match[1],'delivery_choice');
            $header=RequestStock::header('purchase',$row->id);
            PurchaseRequestBasics::validateChoices(['vendor_choice'=>'','delivery_choice'=>$choice],['delivery_choice'=>$header['delivery_choice'] ?? $header['request_basics']['delivery_choice'] ?? '']);
        } elseif ($choice==='other') $type='other';
        else throw ValidationException::withMessages(['delivery_choice'=>'Choose a delivery destination in Procurement.']);
        if ($delivery['delivery_email'] !== '' && !filter_var($delivery['delivery_email'],FILTER_VALIDATE_EMAIL)) throw ValidationException::withMessages(['delivery_email'=>'Enter a valid delivery email or leave it blank.']);
        return $delivery + ['supplier_id'=>$supplier,'vendor_snapshot'=>self::vendorSnapshot($supplier),'delivery_type'=>$type,'delivery_source_id'=>$source,'receiving_location_id'=>$type==='storage'?$source:null];
    }

    public static function references(mixed $data, int $id): bool
    {
        if (!is_array($data)) return false;
        foreach ($data as $key=>$value) {
            if (in_array((string)$key,['supplier_id','vendor_other_id'],true) && (string)$value === (string)$id) return true;
            if ($key === 'vendor_choice' && $value === 'supplier:'.$id) return true;
            if (is_array($value) && self::references($value,$id)) return true;
        }
        return false;
    }

    public function replace(int $actorId, int $id, mixed $revision, int $replacement, bool $delete): array
    {
        return DB::transaction(function () use ($actorId,$id,$revision,$replacement,$delete) {
            $actor = RequestValues::actor($actorId);
            abort_unless(RequestValues::manages($actor,'procurement'),403);
            $row = DB::table('purchase_requests')->where('id',$id)->lockForUpdate()->firstOrFail();
            RequestValues::revision($revision,$row);
            abort_unless(in_array($row->status,['Request','Ordered','Shipped'],true),409);
            $header = RequestStock::header('purchase',$id);
            $choice = $header['request_basics']['vendor_choice'] ?? '';
            $oldId = $row->supplier_id ?: (str_starts_with($choice,'supplier:') ? (int)substr($choice,9) : null);
            if (!$oldId || (int)$oldId === $replacement) throw ValidationException::withMessages(['supplier_id'=>'Choose a different replacement vendor.']);
            foreach (array_unique([(int)$oldId,$replacement]) as $vendorId) DB::table('catalog_references')->where('id',$vendorId)->where('kind','suppliers')->lockForUpdate()->firstOrFail();
            RequestValues::association('suppliers',$replacement,null,'supplier_id',true);
            $old = self::vendorSnapshot((int)$oldId); $new = self::vendorSnapshot($replacement);
            $reason = null;
            foreach (DB::table('purchase_request_activity')->whereIn('action',['Marked Ordered','Saved ordered changes','Received purchase'])->get(['current']) as $entry) {
                if (self::references(json_decode($entry->current ?? '{}',true),(int)$oldId)) { $reason='it appears on a confirmed order'; break; }
            }
            if (!$reason && DB::table('purchase_requests')->where('id','!=',$id)->where(function ($query) use ($oldId,$old) {
                $query->where('supplier_id',$oldId)->orWhere('suggested_merchant',$old['name']);
            })->exists()) $reason='another purchase references it';
            if (!$reason) foreach (DB::table('purchase_fulfillment')->where('purchase_request_id','!=',$id)->get(['data']) as $entry) {
                if (self::references(json_decode($entry->data,true),(int)$oldId)) { $reason='another purchase or saved draft references it'; break; }
            }
            $removed = $delete && !$reason;
            $submitted = $header['submitted_request']['vendor_snapshot'] ?? array_intersect_key($header['submitted_request']['values'] ?? [],array_flip(['vendor_name','vendor_website','vendor_contact','vendor_email','vendor_phone']));
            $note = 'Submitted vendor details: '.json_encode($submitted,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\nCurrent vendor details: ".json_encode($old,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n".$actor->displayName().($removed ? ' removed this unused vendor' : ' replaced this vendor on this request (record retained'.($reason ? ': '.$reason : '').')').' and selected '.$new['name'].'.';
            // Preserve complete Unicode details without exceeding the existing TEXT column.
            $part = 1;
            do {
                $chunk = mb_strcut($note,0,50000,'UTF-8');
                $note = substr($note,strlen($chunk));
                DB::table('purchase_request_notes')->insert(['purchase_request_id'=>$id,'actor_id'=>$actorId,'actor_name'=>$actor->displayName(),
                    'body'=>($part > 1 ? 'Vendor replacement details (continued): ' : '').$chunk,'occurred_at'=>now('UTC')]);
                $part++;
            } while ($note !== '');
            DB::table('purchase_requests')->where('id',$id)->update(['supplier_id'=>$replacement,'suggested_merchant'=>$new['name']]);
            $header['request_basics']['vendor_choice']='supplier:'.$replacement;
            $header['request_basics']['vendor_other_id']=''; $header['request_basics']['vendor_other_baseline']=[];
            foreach ($header['purchase_progress'] ?? [] as $worker=>$modes) foreach ($modes as $mode=>$draft) {
                if ((int)$draft['revision'] !== (int)$row->revision) continue;
                $header['purchase_progress'][$worker][$mode]['values']['supplier_id']=(string)$replacement;
                $header['purchase_progress'][$worker][$mode]['revision']=(int)$row->revision+1;
            }
            RequestStock::saveHeader('purchase',$id,$header);
            if ($removed) DB::table('catalog_references')->where('id',$oldId)->where('kind','suppliers')->delete();
            RequestStock::changed('purchase',$row,$actor,'Replaced vendor',['previous_vendor'=>$old,'replacement_vendor'=>$new,'removed'=>$removed,'retained_reason'=>$reason]);
            return ['removed'=>$removed,'reason'=>$reason];
        },3);
    }
}
