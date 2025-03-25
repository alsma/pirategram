<?php

declare(strict_types=1);

use App\Game\Models\GamePlayer;
use App\Game\Models\GameState;
use App\User\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_states', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->json('board');
            $table->timestamps();
            $table->foreignIdFor(GamePlayer::class, 'current_turn_game_player_id')->nullable();
        });

        Schema::create('game_players', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(GameState::class);
            $table->foreignIdFor(User::class);
            $table->unsignedTinyInteger('team_id');
            $table->unsignedTinyInteger('order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_players');
        Schema::dropIfExists('game_states');
    }
};
