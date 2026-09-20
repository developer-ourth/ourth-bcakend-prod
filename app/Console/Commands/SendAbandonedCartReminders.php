<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Cart;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SendAbandonedCartReminders extends Command
{
    protected $signature = 'carts:send-abandoned-reminders';
    protected $description = 'Scan abandoned carts older than 15 minutes and send automated WhatsApp recovery messages';

    public function handle()
    {
        $cutoffTime = Carbon::now()->subMinutes(15);
        $maxCutoff = Carbon::now()->subHours(24);

        $abandonedCarts = Cart::with('user', 'items')
            ->where('updated_at', '<=', $cutoffTime)
            ->where('updated_at', '>=', $maxCutoff)
            ->whereHas('items')
            ->get();

        $count = 0;
        $wa = new WhatsAppService();

        foreach ($abandonedCarts as $cart) {
            $user = $cart->user;
            if (!$user || !$user->phone_number) continue;

            $name = $user->name ?: 'there';
            $success = $wa->sendAbandonedCartReminder($user->phone_number, $name);

            if ($success) {
                $count++;
            }
        }

        $this->info("Processed {$count} abandoned cart reminders.");
        Log::info("Abandoned cart scanner executed: {$count} reminders sent.");
        return Command::SUCCESS;
    }
}
