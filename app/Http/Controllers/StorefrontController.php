<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Menu;
use App\Models\Pesanan;
use App\Models\Reservasi;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StorefrontController extends Controller
{
    public function index(): View
    {
        $menus = Menu::query()
            ->where('tersedia', true)
            ->orderBy('kategori')
            ->orderBy('nama_menu')
            ->get();

        $categories = $menus->pluck('kategori')->filter()->unique()->values();
        $recentReservations = Reservasi::query()
            ->with('customer')
            ->latest('created_at')
            ->limit(8)
            ->get();

        return view('welcome', compact('menus', 'categories', 'recentReservations'));
    }

    public function storeReservation(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'no_telepon' => ['required', 'string', 'max:30'],
            'tanggal_reservasi' => ['required', 'date', 'after_or_equal:'.now('Asia/Jakarta')->toDateString()],
            'waktu_reservasi' => ['required', 'date_format:H:i'],
            'jumlah_orang' => ['required', 'integer', 'between:1,99'],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = $this->authenticatedCustomer($request);

        DB::transaction(function () use ($validated, $user): void {
            $customer = $this->resolveCustomer(
                $validated['nama'],
                $validated['email'] ?? null,
                $validated['no_telepon'],
                $user,
            );

            Reservasi::create([
                'customer_id' => $customer->id,
                'tanggal_reservasi' => $validated['tanggal_reservasi'],
                'waktu_reservasi' => $validated['waktu_reservasi'],
                'jumlah_orang' => $validated['jumlah_orang'],
                'status' => 'menunggu',
                'catatan' => $validated['catatan'] ?? null,
            ]);
        });

        return back()->with('status', 'Permintaan reservasi diterima. Kami akan segera menghubungi Anda.');
    }

    public function storeOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'no_telepon' => ['required', 'string', 'max:30'],
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*' => ['required', 'array:menu_id,quantity'],
            'items.*.menu_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('menus', 'id')->where('tersedia', true),
            ],
            'items.*.quantity' => ['required', 'integer', 'between:1,50'],
        ]);

        $user = $this->authenticatedCustomer($request);

        $order = DB::transaction(function () use ($validated, $user): Pesanan {
            $menus = Menu::query()
                ->whereIn('id', collect($validated['items'])->pluck('menu_id'))
                ->where('tersedia', true)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($menus->count() !== count($validated['items'])) {
                throw ValidationException::withMessages([
                    'items' => 'Salah satu menu sudah tidak tersedia. Silakan perbarui keranjang Anda.',
                ]);
            }

            $customer = $this->resolveCustomer(
                $validated['nama'],
                $validated['email'] ?? null,
                $validated['no_telepon'],
                $user,
            );

            $order = Pesanan::create([
                'kode_pesanan' => 'TK-'.Str::upper((string) Str::ulid()),
                'customer_id' => $customer->id,
                'total_harga' => 0,
                'status' => 'menunggu',
            ]);
            $totalInCents = 0;

            foreach ($validated['items'] as $item) {
                $menu = $menus->get($item['menu_id']);
                $quantity = (int) $item['quantity'];
                $unitPriceInCents = (int) round((float) $menu->harga * 100);
                $subtotalInCents = $unitPriceInCents * $quantity;
                $totalInCents += $subtotalInCents;

                $order->detailPesanans()->create([
                    'menu_id' => $menu->id,
                    'nama_menu' => $menu->nama_menu,
                    'jumlah' => $quantity,
                    'harga_satuan' => number_format($unitPriceInCents / 100, 2, '.', ''),
                    'subtotal' => number_format($subtotalInCents / 100, 2, '.', ''),
                ]);
            }

            $order->update([
                'total_harga' => number_format($totalInCents / 100, 2, '.', ''),
            ]);

            return $order;
        });

        return response()->json([
            'message' => 'Pesanan Anda berhasil diterima.',
            'code' => $order->kode_pesanan,
        ], 201);
    }

    private function authenticatedCustomer(Request $request): ?User
    {
        $user = $request->user();

        return $user?->is_active && $user->role === User::ROLE_CUSTOMER ? $user : null;
    }

    private function resolveCustomer(string $name, ?string $email, string $phone, ?User $user): Customer
    {
        if ($user !== null) {
            $customer = Customer::query()->firstOrNew(['user_id' => $user->id]);
            $name = $user->name;
            $email = $user->email;
        } else {
            if ($email !== null && Customer::query()->where('email', $email)->whereNotNull('user_id')->exists()) {
                $email = null;
            }

            $customerQuery = Customer::query()->whereNull('user_id');
            $customer = $email !== null
                ? (clone $customerQuery)->where('email', $email)->first()
                : null;

            if ($customer === null) {
                $customer = (clone $customerQuery)->where('no_telepon', $phone)->first();
            }
        }

        $customer ??= new Customer;

        $customer->fill([
            'user_id' => $user?->id,
            'nama' => $name,
            'email' => $email,
            'no_telepon' => $phone,
        ])->save();

        return $customer;
    }
}
