<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('name', 120);
            $table->string('email', 150);
            $table->string('subject', 150)->nullable();
            $table->text('message');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->enum('status', ['unread', 'read', 'replied'])->default('unread');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
