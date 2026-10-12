<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\EmploymentEvent;
use App\Models\Pesanan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_and_registration_pages_render(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Masuk ke akunmu');
        $this->get(route('register'))->assertOk()->assertSee('Buat akun pelanggan');
    }

    public function test_customer_registration_creates_a_linked_account_and_profile(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Maya Kerabat',
            'email' => 'maya@example.test',
            'no_telepon' => '081234567890',
            'password' => 'KerabatAman123',
            'password_confirmation' => 'KerabatAman123',
        ])->assertRedirectToRoute('account.orders.index');

        $this->assertAuthenticated();
        $user = User::query()->where('email', 'maya@example.test')->firstOrFail();

        $this->assertSame(User::ROLE_CUSTOMER, $user->role);
        $this->assertDatabaseHas('customers', [
            'user_id' => $user->id,
            'email' => 'maya@example.test',
            'nama' => 'Maya Kerabat',
        ]);
    }

    public function test_registration_links_previous_guest_orders_to_the_new_customer_account(): void
    {
        $guestCustomer = Customer::factory()->create([
            'email' => 'returning@example.test',
            'user_id' => null,
        ]);
        Pesanan::factory()->for($guestCustomer)->create(['kode_pesanan' => 'TK-RETURNING-GUEST']);

        $this->post(route('register.store'), [
            'name' => $guestCustomer->nama,
            'email' => 'returning@example.test',
            'no_telepon' => '081234567890',
            'password' => 'KerabatAman123',
            'password_confirmation' => 'KerabatAman123',
        ])->assertRedirectToRoute('account.orders.index');

        $user = User::query()->where('email', 'returning@example.test')->firstOrFail();

        $this->assertDatabaseHas('customers', [
            'id' => $guestCustomer->id,
            'user_id' => $user->id,
        ]);
        $this->get(route('account.orders.index'))
            ->assertOk()
            ->assertSee('TK-RETURNING-GUEST');
    }

    public function test_inactive_account_cannot_log_in(): void
    {
        User::factory()->create([
            'email' => 'inactive@example.test',
            'password' => 'KerabatAman123',
            'is_active' => false,
        ]);

        $this->post(route('login.store'), [
            'email' => 'inactive@example.test',
            'password' => 'KerabatAman123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_inactive_employee_with_valid_credentials_sees_account_notice_on_login(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $employee = User::factory()->create([
            'email' => 'inactive-employee@example.test',
            'role' => User::ROLE_OPERATOR,
            'password' => 'KerabatKaryawan123',
            'is_active' => false,
        ]);
        $employee->employmentEvents()->create([
            'actor_id' => $admin->id,
            'action' => 'dismissed',
            'note' => 'Maaf anda melanggar aturan',
        ]);

        $this->post(route('login.store'), [
            'email' => $employee->email,
            'password' => 'KerabatKaryawan123',
        ])->assertRedirectToRoute('account.inactive');

        $this->assertAuthenticatedAs($employee);
        $this->get(route('account.inactive'))
            ->assertOk()
            ->assertSee('Status akun ·')
            ->assertSee('Akun sedang dinonaktifkan.')
            ->assertSee('Akses ke ruang kerja dihentikan sementara. Hubungi administrator jika memerlukan informasi lebih lanjut.')
            ->assertSee('Catatan dari administrator ⚠️')
            ->assertSee('Maaf anda melanggar aturan')
            ->assertSee('Keluar dari akun');
    }

    public function test_inactive_employee_with_wrong_password_does_not_see_account_notice(): void
    {
        User::factory()->create([
            'email' => 'inactive-employee@example.test',
            'role' => User::ROLE_OPERATOR,
            'password' => 'KerabatKaryawan123',
            'is_active' => false,
        ]);

        $this->post(route('login.store'), [
            'email' => 'inactive-employee@example.test',
            'password' => 'PasswordYangSalah123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_customer_cannot_open_internal_dashboard(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        $this->actingAs($customer)->get(route('dashboard'))->assertForbidden();
    }

    public function test_operator_cannot_manage_accounts(): void
    {
        $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);

        $this->actingAs($operator)->get(route('users.index'))->assertForbidden();
    }

    public function test_admin_must_reset_a_default_password_before_activating_an_account(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $legacyUser = User::factory()->create([
            'role' => User::ROLE_OPERATOR,
            'is_active' => false,
            'password' => 'password',
        ]);

        $this->actingAs($admin)
            ->patch(route('users.status.update', $legacyUser))
            ->assertSessionHasErrors('user');

        $this->assertFalse($legacyUser->refresh()->is_active);

        $this->patch(route('users.rehire', $legacyUser), [
            'note' => 'Mencoba mengaktifkan kembali sebelum reset',
        ])->assertSessionHasErrors('user');
        $this->assertDatabaseCount('employment_events', 0);

        $this->patch(route('users.password.update', $legacyUser), [
            'password' => 'KerabatBaru123',
            'password_confirmation' => 'KerabatBaru123',
        ])->assertRedirectToRoute('users.index');

        $this->assertTrue(Hash::check('KerabatBaru123', $legacyUser->refresh()->password));
        $this->assertFalse($legacyUser->is_active);

        $this->patch(route('users.rehire', $legacyUser), [
            'note' => 'Password diperbarui, kembali bekerja ✅',
        ])->assertRedirectToRoute('users.index');
        $this->assertTrue($legacyUser->refresh()->is_active);
        $this->assertDatabaseHas('employment_events', [
            'user_id' => $legacyUser->id,
            'actor_id' => $admin->id,
            'action' => 'rehired',
            'note' => 'Password diperbarui, kembali bekerja ✅',
        ]);
    }

    public function test_admin_dismissal_and_rehire_require_a_note_and_create_audit_history(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $employee = User::factory()->create([
            'role' => User::ROLE_OPERATOR,
            'password' => 'KerabatKaryawan123',
        ]);

        $this->actingAs($admin)
            ->from(route('users.index'))
            ->patch(route('users.dismiss', $employee))
            ->assertRedirectToRoute('users.index')
            ->assertSessionHasErrors('note');

        $this->assertTrue($employee->refresh()->is_active);
        $this->assertDatabaseCount('employment_events', 0);

        $this->patch(route('users.dismiss', $employee), [
            'note' => 'Pelanggaran SOP kasir ⚠️',
        ])->assertRedirectToRoute('users.index');

        $this->assertFalse($employee->refresh()->is_active);
        $this->assertDatabaseHas('employment_events', [
            'user_id' => $employee->id,
            'actor_id' => $admin->id,
            'action' => 'dismissed',
            'note' => 'Pelanggaran SOP kasir ⚠️',
        ]);

        $this->patch(route('users.rehire', $employee), [
            'note' => 'Sanksi selesai, kembali bertugas ✅',
        ])->assertRedirectToRoute('users.index');

        $this->assertTrue($employee->refresh()->is_active);
        $this->assertDatabaseHas('employment_events', [
            'user_id' => $employee->id,
            'actor_id' => $admin->id,
            'action' => 'rehired',
            'note' => 'Sanksi selesai, kembali bertugas ✅',
        ]);
        $this->assertSame(2, EmploymentEvent::query()->where('user_id', $employee->id)->count());
    }

    public function test_dismissed_employee_sees_the_reason_and_only_has_access_to_logout(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $employee = User::factory()->create([
            'role' => User::ROLE_OPERATOR,
            'password' => 'KerabatKaryawan123',
        ]);

        $this->actingAs($admin)->patch(route('users.dismiss', $employee), [
            'note' => 'Perubahan jadwal kerja tidak disepakati.',
        ])->assertRedirectToRoute('users.index');

        $this->actingAs($employee->refresh())
            ->get(route('dashboard'))
            ->assertRedirectToRoute('account.inactive');

        $this->get(route('account.inactive'))
            ->assertOk()
            ->assertSee('Perubahan jadwal kerja tidak disepakati.')
            ->assertSee(route('logout'), false)
            ->assertDontSee(route('dashboard'), false)
            ->assertDontSee(route('profile.edit'), false)
            ->assertDontSee('<a ', false);

        $this->post(route('logout'))->assertRedirectToRoute('home');
        $this->assertGuest();
    }

    public function test_logged_in_employee_receives_dismissal_status_for_the_popup(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $employee = User::factory()->create([
            'role' => User::ROLE_OPERATOR,
            'password' => 'KerabatKaryawan123',
        ]);

        $this->actingAs($employee)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('account.employment-status'), false)
            ->assertSee('id="employmentNotice"', false);

        $this->actingAs($admin)->patch(route('users.dismiss', $employee), [
            'note' => 'Perubahan jadwal kerja tidak disepakati.',
        ])->assertRedirectToRoute('users.index');

        $this->actingAs($employee->refresh());
        $this->getJson(route('account.employment-status'))
            ->assertOk()
            ->assertExactJson([
                'is_active' => false,
                'event' => [
                    'action' => 'dismissed',
                    'note' => 'Perubahan jadwal kerja tidak disepakati.',
                ],
            ]);
    }

    public function test_rehired_employee_is_redirected_from_notice_to_the_dashboard(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $employee = User::factory()->create([
            'role' => User::ROLE_OPERATOR,
            'is_active' => false,
            'password' => 'KerabatKaryawan123',
        ]);
        $employee->employmentEvents()->create([
            'actor_id' => $admin->id,
            'action' => 'dismissed',
            'note' => 'Alasan pemberhentian lama.',
        ]);

        $this->actingAs($admin)->patch(route('users.rehire', $employee), [
            'note' => 'Akun diaktifkan kembali.',
        ])->assertRedirectToRoute('users.index');

        $this->actingAs($employee->refresh())
            ->get(route('account.inactive'))
            ->assertRedirectToRoute('dashboard')
            ->assertSessionHas('employment_notice');
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Akun diaktifkan kembali')
            ->assertSee('Akun diaktifkan kembali.');
    }

    public function test_rehired_employee_sees_the_notice_after_logging_in(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $employee = User::factory()->create([
            'role' => User::ROLE_OPERATOR,
            'is_active' => false,
            'password' => 'KerabatKaryawan123',
        ]);
        $employee->employmentEvents()->create([
            'actor_id' => $admin->id,
            'action' => 'dismissed',
            'note' => 'Alasan pemberhentian lama.',
        ]);

        $this->actingAs($admin)->patch(route('users.rehire', $employee), [
            'note' => 'Kembali bertugas setelah evaluasi.',
        ])->assertRedirectToRoute('users.index');
        $this->post(route('logout'))->assertRedirectToRoute('home');

        $this->post(route('login.store'), [
            'email' => $employee->email,
            'password' => 'KerabatKaryawan123',
        ])->assertRedirectToRoute('dashboard');

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Akun diaktifkan kembali')
            ->assertSee('Kembali bertugas setelah evaluasi.');
    }

    public function test_admin_can_recruit_an_operator_and_store_the_hiring_note(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Karyawan Baru',
            'email' => 'karyawan.baru@example.test',
            'no_telepon' => '081234567890',
            'role' => User::ROLE_OPERATOR,
            'password' => 'KerabatBaru123',
            'password_confirmation' => 'KerabatBaru123',
            'note' => 'Mulai shift pagi ☀️',
        ])->assertRedirectToRoute('users.index');

        $employee = User::query()->where('email', 'karyawan.baru@example.test')->firstOrFail();

        $this->assertDatabaseHas('employment_events', [
            'user_id' => $employee->id,
            'actor_id' => $admin->id,
            'action' => 'hired',
            'note' => 'Mulai shift pagi ☀️',
        ]);
    }

    public function test_operator_cannot_dismiss_an_employee(): void
    {
        $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);
        $employee = User::factory()->create(['role' => User::ROLE_OPERATOR]);

        $this->actingAs($operator)
            ->patch(route('users.dismiss', $employee), ['note' => 'Catatan tidak sah'])
            ->assertForbidden();

        $this->assertTrue($employee->refresh()->is_active);
        $this->assertDatabaseCount('employment_events', 0);
    }

    public function test_admin_employee_screen_displays_recruitment_and_dismissal_controls(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $employee = User::factory()->create([
            'name' => 'Karyawan Catatan',
            'role' => User::ROLE_OPERATOR,
        ]);
        $employee->employmentEvents()->create([
            'actor_id' => $admin->id,
            'action' => 'hired',
            'note' => 'Mulai kerja 🎉',
        ]);

        $this->actingAs($admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('Tambah anggota tim')
            ->assertSee('Catatan perekrutan')
            ->assertSee('Pemberhentian')
            ->assertSee('employmentHistoryModal', false)
            ->assertSee('accountManagementModal', false)
            ->assertSee('data-open-account-management', false)
            ->assertSee('Masuk sebagai pengguna')
            ->assertSee('accountDeleteModal', false)
            ->assertSee('data-open-employment-history', false)
            ->assertSee('Mulai kerja 🎉');
    }

    public function test_admin_can_search_accounts_by_name_email_and_phone(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $matchingUser = User::factory()->create([
            'name' => 'Akun Pencarian Khusus',
            'email' => 'pencarian.khusus@example.test',
            'no_telepon' => '081234567891',
        ]);
        User::factory()->create([
            'name' => 'Akun Tidak Cocok',
            'email' => 'lainnya@example.test',
            'no_telepon' => '089876543210',
        ]);

        foreach (['Pencarian Khusus', 'pencarian.khusus@example.test', '081234567891'] as $term) {
            $this->actingAs($admin)
                ->get(route('users.index', ['search' => $term]))
                ->assertOk()
                ->assertSee($matchingUser->email)
                ->assertDontSee('lainnya@example.test')
                ->assertSee('Daftar akun terdaftar')
                ->assertSee('account-search-icon', false)
                ->assertSee('aria-label="Daftar akun terdaftar"', false)
                ->assertSee('data-label="Tindakan"', false);
        }
    }

    public function test_account_search_can_return_only_the_updated_list_fragment(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $matchingUser = User::factory()->create([
            'name' => 'Akun Fragmen',
            'email' => 'fragmen@example.test',
        ]);
        User::factory()->create(['email' => 'lainnya@example.test']);

        $this->actingAs($admin)
            ->get(route('users.index', ['search' => 'fragmen', 'partial' => 1]))
            ->assertOk()
            ->assertSee('id="accountSearchResults"', false)
            ->assertSee($matchingUser->email)
            ->assertDontSee('lainnya@example.test')
            ->assertDontSee('portal-sidebar', false);
    }

    public function test_guest_cannot_open_employee_management_or_dismissal_routes(): void
    {
        $employee = User::factory()->create(['role' => User::ROLE_OPERATOR]);

        $this->get(route('users.index'))->assertRedirectToRoute('login');
        $this->patch(route('users.dismiss', $employee), ['note' => 'Akses tanpa sesi ❌'])
            ->assertRedirectToRoute('login');

        $this->assertTrue($employee->refresh()->is_active);
        $this->assertDatabaseCount('employment_events', 0);
    }

    public function test_customer_order_history_contains_only_the_authenticated_customers_orders(): void
    {
        $customerUser = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $otherUser = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $customer = Customer::factory()->create(['user_id' => $customerUser->id]);
        $otherCustomer = Customer::factory()->create(['user_id' => $otherUser->id]);
        $ownOrder = Pesanan::factory()->for($customer)->create(['kode_pesanan' => 'TK-OWN-ORDER']);
        Pesanan::factory()->for($otherCustomer)->create(['kode_pesanan' => 'TK-OTHER-ORDER']);

        $this->actingAs($customerUser)
            ->get(route('account.orders.index'))
            ->assertOk()
            ->assertSee('TK-OWN-ORDER')
            ->assertDontSee('TK-OTHER-ORDER');
    }

    public function test_admin_can_change_an_existing_users_role_and_records_operator_history(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $user = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        $this->actingAs($admin)->patch(route('users.role.update', $user), [
            'role' => User::ROLE_OPERATOR,
            'note' => 'Mulai bertugas di kasir.',
        ])->assertRedirectToRoute('users.index');

        $this->assertSame(User::ROLE_OPERATOR, $user->refresh()->role);
        $this->assertDatabaseHas('employment_events', [
            'user_id' => $user->id,
            'actor_id' => $admin->id,
            'action' => 'role_changed',
            'note' => 'Mulai bertugas di kasir.',
        ]);
    }

    public function test_admin_cannot_change_their_own_role_but_can_update_another_admin(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->patch(route('users.role.update', $admin), [
            'role' => User::ROLE_CUSTOMER,
        ])->assertSessionHasErrors('user');

        $this->assertSame(User::ROLE_ADMIN, $admin->refresh()->role);

        $anotherAdmin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->patch(route('users.role.update', $anotherAdmin), [
            'role' => User::ROLE_OWNER,
        ])->assertRedirectToRoute('users.index');

        $this->assertSame(User::ROLE_OWNER, $anotherAdmin->refresh()->role);
    }

    public function test_admin_can_delete_a_customer_without_deleting_their_order_history(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $user = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $customer = Customer::factory()->create(['user_id' => $user->id]);
        Pesanan::factory()->for($customer)->create(['kode_pesanan' => 'TK-KEPT-ORDER']);

        $this->actingAs($admin)->delete(route('users.destroy', $user))
            ->assertRedirectToRoute('users.index');

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'user_id' => null,
        ]);
        $this->assertDatabaseHas('pesanans', ['kode_pesanan' => 'TK-KEPT-ORDER']);
    }

    public function test_admin_cannot_delete_their_own_account_or_a_user_with_employment_audit(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $employee = User::factory()->create(['role' => User::ROLE_OPERATOR]);
        $employee->employmentEvents()->create([
            'actor_id' => $admin->id,
            'action' => 'hired',
            'note' => 'Riwayat kerja.',
        ]);

        $this->actingAs($admin)->delete(route('users.destroy', $admin))
            ->assertSessionHasErrors('user');
        $this->delete(route('users.destroy', $employee))
            ->assertSessionHasErrors('user');

        $this->assertModelExists($admin);
        $this->assertModelExists($employee);
        $this->assertDatabaseHas('employment_events', ['user_id' => $employee->id]);
    }

    public function test_admin_can_access_and_restore_their_account_session(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        $this->actingAs($admin)->post(route('users.impersonate.start', $customer))
            ->assertRedirectToRoute('account.orders.index');

        $this->assertAuthenticatedAs($customer);
        $this->get(route('account.orders.index'))
            ->assertOk()
            ->assertSee('Kembali ke administrator')
            ->assertSee(route('users.impersonate.stop'), false);

        $this->post(route('users.impersonate.stop'))->assertRedirectToRoute('users.index');
        $this->assertAuthenticatedAs($admin);
    }

    public function test_admin_cannot_access_admin_or_inactive_accounts_as_another_user(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $anotherAdmin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $inactiveUser = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->post(route('users.impersonate.start', $anotherAdmin))
            ->assertSessionHasErrors('user');
        $this->post(route('users.impersonate.start', $inactiveUser))
            ->assertSessionHasErrors('user');

        $this->assertAuthenticatedAs($admin);
    }

    public function test_non_admin_cannot_change_roles_delete_accounts_or_access_another_account(): void
    {
        $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        $this->actingAs($operator)
            ->patch(route('users.role.update', $customer), ['role' => User::ROLE_OWNER])
            ->assertForbidden();
        $this->delete(route('users.destroy', $customer))->assertForbidden();
        $this->post(route('users.impersonate.start', $customer))->assertForbidden();

        $this->assertSame(User::ROLE_CUSTOMER, $customer->refresh()->role);
        $this->assertModelExists($customer);
    }
}
