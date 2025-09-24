<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\Customer;
use App\Models\Vendor;
use Illuminate\Database\Seeder;

class ContactSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $firstNames = [
            'John', 'Jane', 'Michael', 'Sarah', 'David', 'Emily', 'Robert', 'Lisa',
            'James', 'Mary', 'William', 'Patricia', 'Richard', 'Jennifer', 'Joseph',
            'Linda', 'Thomas', 'Elizabeth', 'Charles', 'Barbara',
        ];

        $lastNames = [
            'Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller',
            'Davis', 'Rodriguez', 'Martinez', 'Hernandez', 'Lopez', 'Gonzalez',
            'Wilson', 'Anderson', 'Thomas', 'Taylor', 'Moore', 'Jackson', 'Martin',
        ];

        $positions = [
            'CEO', 'CFO', 'Accounting Manager', 'Purchasing Manager', 'Accounts Payable Specialist',
            'Accounts Receivable Specialist', 'Financial Controller', 'Director of Finance',
            'Office Manager', 'Procurement Officer', 'Finance Manager', 'Bookkeeper',
        ];

        $emailDomains = ['example.com', 'company.com', 'business.com', 'corp.com', 'llc.com'];

        // Create contacts for customers
        Customer::all()->each(function ($customer) use ($firstNames, $lastNames, $positions, $emailDomains) {
            // Create 1-3 contacts per customer
            $contactCount = rand(1, 3);

            for ($i = 0; $i < $contactCount; $i++) {
                $firstName = $firstNames[array_rand($firstNames)];
                $lastName = $lastNames[array_rand($lastNames)];
                $emailDomain = $emailDomains[array_rand($emailDomains)];

                Contact::create([
                    'company_id' => $customer->company_id,
                    'customer_id' => $customer->customer_id,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => strtolower($firstName.'.'.$lastName.'@'.$emailDomain),
                    'phone' => '+1-555-'.str_pad(rand(100, 999), 3, '0', STR_PAD_LEFT),
                    'position' => $positions[array_rand($positions)],
                    'notes' => $i === 0 ? 'Primary contact' : null,
                ]);
            }
        });

        // Create contacts for vendors
        Vendor::all()->each(function ($vendor) use ($firstNames, $lastNames, $positions, $emailDomains) {
            // Create 1-2 contacts per vendor
            $contactCount = rand(1, 2);

            for ($i = 0; $i < $contactCount; $i++) {
                $firstName = $firstNames[array_rand($firstNames)];
                $lastName = $lastNames[array_rand($lastNames)];
                $emailDomain = $emailDomains[array_rand($emailDomains)];

                Contact::create([
                    'company_id' => $vendor->company_id,
                    'vendor_id' => $vendor->vendor_id,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => strtolower($firstName.'.'.$lastName.'@'.$emailDomain),
                    'phone' => '+1-555-'.str_pad(rand(100, 999), 3, '0', STR_PAD_LEFT),
                    'position' => $positions[array_rand($positions)],
                    'notes' => $i === 0 ? 'Primary contact' : null,
                ]);
            }
        });

        $this->command->info('Contacts seeded successfully!');
    }
}
