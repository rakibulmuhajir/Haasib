<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Company;
use App\Models\Country;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $companies = Company::all();
        $country = Country::first() ?: Country::create([
            'name' => 'United States',
            'code' => 'US',
            'iso3' => 'USA',
            'phone_code' => '+1',
            'currency_id' => null,
            'is_active' => true,
        ]);

        $customers = [
            [
                'name' => 'Acme Corporation',
                'email' => 'billing@acme.com',
                'phone' => '+1-555-0123',
                'tax_id' => 'US123456789',
                'billing_address' => [
                    'street' => '123 Business Ave',
                    'city' => 'New York',
                    'state' => 'NY',
                    'postal_code' => '10001',
                    'country' => 'United States',
                ],
            ],
            [
                'name' => 'Tech Solutions Inc.',
                'email' => 'accounts@techsolutions.com',
                'phone' => '+1-555-0456',
                'tax_id' => 'US987654321',
                'billing_address' => [
                    'street' => '456 Tech Park',
                    'city' => 'San Francisco',
                    'state' => 'CA',
                    'postal_code' => '94105',
                    'country' => 'United States',
                ],
            ],
            [
                'name' => 'Global Industries Ltd',
                'email' => 'finance@globalindustries.com',
                'phone' => '+1-555-0789',
                'tax_id' => 'US456789123',
                'billing_address' => [
                    'street' => '789 Commerce Blvd',
                    'city' => 'Chicago',
                    'state' => 'IL',
                    'postal_code' => '60601',
                    'country' => 'United States',
                ],
            ],
            [
                'name' => 'Innovate Systems',
                'email' => 'payments@innovatesystems.com',
                'phone' => '+1-555-0321',
                'tax_id' => 'US789123456',
                'billing_address' => [
                    'street' => '321 Innovation Drive',
                    'city' => 'Austin',
                    'state' => 'TX',
                    'postal_code' => '73301',
                    'country' => 'United States',
                ],
            ],
            [
                'name' => 'MegaCorp Enterprises',
                'email' => 'invoice@megacorp.com',
                'phone' => '+1-555-0654',
                'tax_id' => 'US321654987',
                'billing_address' => [
                    'street' => '654 Corporate Plaza',
                    'city' => 'Los Angeles',
                    'state' => 'CA',
                    'postal_code' => '90001',
                    'country' => 'United States',
                ],
            ],
        ];

        foreach ($companies as $company) {
            foreach ($customers as $index => $customerData) {
                Customer::create([
                    'customer_id' => (string) Str::uuid(),
                    'company_id' => $company->id,
                    'name' => $customerData['name'],
                    'email' => $customerData['email'],
                    'phone' => $customerData['phone'],
                    'tax_number' => $customerData['tax_id'],
                    'billing_address' => json_encode($customerData['billing_address']),
                    'currency_id' => null,
                    'is_active' => true,
                    'created_by' => null,
                    'updated_by' => null,
                ]);
            }
        }
    }
}
