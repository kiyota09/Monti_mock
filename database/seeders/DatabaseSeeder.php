<?php

namespace Database\Seeders;

use App\Models\Core\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $faker = fake();

        // ==========================================
        // 1. SEED USERS (Your existing logic)
        // ==========================================
        $roles = ['HRM', 'SCM', 'FIN', 'MAN', 'INV', 'ORD', 'WAR', 'CRM', 'ECO'];
        $positions = ['manager', 'staff'];
        $crmUserIds = []; // We'll save CRM user IDs to assign leads to them later

        foreach ($roles as $role) {
            foreach ($positions as $position) {
                $email = strtolower($role.'.'.$position.'@montierp.com');

                $user = User::updateOrCreate(
                    ['email' => $email],
                    [
                        'name' => $faker->name(),
                        'role' => $role,
                        'position' => $position,
                        'password' => Hash::make('password123'),
                        'email_verified_at' => now(),
                        'is_active' => true,
                    ]
                );

                if ($role === 'CRM') {
                    $crmUserIds[] = $user->id;
                }
            }
        }

        // ==========================================
        // 2. SEED APPLICANTS (HR Module)
        // ==========================================
        $applicants = [];
        $noticePeriods = ['immediate', '15_days', '30_days', '60_days'];
        for ($i = 0; $i < 50; $i++) {
            $applicants[] = [
                'first_name' => $faker->firstName(),
                'last_name' => $faker->lastName(),
                'email' => $faker->unique()->safeEmail(),
                'phone_number' => $faker->phoneNumber(),
                'street_address' => $faker->streetAddress(),
                'city' => $faker->city(),
                'state_province' => $faker->state(),
                'postal_zip_code' => $faker->postcode(),
                'position_applied' => $faker->randomElement(['Machine Operator', 'Quality Control', 'Warehouse Staff', 'Sales Agent', 'Accountant']),
                'expected_salary' => $faker->numberBetween(15000, 50000),
                'notice_period' => $faker->randomElement($noticePeriods),
                'textile_experience' => $faker->randomElement(['yes', 'no']),
                'status' => $faker->randomElement(['Pending', 'Interviewed', 'Hired', 'Rejected']),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('applicants')->insert($applicants);

        // ==========================================
        // 3. SEED CLIENTS (CRM/Sales Module)
        // ==========================================
        $clients = [];
        for ($i = 0; $i < 30; $i++) {
            $clients[] = [
                'company_name' => $faker->company(),
                'business_type' => $faker->randomElement(['Retail', 'Wholesale', 'Manufacturing', 'Fashion Brand']),
                'tin_number' => $faker->unique()->numerify('###-###-###-000'),
                'contact_person' => $faker->name(),
                'email' => $faker->unique()->companyEmail(),
                'password' => Hash::make('password123'),
                'phone' => $faker->phoneNumber(),
                'company_address' => $faker->address(),
                'city' => $faker->city(),
                'status' => $faker->randomElement(['active', 'active', 'active', 'pending', 'suspended']),
                'credit_limit' => $faker->randomFloat(2, 10000, 500000),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('clients')->insert($clients);

        // ==========================================
        // 4. SEED CRM LEADS (CRM Module)
        // ==========================================
        $leads = [];
        $leadStatuses = ['Inquiry', 'Negotiation', 'Approval Sent', 'Closed-Won', 'Lost', 'Converted'];
        for ($i = 0; $i < 40; $i++) {
            $leads[] = [
                'company_name' => $faker->company(),
                'contact_person' => $faker->name(),
                'email' => $faker->unique()->safeEmail(),
                'phone' => $faker->phoneNumber(),
                'interest_fabric' => $faker->randomElement(['Cotton', 'Polyester', 'Silk', 'Denim', 'Linen', 'Spandex']),
                'estimated_value' => $faker->randomFloat(2, 5000, 100000),
                'status' => $faker->randomElement($leadStatuses),
                'assigned_staff_id' => $faker->randomElement($crmUserIds) ?? 1, // Fallback to 1 if empty
                'created_at' => $faker->dateTimeBetween('-6 months', 'now'),
                'updated_at' => now(),
            ];
        }
        DB::table('crm_leads')->insert($leads);

        // ==========================================
        // 5. SEED SUPPLIERS & WAREHOUSES (Inventory)
        // ==========================================
        $suppliers = [];
        for ($i = 0; $i < 10; $i++) {
            $suppliers[] = [
                'business_name' => $faker->company().' Textiles',
                'representative_name' => $faker->name(),
                'address' => $faker->address(),
                'email' => $faker->unique()->companyEmail(),
                'phone_number' => $faker->phoneNumber(),
                'password' => Hash::make('password123'),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('suppliers')->insert($suppliers);

        $warehouses = [
            ['name' => 'Main Facility', 'location' => 'General Trias, Cavite', 'manager' => $faker->name(), 'color' => 'blue', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'North Hub', 'location' => 'Valenzuela City', 'manager' => $faker->name(), 'color' => 'green', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'South Storage', 'location' => 'Laguna', 'manager' => $faker->name(), 'color' => 'red', 'created_at' => now(), 'updated_at' => now()],
        ];
        DB::table('warehouses')->insert($warehouses);

        // ==========================================
        // 6. SEED MATERIALS & PRODUCTS (SCM/Manufacturing)
        // ==========================================
        $materials = [];
        for ($i = 0; $i < 20; $i++) {
            $materials[] = [
                'mat_id' => 'MAT-'.$faker->unique()->numerify('####'),
                'name' => $faker->colorName().' '.$faker->randomElement(['Thread', 'Dye', 'Cotton Roll', 'Polyester Blend', 'Buttons']),
                'category' => $faker->randomElement(['Raw Fabric', 'Chemicals', 'Accessories']),
                'unit' => $faker->randomElement(['kg', 'meters', 'liters', 'pieces']),
                'reorder_point' => $faker->numberBetween(50, 200),
                'unit_cost' => $faker->randomFloat(2, 5, 500),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('materials')->insert($materials);

        $products = [];
        for ($i = 0; $i < 25; $i++) {
            $cost = $faker->randomFloat(2, 50, 300);
            $products[] = [
                'product_id' => 'PRD-'.$faker->unique()->numerify('####'),
                'sku' => strtoupper($faker->unique()->lexify('???-####')),
                'name' => $faker->words(3, true),
                'category' => $faker->randomElement(['Apparel', 'Industrial Fabric', 'Upholstery']),
                'status' => 'Active',
                'unit_cost' => $cost,
                'selling_price' => $cost * 1.5, // 50% markup
                'stock_on_hand' => $faker->numberBetween(0, 1000),
                'description' => $faker->sentence(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('products')->insert($products);

        // ==========================================
        // 7. SEED IT MODULE (service desk, assets, monitoring, KB, changes)
        // ==========================================
        $this->call(ItSeeder::class);
    }
}
