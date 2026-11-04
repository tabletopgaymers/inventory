<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PurchaseFulfillment
{
    public function validateCatalogLines(int $id, int $actor, array $lines, string $mode='order'): void
    {
        $retained = DB::table('purchase_fulfillment_lines')->where('purchase_request_id',$id)->whereNotNull('item_id')->pluck('item_id')->map(fn($item)=>(int)$item)->all();
        $retained = array_merge($retained,DB::table('purchase_request_lines')->where('purchase_request_id',$id)->pluck('item_id')->map(fn($item)=>(int)$item)->all(),
            DB::table('purchase_material_lines')->where('purchase_request_id',$id)->whereNotNull('item_id')->pluck('item_id')->map(fn($item)=>(int)$item)->all());
        foreach ($this->progressDraft($actor,$id,$mode)['values']['lines'] ?? [] as $key=>$line) {
            $item=array_key_exists('item_id',$line)?$line['item_id']:(ctype_digit((string)$key)?$key:null);
            if ($item) $retained[]=(int)$item;
        }
        foreach ($lines as $key=>$line) if ($line['item_id']) RequestValues::association('items',$line['item_id'],in_array($line['item_id'],$retained,true)?$line['item_id']:null,'lines.'.$key.'.item_id');
    }

    public function orderReview(int $actorId, int $id, mixed $revision, array $values): array
    {
        return DB::transaction(function () use($actorId,$id,$revision,$values) {
            $actor=RequestValues::actor($actorId);
            abort_unless(RequestValues::manages($actor,'procurement'),403);
            abort_unless(app(BaselineProbe::class)->schemaState(DB::connection())['fulfillmentReady'],503);
            $row=DB::table('purchase_requests')->where('id',$id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($row->status,['Draft','Request','Ordered','Shipped'],true),409);
            RequestValues::revision($revision,$row);
            $costing=app(PurchaseCosting::class)->calculate($values);
            $this->validateCatalogLines($id,$actorId,$costing['lines']);
            return $costing + PurchaseProcurement::complete($row,$values);
        },3);
    }

    public function expected(object $row, array $header): array
    {
        if (!in_array($row->status,['Ordered','Shipped'],true)) return [];
        return $header['ordered_quantities'] ?? [];
    }

    public function recordedOrder(int $id): array
    {
        $entry=DB::table('purchase_request_activity')->where('purchase_request_id',$id)->whereIn('action',['Marked Ordered','Saved ordered changes'])->orderByDesc('id')->first();
        return json_decode($entry->current ?? '{}',true);
    }

    public function receiptReview(int $actorId, int $id, mixed $revision, array $data): array
    {
        return DB::transaction(function () use($actorId,$id,$revision,$data) {
            $actor=RequestValues::actor($actorId);
            abort_unless(RequestValues::manages($actor,'procurement'),403);
            $row=DB::table('purchase_requests')->where('id',$id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($row->status,['Ordered','Shipped'],true),409);
            RequestValues::revision($revision,$row);
            $header=RequestStock::header('purchase',$id);
            $this->assertReceiptSource($header,$data);
            $order=$this->recordedOrder($id);
            if (!$order || (int)($order['supplier_id'] ?? 0)!==(int)$row->supplier_id) throw ValidationException::withMessages(['order'=>'Record the updated order before confirming receipt. Receiving values are retained.']);
            $protected=['title','supplier_id','receiving_location_id','ordered_date','shipped_date',...PurchaseProcurement::FIELDS];
            $values=array_replace($data,array_replace(array_fill_keys($protected,''),array_intersect_key($order,array_flip($protected))));
            if ($values['delivery_choice']==='' && !empty($order['receiving_location_id'])) $values['delivery_choice']='location:'.$order['receiving_location_id'];
            $fields=PurchaseProcurement::complete($row,$values,true);
            $fields['vendor_snapshot']=$order['vendor_snapshot'] ?? $fields['vendor_snapshot'];
            $raw=PurchaseProgress::invoice($values);
            foreach ($raw['lines'] as $key=>$line) if ($line['item_id'] && ($order['lines'][$key]['item_id'] ?? null)!==$line['item_id']) {
                foreach ($order['lines'] ?? [] as $orderedKey=>$orderedLine) if (($orderedLine['item_id'] ?? null)===$line['item_id'] && !isset($raw['lines'][$orderedKey])) {
                    unset($raw['lines'][$key]);$line['line_key']=(string)$orderedKey;$line['source_request_key']=$orderedLine['source_request_key'] ?? null;$raw['lines'][$orderedKey]=$line;break;
                }
            }
            $received=[];$positive=[];
            foreach ($raw['lines'] as $key=>$line) {
                $quantity=RequestValues::quantity($line['quantity'],'lines.'.$key.'.quantity');
                RequestValues::text($line['description'],'lines.'.$key.'.description',500,true);
                if ($quantity===0) $received[$key]=array_replace($line,['quantity'=>0,'unit'=>'0.000000000000','cost'=>0,'fee'=>0]);
                else {
                    foreach (['unit','cost','fee'] as $field) if ($line[$field]==='') throw ValidationException::withMessages(['lines.'.$key.'.'.$field=>'Complete this received item’s '.$field.'.']);
                    $positive[$key]=$line;
                }
            }
            if ($positive===[]) throw ValidationException::withMessages(['lines'=>'No items were received. Cancel the purchase after resolving the outcome with the supplier.']);
            $costing=app(PurchaseCosting::class)->calculate(array_replace($values,['lines'=>$positive]));
            $this->validateCatalogLines($id,$actorId,$costing['lines'],'receipt');
            $received=array_replace($received,$costing['lines']);
            foreach ($order['lines'] ?? [] as $key=>$line) if (!isset($received[$key])) $received[$key]=array_replace(PurchaseInvoiceLines::identity($key,$line),$line,['line_key'=>(string)$key,'quantity'=>0,'unit'=>'0.000000000000','cost'=>0,'fee'=>0]);
            $stock=app(RequestStock::class);$ids=array_values(array_filter(array_column($costing['lines'],'item_id')));$items=$stock->items($ids);$projection=[];
            foreach ($costing['lines'] as $key=>$line) if ($line['item_id']) {
                $item=$line['item_id'];$held=$stock->held($item);$storage=$fields['delivery_type']==='storage';
                $before=$items[$item]->unit_cost;$after=PurchaseCosting::receiptCost($held,$before,$line['quantity'],$line['acquisition'],$storage);
                $location=$storage?(int)DB::table('inventory_balances')->where('item_id',$item)->where('storage_location_id',$fields['receiving_location_id'])->value('quantity'):null;
                $projection[$key]=['item_id'=>$item,'held'=>$held,'before_cost'=>$before,'after_held'=>$held+($storage?$line['quantity']:0),'after_cost'=>$after,
                    'stock_change'=>$storage?$line['quantity']:0,'location_before'=>$location,'location_after'=>$storage?$location+$line['quantity']:null];
            }
            return $costing+$fields+['title'=>$order['title'],'ordered_date'=>$order['ordered_date'],'shipped_date'=>$order['shipped_date'] ?? '',
                'received_date'=>RequestStock::date($values['received_date'] ?? null,'received_date'),'source_order_operation'=>$header['order_operation'],
                'received_lines'=>$received,'ordered_lines'=>$order['lines'] ?? [],'projection'=>$projection,'receiver_id'=>$actorId,'receiver_name'=>$actor->displayName(),'can_confirm'=>true];
        },3);
    }

    private function recordOrder(int $actorId, int $id, mixed $revision, array $data, string $action, ?string $operation): array
    {
        return DB::transaction(function () use($actorId,$id,$revision,$data,$action,$operation) {
            $actor=RequestValues::actor($actorId);
            abort_unless(RequestValues::manages($actor,'procurement'),403);
            $row=DB::table('purchase_requests')->where('id',$id)->lockForUpdate()->firstOrFail();
            $header=RequestStock::header('purchase',$id);
            if ($action==='confirm' && ($header['order_operation'] ?? null)===$operation) {
                abort_unless((int)($header['order_actor'] ?? 0)===$actorId,403);
                return ['duplicate'=>true];
            }
            abort_unless(in_array($row->status,['Request','Ordered','Shipped'],true),409,'Submit the request before recording an order.');
            RequestValues::revision($revision,$row);
            $clean=$this->orderReview($actorId,$id,$revision,$data);
            $clean += ['title'=>RequestValues::text($data['title'] ?? null,'title',255,true),
                'ordered_date'=>RequestStock::date($data['ordered_date'] ?? null,'ordered_date'),
                'shipped_date'=>RequestStock::date($data['shipped_date'] ?? null,'shipped_date',true), 'received_date'=>''];
            $clean['procurer_id']=$header['procurer_id'] ?? $actorId;
            $clean['procurer_name']=$header['procurer_name'] ?? $actor->displayName();
            if ($action==='review') return $clean;
            $old=$this->expected($row,$header);
            $new=[];
            if ($clean['delivery_type']==='storage') foreach ($clean['lines'] as $line) if ($line['item_id']) $new[$line['item_id']]=$line['quantity'];
            $original=$row->supplier_id ?: (str_starts_with($header['request_basics']['vendor_choice'] ?? '', 'supplier:')?(int)substr($header['request_basics']['vendor_choice'],9):null);
            if ($original && (int)$original !== (int)$clean['supplier_id']) {
                // A different actual supplier always leaves evidence, even without optional deletion.
                app(PurchaseProcurement::class)->replace($actorId,$id,$row->revision,$clean['supplier_id'],false);
                $row=DB::table('purchase_requests')->where('id',$id)->lockForUpdate()->firstOrFail();
                $header=RequestStock::header('purchase',$id);
            }
            $stock=app(RequestStock::class);
            $ids=array_unique(array_merge(array_keys($old),array_keys($new)));
            $items=$stock->items($ids);
            foreach ($ids as $item) {
                $delta=($new[$item] ?? 0)-($old[$item] ?? 0);
                if (!$delta) continue;
                $stock->source($item,'ordered',$delta);
                $stock->entry($actor,'purchase',$id,$items[$item],$operation,'ordered','Purchase expectation: '.$clean['vendor_snapshot']['name'].' → '.$clean['delivery_name'],$delta,$items[$item]->unit_cost);
            }
            DB::table('purchase_fulfillment_lines')->where('purchase_request_id',$id)->delete();
            foreach ($clean['lines'] as $line) DB::table('purchase_fulfillment_lines')->insert(['purchase_request_id'=>$id]+array_intersect_key($line,array_flip(['line_key','item_id','description','group_label','source_request_key','quantity','unit','cost','fee'])));
            $saved=array_intersect_key($clean,array_flip(['title','supplier_id','vendor_snapshot','receiving_location_id','ordered_date','shipped_date','received_date','shipping_method','delivery_type','delivery_source_id',...PurchaseProcurement::FIELDS]));
            foreach ($clean['charges'] as $field=>$cents) $saved[$field]=PurchaseCosting::dollars($cents);
            $saved += ['order_operation'=>$operation,'order_actor'=>$actorId,'ordered_quantities'=>$new,'procurer_id'=>$header['procurer_id'] ?? $actorId,'procurer_name'=>$header['procurer_name'] ?? $actor->displayName()];
            unset($header['purchase_progress'][$actorId]['order']);
            RequestStock::saveHeader('purchase',$id,array_replace($header,$saved));
            DB::table('purchase_requests')->where('id',$id)->update(['title'=>$clean['title'],'supplier_id'=>$clean['supplier_id'],'receiving_location_id'=>$clean['receiving_location_id']]);
            RequestStock::changed('purchase',$row,$actor,in_array($row->status,['Ordered','Shipped'],true)?'Saved ordered changes':'Marked Ordered',$clean,$row->status==='Shipped'?'Shipped':'Ordered');
            return $clean;
        },3);
    }

    public function data(int $id): array
    {
        $row = DB::table('purchase_requests')->where('id', $id)->firstOrFail();
        $header = RequestStock::header('purchase', $id);
        $lines = [];
        foreach (DB::table('purchase_fulfillment_lines')->where('purchase_request_id', $id)->orderBy('id')->get() as $line) {
            $lines[$line->line_key] = ['line_key' => $line->line_key, 'item_id' => $line->item_id, 'description' => $line->description,
                'group_label' => $line->group_label, 'source_request_key' => $line->source_request_key,
                'quantity' => $line->quantity, 'unit' => $line->unit, 'cost' => PurchaseCosting::dollars((int) $line->cost), 'fee' => PurchaseCosting::dollars((int) $line->fee)];
        }
        unset($header['purchase_progress']);
        $header = array_replace($header, ['title' => $row->title ?? '', 'supplier_id' => $row->supplier_id ?? '', 'receiving_location_id' => $row->receiving_location_id ?? '']);
        if (!$header['supplier_id'] && str_starts_with($header['request_basics']['vendor_choice'] ?? '', 'supplier:')) $header['supplier_id'] = substr($header['request_basics']['vendor_choice'],9);
        $delivery = $header['submitted_request']['delivery_snapshot'] ?? PurchaseProcurement::delivery($header['request_basics'] ?? []);
        foreach (PurchaseProcurement::FIELDS as $field) $header[$field] ??= $delivery[$field];

        return $header + ['ordered_date' => $row->planned_date ?? '',
            'shipped_date' => '', 'received_date' => '', 'shipping_method' => 'line_total', 'discount' => '0.00', 'tax' => '0.00', 'shipping' => '0.00', 'other_fees' => '0.00', 'lines' => $lines];
    }

    public function progressDraft(int $actorId, int $id, string $mode): ?array
    {
        return RequestStock::header('purchase', $id)['purchase_progress'][$actorId][$mode] ?? null;
    }

    public function assertReceiptSource(array $header, array $values): void
    {
        if (empty($header['order_operation']) || ($values['source_order_operation'] ?? null)!==$header['order_operation']) throw ValidationException::withMessages(['source_order_operation'=>'The recorded order changed. Your receipt values are retained; reconcile with the updated order before continuing.']);
    }

    public function saveProgress(int $actorId, int $id, mixed $revision, array $data, string $mode): array
    {
        abort_unless(in_array($mode, ['order', 'receipt'], true), 422);

        return DB::transaction(function () use ($actorId, $id, $revision, $data, $mode) {
            $actor = RequestValues::actor($actorId);
            abort_unless(app(BaselineProbe::class)->schemaState(DB::connection())['fulfillmentReady'], 503);
            abort_unless(RequestValues::manages($actor, 'procurement'), 403);
            $row = DB::table('purchase_requests')->where('id', $id)->lockForUpdate()->firstOrFail();
            abort_unless($mode === 'receipt' ? in_array($row->status, ['Ordered', 'Shipped'], true) : in_array($row->status, ['Draft', 'Request', 'Ordered', 'Shipped'], true), 409);
            RequestValues::revision($revision, $row);
            $clean = PurchaseProgress::invoice($data);
            if ($clean['delivery_email'] !== '' && !filter_var($clean['delivery_email'],FILTER_VALIDATE_EMAIL)) throw ValidationException::withMessages(['delivery_email'=>'Enter a valid delivery email or leave it blank.']);
            $header = RequestStock::header('purchase', $id);
            $clean['procurer_id'] = $mode==='order' ? $actorId : ($header['procurer_id'] ?? null);
            $clean['procurer_name'] = $mode==='order' ? $actor->displayName() : ($header['procurer_name'] ?? '');
            $before = $header['purchase_progress'][$actorId][$mode] ?? null;
            if ($mode==='receipt') {
                $this->assertReceiptSource($header,$clean);
                $clean['receiver_id']=$actorId; $clean['receiver_name']=$actor->displayName();
            }
            if ($clean['delivery_choice'] === '' && $clean['receiving_location_id'] !== '') $clean['delivery_choice']='location:'.$clean['receiving_location_id'];
            $clean['receiving_location_id'] = str_starts_with($clean['delivery_choice'],'location:') ? substr($clean['delivery_choice'],9) : '';
            if ($clean['delivery_choice'] !== '') PurchaseRequestBasics::validateChoices(['vendor_choice'=>'', 'delivery_choice'=>$clean['delivery_choice']], ['delivery_choice'=>$before['values']['delivery_choice'] ?? $header['delivery_choice'] ?? $header['request_basics']['delivery_choice'] ?? '']);
            foreach (['supplier_id' => 'suppliers', 'receiving_location_id' => 'storage_locations'] as $field => $catalog) {
                $value = RequestValues::id($clean[$field], $field);
                $retainedValue = RequestValues::id($before['values'][$field] ?? null, $field);
                if ($field === 'supplier_id') PurchaseProcurement::draftVendor($value,$value === $retainedValue ? $retainedValue : $row->$field);
                else RequestValues::association($catalog, $value, $value === $retainedValue ? $retainedValue : $row->$field, $field);
            }
            foreach (['ordered_date', 'shipped_date', 'received_date'] as $field) {
                if ($clean[$field] !== '') {
                    RequestStock::date($clean[$field], $field);
                }
            }
            $retained = array_unique(array_merge(DB::table('purchase_fulfillment_lines')->where('purchase_request_id', $id)->pluck('item_id')->map(fn ($item) => (int) $item)->all(), DB::table('purchase_request_lines')->where('purchase_request_id', $id)->pluck('item_id')->map(fn ($item) => (int) $item)->all()));
            foreach ($before['values']['lines'] ?? [] as $key => $line) {
                $item = array_key_exists('item_id', $line) ? $line['item_id'] : (ctype_digit((string) $key) ? $key : null);
                if ($item) $retained[] = (int) $item;
            }
            $retained = array_unique($retained);
            $completeCosts = $clean['lines'] !== [];
            foreach ($clean['lines'] as $key => $line) {
                $item = $line['item_id'];
                if ($item) RequestValues::association('items', $item, in_array($item, $retained, true) ? $item : null, 'lines.'.$key.'.item_id');
                $item = $key;
                foreach (['quantity', 'unit', 'cost', 'fee'] as $field) {
                    if ($line[$field] === '') {
                        $completeCosts = false;
                    } elseif ($field === 'quantity') {
                        if (RequestValues::quantity($line[$field], 'lines.'.$item.'.quantity') === 0) {
                            $completeCosts = false;
                        }
                    } elseif ($field === 'unit') {
                        StockNumbers::cost($line[$field]);
                    } else {
                        PurchaseCosting::money($line[$field], 'lines.'.$item.'.'.$field);
                    }
                }
                if ($line['quantity'] !== '' && $line['unit'] !== '' && $line['cost'] !== '') {
                    $expected = BigDecimal::of($line['unit'])->multipliedBy(RequestValues::quantity($line['quantity'], 'lines.'.$item.'.quantity'))->toScale(2, RoundingMode::HalfUp);
                    if ((string) $expected !== PurchaseCosting::dollars(PurchaseCosting::money($line['cost'], 'lines.'.$item.'.cost'))) {
                        throw ValidationException::withMessages(['lines.'.$item.'.cost' => 'Cost does not equal quantity × the retained exact Unit, rounded to cents. Correct or explicitly recalculate this row.']);
                    }
                }
            }
            foreach (['other_fees', 'discount', 'tax', 'shipping'] as $field) {
                if ($clean[$field] === '') {
                    $completeCosts = false;
                } else {
                    PurchaseCosting::money($clean[$field], $field);
                }
            }
            if ($completeCosts) {
                app(PurchaseCosting::class)->calculate($clean);
            }
            if ($before && $before['values'] === $clean && (int) $before['revision'] === (int) $row->revision) {
                return $before;
            }
            $saved = ['revision' => (int) $row->revision + 1, 'values' => $clean];
            $header['purchase_progress'][$actorId][$mode] = $saved;
            if (strlen(json_encode($header['purchase_progress'], JSON_THROW_ON_ERROR)) > 5000000) {
                throw ValidationException::withMessages(['lines' => 'Saved invoice drafts exceed the available size for this purchase.']);
            }
            RequestStock::saveHeader('purchase', $id, $header);
            RequestStock::changed('purchase', $row, $actor, 'Saved invoice draft', ['progress_mode' => $mode]);

            return $saved;
        }, 3);
    }

    public function work(int $actorId, int $id, mixed $revision, array $data, string $mode, string $action, ?string $operation = null): array
    {
        abort_unless(in_array($mode, ['order', 'receipt'], true) && in_array($action, ['review', 'confirm'], true), 422);
        if ($action === 'confirm') {
            abort_unless(is_string($operation) && Str::isUuid($operation), 422);
        }
        if ($mode==='order') return $this->recordOrder($actorId,$id,$revision,$data,$action,$operation);

        if ($action==='review') return $this->receiptReview($actorId,$id,$revision,$data);
        return $this->recordReceipt($actorId,$id,$revision,$data,$operation);
    }

    private function recordReceipt(int $actorId, int $id, mixed $revision, array $data, string $operation): array
    {
        return DB::transaction(function () use($actorId,$id,$revision,$data,$operation) {
            $actor=RequestValues::actor($actorId);
            abort_unless(RequestValues::manages($actor,'procurement'),403);
            abort_unless(app(BaselineProbe::class)->schemaState(DB::connection())['fulfillmentReady'],503);
            $row=DB::table('purchase_requests')->where('id',$id)->lockForUpdate()->firstOrFail();
            $header=RequestStock::header('purchase',$id);
            if (($header['receipt_operation'] ?? null)===$operation) {
                abort_unless((int)($header['receipt_actor'] ?? 0)===$actorId,403);
                return ['duplicate'=>true];
            }
            abort_unless(in_array($row->status,['Ordered','Shipped'],true),409,'Received is final.');
            RequestValues::revision($revision,$row);
            $clean=$this->receiptReview($actorId,$id,$revision,$data);
            $stock=app(RequestStock::class);$expected=$this->expected($row,$header);
            $ids=array_unique(array_merge(array_keys($expected),array_values(array_filter(array_column($clean['lines'],'item_id')))));
            $items=$stock->items($ids);
            foreach ($expected as $item=>$quantity) {
                $stock->source($item,'ordered',-(int)$quantity);
                $stock->entry($actor,'purchase',$id,$items[$item],$operation,'ordered','Purchase expectation cleared at final receipt',-(int)$quantity,$items[$item]->unit_cost);
            }
            foreach ($clean['lines'] as $key=>$line) if ($line['item_id']) {
                $item=$line['item_id'];$projection=$clean['projection'][$key];
                if ($clean['delivery_type']==='storage') $stock->storage($item,$clean['receiving_location_id'],$line['quantity']);
                DB::table('items')->where('id',$item)->update(['unit_cost'=>$projection['after_cost'],'updated_at'=>now('UTC')]);
                $stock->entry($actor,'purchase',$id,$items[$item],$operation,'purchase_receipt','Purchase receipt: '.$clean['vendor_snapshot']['name'].' → '.$clean['delivery_name'],$projection['stock_change'],$projection['after_cost']);
            }
            DB::table('purchase_fulfillment_lines')->where('purchase_request_id',$id)->delete();
            foreach ($clean['received_lines'] as $line) DB::table('purchase_fulfillment_lines')->insert(['purchase_request_id'=>$id]+array_intersect_key($line,array_flip(['line_key','item_id','description','group_label','source_request_key','quantity','unit','cost','fee'])));
            $saved=array_intersect_key($clean,array_flip(['title','supplier_id','vendor_snapshot','receiving_location_id','ordered_date','shipped_date','received_date','shipping_method','delivery_type','delivery_source_id',...PurchaseProcurement::FIELDS]));
            foreach ($clean['charges'] as $field=>$cents) $saved[$field]=PurchaseCosting::dollars($cents);
            $clean['procurer_id']=$header['procurer_id'] ?? null;$clean['procurer_name']=$header['procurer_name'] ?? '';
            $saved += ['receipt_operation'=>$operation,'receipt_actor'=>$actorId,'receiver_id'=>$actorId,'receiver_name'=>$actor->displayName(),'ordered_quantities'=>[],'final_breakdown'=>$clean];
            unset($header['purchase_progress'][$actorId]['receipt']);
            RequestStock::saveHeader('purchase',$id,array_replace($header,$saved));
            DB::table('purchase_requests')->where('id',$id)->update(['title'=>$clean['title'],'supplier_id'=>$clean['supplier_id'],'receiving_location_id'=>$clean['receiving_location_id']]);
            RequestStock::changed('purchase',$row,$actor,'Received purchase',$clean,'Received');
            return $clean;
        },3);
    }

    public function transition(int $actorId, int $id, mixed $revision, string $action, ?string $date = null): void
    {
        abort_unless(in_array($action, ['shipped', 'backward', 'cancel'], true), 422);
        DB::transaction(function () use ($actorId, $id, $revision, $action, $date) {
            $actor = RequestValues::actor($actorId);
            abort_unless(app(BaselineProbe::class)->schemaState(DB::connection())['fulfillmentReady'], 503);
            abort_unless(RequestValues::manages($actor, 'procurement'), 403);
            $row = DB::table('purchase_requests')->where('id', $id)->lockForUpdate()->firstOrFail();
            RequestValues::revision($revision, $row);
            $next = match ($action) {
                'shipped' => $row->status === 'Ordered' ? 'Shipped' : null,
                'backward' => match ($row->status) {
                    'Request' => 'Draft', 'Ordered' => 'Request', 'Shipped' => 'Ordered', default => null
                },
                'cancel' => in_array($row->status, ['Draft', 'Request', 'Ordered', 'Shipped'], true) ? 'Cancelled' : null,
            };
            abort_unless($next !== null, 409, 'Received is final and Cancelled is terminal.');
            $header = RequestStock::header('purchase', $id);
            if ($action === 'shipped') {
                $header['shipped_date'] = RequestStock::date($date ?: now(DisplayDates::effectiveZone())->toDateString(), 'shipped_date');
            }
            if (in_array($row->status, ['Ordered', 'Shipped'], true) && ! in_array($next, ['Ordered', 'Shipped'], true)) {
                $stock = app(RequestStock::class);
                $lines = $this->expected($row,$header);
                $items = $stock->items(array_keys($lines));
                $operation = (string) Str::uuid();
                foreach ($lines as $itemId => $quantity) {
                    $stock->source($itemId, 'ordered', -(int) $quantity);
                    $stock->entry($actor, 'purchase', $id, $items[$itemId], $operation, 'ordered', $next === 'Cancelled' ? 'Purchase Cancelled' : 'Purchase returned to Request', -(int) $quantity, $items[$itemId]->unit_cost);
                }
                $header['ordered_quantities']=[];
            }
            if (in_array($row->status,['Ordered','Shipped'],true) && in_array($next,['Ordered','Shipped'],true)) {
                foreach ($header['purchase_progress'][$actorId] ?? [] as $mode=>$draft) {
                    if ((int)$draft['revision']!==(int)$row->revision) continue;
                    if ($mode==='receipt' && ($draft['values']['source_order_operation'] ?? null)!==($header['order_operation'] ?? null)) continue;
                    $header['purchase_progress'][$actorId][$mode]['revision']=(int)$row->revision+1;
                }
            }
            RequestStock::saveHeader('purchase', $id, $header);
            RequestStock::changed('purchase', $row, $actor, 'Status changed', ['recorded_dates' => array_intersect_key($header, array_flip(['ordered_date', 'shipped_date', 'received_date']))], $next);
        }, 3);
    }
}
