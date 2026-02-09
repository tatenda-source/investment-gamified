<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('portfolios', function (Blueprint $table) {
            $table->unsignedBigInteger('ledger_checkpoint_id')->nullable()->after('average_price');
            $table->string('checksum', 128)->nullable()->after('ledger_checkpoint_id');

            $table->foreign('ledger_checkpoint_id')->references('id')->on('portfolio_audit')->onDelete('set null');
            $table->index(['ledger_checkpoint_id']);
            $table->index(['checksum']);
        });
    }

    public function down()
    {
        Schema::table('portfolios', function (Blueprint $table) {
            $table->dropForeign(['ledger_checkpoint_id']);
            $table->dropIndex(['ledger_checkpoint_id']);
            $table->dropIndex(['checksum']);
            $table->dropColumn(['ledger_checkpoint_id', 'checksum']);
        });
    }
};
