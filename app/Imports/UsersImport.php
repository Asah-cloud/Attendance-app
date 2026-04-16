<?php

namespace App\Imports;

use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Row;

class UsersImport implements OnEachRow
{
    private $event;
    private $password;

    public function __construct(Event $event)
    {
        $this->event = $event;
        $this->password = Hash::make('password123'); // Default password
    }

    public function onRow(Row $row)
    {
        $data = $row->toArray();

        // Skip header or empty rows (Checking index 1 for Name)
        if (empty($data[1]) || strtolower(trim($data[1])) === 'name') {
            return null;
        }

        $id = trim($data[0]);
        $name = trim($data[1]);
        $category = !empty(trim($data[4] ?? '')) ? trim($data[4]) : 'Member';
        
        // --- PHONE NORMALIZATION LOGIC ---
        $rawPhone = !empty(trim($data[5] ?? '')) ? trim($data[5]) : null;
        $cleanPhone = null;

        if ($rawPhone) {
            // 1. Remove everything that isn't a number (spaces, +, dashes)
            $cleanPhone = preg_replace('/[^0-9]/', '', $rawPhone);
            
            // 2. Strip leading 233
            if (str_starts_with($cleanPhone, '233')) {
                $cleanPhone = substr($cleanPhone, 3);
            }
            
            // 3. Strip leading 0
            if (str_starts_with($cleanPhone, '0')) {
                $cleanPhone = substr($cleanPhone, 1);
            }
            
            // Now a number like +233 24 123 4567 becomes 241234567
        }

        $email = 'user'.$id.'@church.com';

        // 1. Create or Update the User
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'phone' => $cleanPhone, // Save the cleaned 9-digit version
                'category' => $category,
                'role' => 'member',      // Updated role to member
                'password' => $this->password,
            ]
        );

        // 2. Attach this user to the specific event
        $this->event->users()->syncWithoutDetaching([$user->id]);
    }
}