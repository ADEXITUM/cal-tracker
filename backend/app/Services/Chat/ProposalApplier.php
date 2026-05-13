<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Models\ChatMessage;
use App\Models\DayEntry;
use App\Models\Meal;
use App\Models\User;
use App\Support\LogicalDate;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProposalApplier
{
    /**
     * Apply (approve or reject) one or more proposals in an assistant message.
     * Returns the updated message with status flipped per applied tool_use_id.
     *
     * On approve: writes a Meal for proposal.target_user, fills meal_uuid in the block.
     * Already-finalized blocks are silently skipped (idempotent for double-tap UX).
     *
     * @param  list<array{tool_use_id: string, action: string}>  $actions
     */
    public function apply(ChatMessage $assistantMessage, array $actions): ChatMessage
    {
        if ($assistantMessage->role !== ChatMessage::ROLE_ASSISTANT) {
            throw new InvalidArgumentException('Not an assistant message.');
        }

        $content = $assistantMessage->content;
        $indexById = [];
        foreach ($content as $i => $block) {
            if (($block['type'] ?? '') === 'tool_use') {
                $indexById[(string) ($block['id'] ?? '')] = $i;
            }
        }

        DB::transaction(function () use (&$content, $indexById, $actions) {
            foreach ($actions as $action) {
                $id = $action['tool_use_id'] ?? '';
                $verb = $action['action'] ?? '';
                if (!isset($indexById[$id])) {
                    throw new InvalidArgumentException("Tool use id not found: {$id}");
                }
                $i = $indexById[$id];
                $block = $content[$i];

                // Idempotency: if the block is no longer pending, ignore.
                if (($block['status'] ?? '') !== 'pending') {
                    continue;
                }

                if ($verb === 'approve') {
                    $meal = $this->writeMealFromProposal($block);
                    $content[$i]['status']    = 'approved';
                    $content[$i]['meal_uuid'] = $meal->uuid;
                } elseif ($verb === 'reject') {
                    $content[$i]['status'] = 'rejected';
                } else {
                    throw new InvalidArgumentException("Unknown action: {$verb}");
                }
            }
        });

        $assistantMessage->content = $content;
        $assistantMessage->save();

        return $assistantMessage;
    }

    /**
     * @param  array<string, mixed>  $block  a tool_use block with a `result` payload
     */
    private function writeMealFromProposal(array $block): Meal
    {
        $r = $block['result'] ?? null;
        if (!is_array($r)) {
            throw new InvalidArgumentException('Proposal has no result payload.');
        }

        $targetUuid = $r['target_user']['uuid'] ?? null;
        $targetUser = $targetUuid ? User::where('uuid', $targetUuid)->first() : null;
        if (!$targetUser) {
            throw new InvalidArgumentException('Target user no longer exists.');
        }

        $eatenAt = Carbon::parse((string) ($r['eaten_at'] ?? now()->toIso8601String()))
            ->setTimezone($targetUser->timezone ?? 'UTC');

        // Использовать логическую дату с ночным гэпом — meal съеденный в 02:30
        // относится к day_entry за предыдущий день. Без этого чат записывал бы
        // ночные приёмы в новый календарный день, расходясь с UI приложения.
        $logicalDate = LogicalDate::forInstant($eatenAt, $targetUser->timezone ?? 'UTC')->toDateString();

        $entry = DayEntry::firstOrCreate(
            ['user_id' => $targetUser->id, 'date' => $logicalDate],
        );

        return Meal::create([
            'day_entry_id' => $entry->id,
            'user_id'      => $targetUser->id,
            'dish_id'      => null,
            'slot'         => (string) ($r['slot'] ?? 'other'),
            'eaten_at'     => $eatenAt,
            'grams'        => (float) ($r['eaten_grams'] ?? 0),
            'name'         => (string) ($r['label'] ?? 'Без названия'),
            'kcal'         => (float) ($r['kcal'] ?? 0),
            'protein_g'    => (float) ($r['protein_g'] ?? 0),
            'fat_g'        => (float) ($r['fat_g'] ?? 0),
            'carbs_g'      => (float) ($r['carbs_g'] ?? 0),
        ]);
    }
}
