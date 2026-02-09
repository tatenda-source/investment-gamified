<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up()
    {
        Schema::create('portfolio_audit', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('stock_id')->nullable();
            $table->string('type', 10); // buy / sell
            $table->integer('quantity');
            $table->decimal('price', 14, 4);
            $table->decimal('total_amount', 18, 4);
            $table->json('portfolio_snapshot')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id']);
            $table->index(['stock_id']);

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('stock_id')->references('id')->on('stocks')->onDelete('set null');
        });

        // Create DB-level triggers to prevent UPDATE/DELETE on the audit table
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS portfolio_audit_no_update');
            DB::unprepared("CREATE TRIGGER portfolio_audit_no_update BEFORE UPDATE ON portfolio_audit FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'portfolio_audit is immutable'; END;");
            DB::unprepared('DROP TRIGGER IF EXISTS portfolio_audit_no_delete');
            DB::unprepared("CREATE TRIGGER portfolio_audit_no_delete BEFORE DELETE ON portfolio_audit FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'portfolio_audit is immutable'; END;");
        } elseif ($driver === 'sqlite') {
            // SQLite trigger using RAISE(ABORT, 'msg')
            DB::unprepared('CREATE TRIGGER IF NOT EXISTS portfolio_audit_no_update BEFORE UPDATE ON portfolio_audit BEGIN SELECT RAISE(ABORT, "portfolio_audit is immutable"); END;');
            DB::unprepared('CREATE TRIGGER IF NOT EXISTS portfolio_audit_no_delete BEFORE DELETE ON portfolio_audit BEGIN SELECT RAISE(ABORT, "portfolio_audit is immutable"); END;');
        } elseif ($driver === 'pgsql' || $driver === 'postgres') {
            DB::unprepared('CREATE OR REPLACE FUNCTION portfolio_audit_no_update() RETURNS trigger AS $$ BEGIN RAISE EXCEPTION ''portfolio_audit is immutable''; END; $$ LANGUAGE plpgsql;');
            DB::unprepared('CREATE TRIGGER portfolio_audit_no_update BEFORE UPDATE ON portfolio_audit FOR EACH ROW EXECUTE PROCEDURE portfolio_audit_no_update();');
            DB::unprepared('CREATE OR REPLACE FUNCTION portfolio_audit_no_delete() RETURNS trigger AS $$ BEGIN RAISE EXCEPTION ''portfolio_audit is immutable''; END; $$ LANGUAGE plpgsql;');
            DB::unprepared('CREATE TRIGGER portfolio_audit_no_delete BEFORE DELETE ON portfolio_audit FOR EACH ROW EXECUTE PROCEDURE portfolio_audit_no_delete();');
        }
    }

    public function down()
    {
        Schema::dropIfExists('portfolio_audit');
    }
};
