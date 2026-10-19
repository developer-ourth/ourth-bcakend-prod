<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\KrayinClient;
use App\Support\PhoneNumber;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Creates the Krayin person + lead for a captured user, or adds a timeline
 * note when the user already has a lead (repeat enquiries, new ad clicks).
 */
class SyncLeadToKrayin implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(
        public int $userId,
        public string $sourceName,
        public string $note = '',
    ) {}

    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function handle(KrayinClient $krayin): void
    {
        if (! $krayin->isConfigured()) {
            return;
        }

        // Serialise per user so two webhooks for the same person cannot create two leads.
        Cache::lock("krayin-lead-sync:{$this->userId}", 60)->block(30, function () use ($krayin) {
            $user = User::find($this->userId);

            if (! $user) {
                return;
            }

            if ($user->krayin_lead_id) {
                $krayin->addNote($user->krayin_lead_id, trim("New enquiry via {$this->sourceName}. {$this->note}"));

                return;
            }

            $phone = PhoneNumber::toE164Digits($user->phone);
            $phone = $phone ? '+' . $phone : null;

            $ids = $krayin->createLead(
                title: ($user->name ?: 'Lead') . ' – ' . $this->sourceName,
                person: [
                    'name' => $user->name ?: 'Lead',
                    'phone' => $phone,
                    'email' => $user->email,
                    'person_id' => $user->krayin_person_id ?: $krayin->findPersonId($phone, $user->email),
                ],
                sourceName: $this->sourceName,
                description: trim(($user->crm_segment ? "Segment: {$user->crm_segment}\n" : '') . $this->note),
            );

            $user->forceFill([
                'krayin_lead_id' => $ids['lead_id'],
                'krayin_person_id' => $ids['person_id'] ?? $user->krayin_person_id,
            ])->save();
        });
    }

    public function failed(\Throwable $e): void
    {
        Log::error("Krayin lead sync failed for user #{$this->userId}: " . $e->getMessage());
    }
}
