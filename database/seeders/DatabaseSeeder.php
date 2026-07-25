<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ServiceInstance;
use App\Models\ServiceType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Create main user
        $user = User::create([
            'name' => 'Brian Lucas',
            'email' => 'brianlucas67@gmail.com',
            'password' => Hash::make('admin@admin'),
            'role' => 'management',
            'hourly_rate' => 25.00,
        ]);

        // 2. Create Company
        $company = Company::create([
            'user_id' => $user->id,
            'name' => 'Lucas Cleaning Services',
            'email' => 'brianlucas67@gmail.com',
            'phone' => '+44 7123 456789',
            'address' => '123 High Street, London, EC1A 1BB',
            'payment_name' => 'Lucas Services Ltd',
            'payment_account_number' => '12345678',
            'payment_sort_code' => '12-34-56',
            'default_invoice_message' => 'Thank you for choosing Lucas Cleaning Services. Payment is due within 14 days.',
        ]);

        $user->update(['company_id' => $company->id]);

        // 3. Create Collaborators
        $john = User::create([
            'company_id' => $company->id,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password'),
            'role' => 'collaborator',
            'hourly_rate' => 12.50,
        ]);

        $mary = User::create([
            'company_id' => $company->id,
            'name' => 'Mary Smith',
            'email' => 'mary@example.com',
            'password' => Hash::make('password'),
            'role' => 'collaborator',
            'hourly_rate' => 14.00,
        ]);

        // 4. Create Service Types
        $regular = ServiceType::create(['company_id' => $company->id, 'name' => 'Regular Cleaning']);
        $deep = ServiceType::create(['company_id' => $company->id, 'name' => 'Deep Cleaning']);
        $garden = ServiceType::create(['company_id' => $company->id, 'name' => 'Garden Maintenance']);
        $pool = ServiceType::create(['company_id' => $company->id, 'name' => 'Pool Maintenance']);

        // 5. Create Customers
        $robert = Customer::create([
            'company_id' => $company->id,
            'name' => 'Robert Johnson',
            'email' => 'robert@example.com',
            'phone' => '+44 7123 987654',
            'address' => '45 Park Lane, London, W1K 1PR',
            'is_active' => true,
        ]);

        $alice = Customer::create([
            'company_id' => $company->id,
            'name' => 'Alice Green',
            'email' => 'alice@example.com',
            'phone' => '+44 7987 654321',
            'address' => '89 Queen Road, Ashford, TN24 8HF',
            'is_active' => true,
        ]);

        // 6. Create Service Addresses
        $robertAddress = $robert->addresses()->create([
            'label' => 'Penthouse',
            'address' => '45 Park Lane, Flat 5',
            'city' => 'London',
            'zip_code' => 'W1K 1PR',
            'start_date' => '2026-07-01',
            'duration_hours' => 4.00,
            'hourly_rate' => 20.00,
            'is_active' => true,
        ]);

        $aliceAddress = $alice->addresses()->create([
            'label' => 'Main House',
            'address' => '89 Queen Road',
            'city' => 'Ashford',
            'zip_code' => 'TN24 8HF',
            'start_date' => '2026-07-01',
            'duration_hours' => 3.00,
            'hourly_rate' => 18.00,
            'is_active' => true,
        ]);

        // 7. Create Service Schedules
        $schedule1 = $robertAddress->schedules()->create([
            'service_type_id' => $regular->id,
            'recurrence_type' => 'weekly',
            'days_of_week' => [1, 3],
            'start_date' => '2026-07-01',
            'start_time' => '09:00',
            'is_active' => true,
        ]);
        $schedule1->users()->sync([$john->id]);

        $schedule2 = $robertAddress->schedules()->create([
            'service_type_id' => $garden->id,
            'recurrence_type' => 'fortnightly',
            'days_of_week' => [5],
            'start_date' => '2026-07-01',
            'start_time' => '14:00',
            'is_active' => true,
        ]);
        $schedule2->users()->sync([$john->id, $mary->id]);

        $schedule3 = $aliceAddress->schedules()->create([
            'service_type_id' => $regular->id,
            'recurrence_type' => 'weekly',
            'days_of_week' => [2],
            'start_date' => '2026-07-01',
            'start_time' => '10:00',
            'is_active' => true,
        ]);
        $schedule3->users()->sync([$mary->id]);

        // 8. Create Service Instances (Completed/Skipped/Scheduled)
        $inst1 = ServiceInstance::create([
            'company_id' => $company->id,
            'customer_id' => $robert->id,
            'service_address_id' => $robertAddress->id,
            'service_schedule_id' => $schedule1->id,
            'service_type_id' => $regular->id,
            'description' => 'Regular Cleaning',
            'original_date' => '2026-07-13',
            'date' => '2026-07-13',
            'time' => '09:00',
            'status' => 'completed',
            'duration_hours' => 4.00,
            'hourly_rate' => 20.00,
            'notes' => 'Everything went smoothly. Cleaned all rooms.',
        ]);
        $inst1->users()->sync([$john->id]);

        $inst2 = ServiceInstance::create([
            'company_id' => $company->id,
            'customer_id' => $alice->id,
            'service_address_id' => $aliceAddress->id,
            'service_schedule_id' => $schedule3->id,
            'service_type_id' => $regular->id,
            'description' => 'Regular Cleaning',
            'original_date' => '2026-07-14',
            'date' => '2026-07-14',
            'time' => '10:00',
            'status' => 'completed',
            'duration_hours' => 3.00,
            'hourly_rate' => 18.00,
            'notes' => 'Client requested focus on kitchen.',
        ]);
        $inst2->users()->sync([$mary->id]);

        $inst3 = ServiceInstance::create([
            'company_id' => $company->id,
            'customer_id' => $robert->id,
            'service_address_id' => $robertAddress->id,
            'service_schedule_id' => $schedule1->id,
            'service_type_id' => $regular->id,
            'description' => 'Regular Cleaning',
            'original_date' => '2026-07-15',
            'date' => '2026-07-15',
            'time' => '09:00',
            'status' => 'completed',
            'duration_hours' => 4.00,
            'hourly_rate' => 20.00,
            'notes' => 'All done. Trash taken out.',
        ]);
        $inst3->users()->sync([$john->id]);

        $inst4 = ServiceInstance::create([
            'company_id' => $company->id,
            'customer_id' => $robert->id,
            'service_address_id' => $robertAddress->id,
            'service_schedule_id' => $schedule2->id,
            'service_type_id' => $garden->id,
            'description' => 'Garden Maintenance',
            'original_date' => '2026-07-17',
            'date' => '2026-07-17',
            'time' => '14:00',
            'status' => 'completed',
            'duration_hours' => 4.00,
            'hourly_rate' => 20.00,
            'notes' => 'Mowed the lawn and trimmed hedges.',
        ]);
        $inst4->users()->sync([$john->id, $mary->id]);

        // 9. Create Invoices
        $invoice = Invoice::create([
            'company_id' => $company->id,
            'customer_id' => $robert->id,
            'number' => '1',
            'date' => '2026-07-16',
            'due_date' => '2026-07-30',
            'status' => 'paid',
            'total_amount' => 160.00,
            'notes' => $company->default_invoice_message,
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'service_instance_id' => $inst1->id,
            'description' => 'Regular Cleaning (13/07/2026)',
            'quantity' => 4.00,
            'unit_price' => 20.00,
            'amount' => 80.00,
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'service_instance_id' => $inst3->id,
            'description' => 'Regular Cleaning (15/07/2026)',
            'quantity' => 4.00,
            'unit_price' => 20.00,
            'amount' => 80.00,
        ]);
    }
}
