<?php

namespace Database\Seeders;

use App\Models\Vendor;
use App\Models\Company;
use App\Models\Country;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class VendorSeeder extends Seeder
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

        $vendors = [
            [
                'name' => 'Office Supplies Co.',
                'email' => 'sales@officesupplies.com',
                'phone' => '+1-555-1111',
                'tax_id' => 'US111111111',
                'payment_terms' => 30,
                'billing_address' => [
                    'street' => '100 Supply Street',
                    'city' => 'Boston',
                    'state' => 'MA',
                    'postal_code' => '02108',
                    'country' => 'United States',
                ],
                'shipping_address' => [
                    'street' => '100 Supply Street',
                    'city' => 'Boston',
                    'state' => 'MA',
                    'postal_code' => '02108',
                    'country' => 'United States',
                ],
            ],
            [
                'name' => 'Tech Hardware Inc.',
                'email' => 'orders@techhardware.com',
                'phone' => '+1-555-2222',
                'tax_id' => 'US222222222',
                'payment_terms' => 15,
                'billing_address' => [
                    'street' => '200 Hardware Way',
                    'city' => 'Seattle',
                    'state' => 'WA',
                    'postal_code' => '98101',
                    'country' => 'United States',
                ],
                'shipping_address' => [
                    'street' => '200 Hardware Way',
                    'city' => 'Seattle',
                    'state' => 'WA',
                    'postal_code' => '98101',
                    'country' => 'United States',
                ],
            ],
            [
                'name' => 'Software Solutions Ltd',
                'email' => 'partners@softwaresolutions.com',
                'phone' => '+1-555-3333',
                'tax_id' => 'US333333333',
                'payment_terms' => 45,
                'billing_address' => [
                    'street' => '300 Code Avenue',
                    'city' => 'San Jose',
                    'state' => 'CA',
                    'postal_code' => '95101',
                    'country' => 'United States',
                ],
                'shipping_address' => [
                    'street' => '300 Code Avenue',
                    'city' => 'San Jose',
                    'state' => 'CA',
                    'postal_code' => '95101',
                    'country' => 'United States',
                ],
            ],
            [
                'name' => 'Consulting Group LLC',
                'email' => 'services@consultinggroup.com',
                'phone' => '+1-555-4444',
                'tax_id' => 'US444444444',
                'payment_terms' => 60,
                'billing_address' => [
                    'street' => '400 Advisory Blvd',
                    'city' => 'Denver',
                    'state' => 'CO',
                    'postal_code' => '80201',
                    'country' => 'United States',
                ],
                'shipping_address' => [
                    'street' => '400 Advisory Blvd',
                    'city' => 'Denver',
                    'state' => 'CO',
                    'postal_code' => '80201',
                    'country' => 'United States',
                ],
            ],
            [
                'name' => 'Manufacturing Corp',
                'email' => 'wholesale@manufacturingcorp.com',
                'phone' => '+1-555-5555',
                'tax_id' => 'US555555555',
                'payment_terms' => 30,
                'billing_address' => [
                    'street' => '500 Factory Road',
                    'city' => 'Detroit',
                    'state' => 'MI',
                    'postal_code' => '48201',
                    'country' => 'United States',
                ],
                'shipping_address' => [
                    'street' => '500 Factory Road',
                    'city' => 'Detroit',
                    'state' => 'MI',
                    'postal_code' => '48201',
                    'country' => 'United States',
                ],
            ],
        ];

        foreach ($companies as $company) {
            foreach ($vendors as $index => $vendorData) {
                $vendorNumber = 'VEND-' . str_pad(($index + 1), 4, '0', STR_PAD_LEFT) . '-' . strtoupper(Str::random(4));
                
                Vendor::create([
                    'vendor_id' => (string) Str::uuid(),
                    'company_id' => $company->id,
                    'name' => $vendorData['name'],
                    'email' => $vendorData['email'],
                    'phone' => $vendorData['phone'],
                    'tax_number' => $vendorData['tax_id'],
                    'address' => json_encode($vendorData['billing_address']),
                    'currency_id' => null,
                    'is_active' => true,
                    'created_by' => null,
                    'updated_by' => null,
                ]);
            }
        }
    }
}
