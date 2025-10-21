<?php

declare(strict_types=1);

use App\MatchMaking\Models\Party;
use App\User\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parties', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class, 'leader_id')->constrained();
            $table->string('mode');
            $table->string('status');
            $table->timestamps();
        });
        Schema::create('party_members', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained();
            $table->foreignIdFor(Party::class)->constrained();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('party_members');
        Schema::dropIfExists('parties');
    }
};
