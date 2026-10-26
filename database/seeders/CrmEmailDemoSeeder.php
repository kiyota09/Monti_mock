<?php

namespace Database\Seeders;

use App\Models\Crm\Client;
use App\Models\Crm\CrmEmailMessage;
use App\Models\Crm\CrmEmailThread;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds a handful of received client emails so the CRM Email inbox is not
 * empty on a fresh database. Safe to run repeatedly — it bails out when any
 * thread already exists.
 */
class CrmEmailDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (CrmEmailThread::exists()) {
            return;
        }

        $clients = Client::query()
            ->whereNotNull('email')
            ->orderBy('id')
            ->take(5)
            ->get();

        if ($clients->isEmpty()) {
            return;
        }

        $samples = [
            [
                'subject' => 'Request for fabric swatches',
                'body' => "Hi Monti Textiles team,\n\nWe are interested in your cotton and linen lines. Could you send us swatches and your current price list for bulk orders?\n\nThank you,\n",
            ],
            [
                'subject' => 'Follow-up on our quotation',
                'body' => "Good day,\n\nWe would like to follow up on the quotation you sent last week. Are the lead times still the same if we double the quantity?\n\nBest regards,\n",
            ],
            [
                'subject' => 'Partnership inquiry',
                'body' => "Hello,\n\nOur company is looking for a reliable fabric supplier for a new production run. Please let us know how we can become an accredited partner.\n\nThanks,\n",
            ],
            [
                'subject' => 'Sample order delivery',
                'body' => "Hi,\n\nJust checking on the delivery schedule of the sample order we placed. Kindly confirm once it ships.\n\nRegards,\n",
            ],
            [
                'subject' => 'Wholesale pricing question',
                'body' => "Dear team,\n\nWe are a retailer and would like to understand your wholesale tiers and minimum order quantities. Looking forward to your response.\n\nSincerely,\n",
            ],
        ];

        foreach ($clients as $index => $client) {
            $sample = $samples[$index % count($samples)];
            $sentAt = now()->subDays(count($samples) - $index)->subHours(3);

            $thread = CrmEmailThread::create([
                'client_id' => $client->id,
                'from_email' => strtolower($client->email),
                'from_name' => $client->contact_person ?: $client->company_name,
                'subject' => $sample['subject'],
                'last_message_preview' => Str::limit(trim(strip_tags($sample['body'])), 160),
                'last_message_at' => $sentAt,
            ]);

            CrmEmailMessage::create([
                'thread_id' => $thread->id,
                'direction' => 'inbound',
                'type' => 'inbound',
                'from_email' => strtolower($client->email),
                'from_name' => $client->contact_person ?: $client->company_name,
                'to_email' => config('mail.from.address'),
                'to_name' => config('mail.from.name'),
                'subject' => $sample['subject'],
                'body' => $sample['body'] . ($client->contact_person ?: $client->company_name),
                'message_id' => (string) Str::uuid(),
                'status' => 'received',
                'sent_at' => $sentAt,
                'created_at' => $sentAt,
                'updated_at' => $sentAt,
            ]);
        }
    }
}
