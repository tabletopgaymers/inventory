<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Forward reconciliation for the locally applied first draft; also safe on clean installs.
        Schema::table('purchase_fulfillment_lines', function (Blueprint $table) {
            $table->string('group_label', 600)->default('Other items')->change();
        });
    }

    public function down(): void
    {
        throw new LogicException('Do not truncate purchase classification snapshots.');
    }
};
