<?php

use App\Models\Message;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('messages')->select(['id', 'body'])->orderBy('id')->chunkById(200, function ($messages): void {
            foreach ($messages as $message) {
                $links = Message::extractLinks($message->body);
                if ($links !== []) {
                    DB::table('messages')->where('id', $message->id)->update(['links' => json_encode($links)]);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
