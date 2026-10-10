<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run()
    {
        $users = [
            [
                'name' => 'CEO',
                'email' => 'admin@skyinfinit.com',
                'password' => Hash::make('12345678'),
                'profile_img' => asset('default.jpg'),
            ],
        ];

        foreach ($users as $user) {
            $record = User::firstOrNew(['email' => $user['email']]);
            $record->fill($user);
            $record->save();
        }
    }
}
