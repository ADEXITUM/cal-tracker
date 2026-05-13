<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            // Null for assistant-role messages (AI is not a user). Set null on
            // user delete rather than cascading so the conversation survives
            // an admin being removed.
            $table->foreignId('sender_user_id')->nullable()->constrained('users')->nullOnDelete();
            // 'user' — text typed by some admin
            // 'assistant' — model response (text + optional tool_use blocks)
            $table->string('role', 16);
            // Anthropic-shaped content blocks. Examples:
            //   user      → [{type: 'text', text: '…'}]
            //   assistant → [{type: 'text', text: '…'},
            //                {type: 'tool_use', id, name, input, result, status}]
            // Approval state lives in the tool_use block:
            //   status: 'pending' | 'approved' | 'rejected' | 'error'
            //   meal_uuid set when approved.
            $table->jsonb('content');
            $table->timestamps();

            // Shared chat is sorted globally — id ascending suffices.
            // Sender index supports future "show only my proposals" filtering.
            $table->index('sender_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
