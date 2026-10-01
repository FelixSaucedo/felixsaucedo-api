<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->validateAdminCredentials();

        DB::transaction(function (): void {
            $this->seedAdmin();
            $this->call(PortfolioSeeder::class);
        });
    }

    private function validateAdminCredentials(): void
    {
        $email = config('portfolio.admin.email');
        $password = config('portfolio.admin.password');
        if (! $email && ! $password) {
            return;
        }
        if (! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)
            || ! is_string($password) || mb_strlen($password) < 16) {
            throw new RuntimeException('Configura PORTFOLIO_ADMIN_EMAIL y PORTFOLIO_ADMIN_PASSWORD de al menos 16 caracteres.');
        }
    }

    private function seedAdmin(): void
    {
        $email = config('portfolio.admin.email');
        if (! $email) {
            $this->command?->warn('No se creó administrador: faltan las credenciales PORTFOLIO_ADMIN_* .');

            return;
        }
        $admin = User::query()->firstOrNew(['email' => mb_strtolower(trim($email))]);
        $admin->name = config('portfolio.admin.name');
        $admin->role = UserRole::SuperAdmin;
        if (! $admin->exists) {
            $admin->password = config('portfolio.admin.password');
            $admin->email_verified_at = now();
        }
        $admin->save();
    }
}
