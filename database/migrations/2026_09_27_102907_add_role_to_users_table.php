<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('customer')->index();
            $table->string('no_telepon', 30)->nullable();
            $table->boolean('is_active')->default(true);
        });

        $demoUser = DB::table('users')->where('email', 'test@example.com')->first();

        if ($demoUser !== null && Hash::check('password', $demoUser->password)) {
            DB::table('users')->where('id', $demoUser->id)->update(['is_active' => false]);
        }

        foreach ([
            'owners' => 'owner',
            'karyawans' => 'operator',
        ] as $table => $role) {
            DB::table($table)->orderBy('id')->each(function (object $account) use ($role): void {
                if (DB::table('users')->where('email', $account->email)->exists()) {
                    return;
                }

                $isDefaultPassword = password_get_info($account->password)['algoName'] !== 'unknown'
                    && Hash::check('password', $account->password);

                DB::table('users')->insert([
                    'name' => $account->nama,
                    'email' => $account->email,
                    'password' => $account->password,
                    'role' => $role,
                    'no_telepon' => $account->no_telepon,
                    'is_active' => ! $isDefaultPassword && ($role !== 'operator' || (bool) $account->aktif),
                    'created_at' => $account->created_at,
                    'updated_at' => $account->updated_at,
                ]);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn(['role', 'no_telepon', 'is_active']);
        });
    }
};
