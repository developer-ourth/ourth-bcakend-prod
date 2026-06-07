<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    /** @var array<string, array<string, string>> */
    private array $users = [
        'founder' => ['name' => 'Pranay Bhargava (Founder)',   'email' => 'pranay@healingourth.com'],
        'admin' => ['name' => 'Sahil (Admin)',               'email' => 'sahilbhargava6@gmail.com'],
        'operations' => ['name' => 'Suresh Nair (Operations)',    'email' => 'operations@ourth.app'],
        'waste_management' => ['name' => 'Deepak Rao (Waste Mgmt)',     'email' => 'waste@ourth.app'],
        'finance' => ['name' => 'Ananya Singh (Finance)',      'email' => 'finance@ourth.app'],
        'marketing' => ['name' => 'Meera Patel (Marketing)',     'email' => 'marketing@ourth.app'],
        'government' => ['name' => 'Government Officer',          'email' => 'government@ourth.app'],
        'vendor' => ['name' => 'Ravi Kumar (Vendor)',         'email' => 'vendor@ourth.app'],
        'consumer' => ['name' => 'Priya Sharma (Consumer)',     'email' => 'consumer@ourth.app'],
    ];

    public function run(): void
    {
        foreach ($this->users as $role => $attrs) {
            User::updateOrCreate(
                ['email' => $attrs['email']],
                [
                    'name' => $attrs['name'],
                    'password' => Hash::make('password123'),
                    'role' => $role,
                    'email_verified_at' => now(),
                ]
            );
        }

        $this->command->info('Users created successfully (password: password123):');
        foreach ($this->users as $role => $attrs) {
            $this->command->line("  [{$role}] {$attrs['email']}");
        }
    }
}
