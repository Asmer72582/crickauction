<?php

use App\Services\RegistrationFormService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('registration_forms', 'auction_id')) {
            Schema::table('registration_forms', function (Blueprint $table) {
                $table->foreignId('auction_id')
                    ->nullable()
                    ->after('tournament_id')
                    ->constrained('auctions')
                    ->nullOnDelete();
            });
        }

        app(RegistrationFormService::class)->assignExclusiveAuctionForms();

        $indexName = 'registration_forms_auction_id_unique';
        $hasUnique = collect(Schema::getIndexes('registration_forms'))->contains(
            fn (array $index) => ($index['name'] ?? '') === $indexName
                || (($index['unique'] ?? false) && ($index['columns'] ?? []) === ['auction_id'])
        );
        if (! $hasUnique) {
            Schema::table('registration_forms', function (Blueprint $table) use ($indexName) {
                $table->unique('auction_id', $indexName);
            });
        }
    }

    public function down(): void
    {
        Schema::table('registration_forms', function (Blueprint $table) {
            $table->dropUnique(['auction_id']);
            $table->dropConstrainedForeignId('auction_id');
        });
    }
};
