<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('fiskalizimi.table', 'kuponat_fiskal');

        if (!Schema::hasTable($table)) {
            Schema::create($table, function (Blueprint $table) {
                $table->id();
                $table->timestamps();
            });
        }

        Schema::table($table, function (Blueprint $t) {
            if (!Schema::hasColumn($t->getTable(), 'fiscal_status')) {
                $t->string('fiscal_status', 20)->default('pending')->index();
            }
            if (!Schema::hasColumn($t->getTable(), 'fiscal_verification_no')) {
                $t->string('fiscal_verification_no', 16)->nullable()->unique();
            }
            if (!Schema::hasColumn($t->getTable(), 'fiscal_time')) {
                $t->unsignedBigInteger('fiscal_time')->nullable();
            }
            if (!Schema::hasColumn($t->getTable(), 'fiscal_details')) {
                $t->longText('fiscal_details')->nullable();
            }
            if (!Schema::hasColumn($t->getTable(), 'fiscal_signature')) {
                $t->text('fiscal_signature')->nullable();
            }
            if (!Schema::hasColumn($t->getTable(), 'fiscal_citizen_qr')) {
                $t->text('fiscal_citizen_qr')->nullable();
            }
            if (!Schema::hasColumn($t->getTable(), 'fiscal_response')) {
                $t->json('fiscal_response')->nullable();
            }
            if (!Schema::hasColumn($t->getTable(), 'fiscal_error')) {
                $t->text('fiscal_error')->nullable();
            }
            if (!Schema::hasColumn($t->getTable(), 'fiscalized_at')) {
                $t->timestamp('fiscalized_at')->nullable();
            }
            if (!Schema::hasColumn($t->getTable(), 'atk_transaction_no')) {
                $t->bigInteger('atk_transaction_no')->nullable();
            }
          if (!Schema::hasColumn($t->getTable(), 'idempotency_key')) {
            $t->char('idempotency_key', 64)->nullable();
            $t->unique('idempotency_key', 'fiscal_coupons_idempotency_key_unique');
}
        });
    }

    public function down(): void
    {
        // Intentionally left empty — dropping fiscal columns destroys audit data
    }
};
