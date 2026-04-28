<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Company;
use App\Models\Branch;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::create([
            'name' => 'RestoPro Group',
            'phone' => '+237000000000',
            'email' => 'contact@restopro.com',
        ]);

        Branch::create([
            'company_id' => $company->id,
            'name' => 'Douala Centre',
            'address' => 'Akwa, Douala',
            'phone' => '+237000000001',
        ]);
    }
}