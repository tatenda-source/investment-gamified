<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('portfolio_audit', function (Blueprint $table) {
            if (!Schema::hasColumn('portfolio_audit', 'created_at')) {
                return;
            }

            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::table('portfolio_audit', function (Blueprint $table) {
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            // Best-effort drop; ignore if missing
            try {
                $table->dropIndex(['created_at']);
            } catch (\Exception $e) {
            }
        });
    }
};
