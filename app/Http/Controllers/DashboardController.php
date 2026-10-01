<?php

namespace App\Http\Controllers;

use App\Models\DetailPesanan;
use App\Models\InventoryItem;
use App\Models\Pesanan;
use App\Models\Reservasi;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = request()->user();
        $canViewInventory = in_array($user->role, [User::ROLE_OWNER, User::ROLE_ADMIN], true);
        $canViewRevenue = in_array($user->role, [User::ROLE_OWNER, User::ROLE_ADMIN], true);

        $pendingReservationCount = in_array($user->role, [User::ROLE_ADMIN, User::ROLE_OPERATOR], true)
            ? Reservasi::query()->where('status', 'menunggu')->count()
            : 0;

        $data = [
            'pendingOrderCount' => Pesanan::query()->where('status', 'menunggu')->count(),
            'pendingReservationCount' => $pendingReservationCount,
            'todayOrderCount' => Pesanan::query()->whereDate('created_at', today())->count(),
            'todayRevenue' => $canViewRevenue
                ? Pesanan::query()
                    ->whereIn('status', ['diproses', 'selesai'])
                    ->whereDate('created_at', today())
                    ->sum('total_harga')
                : 0,
            'canViewInventory' => $canViewInventory,
            'recentOrders' => Pesanan::query()
                ->with('customer')
                ->latest()
                ->limit(6)
                ->get(),
            'recentReservations' => in_array($user->role, [User::ROLE_ADMIN, User::ROLE_OPERATOR], true)
                ? Reservasi::query()
                    ->with('customer')
                    ->latest('created_at')
                    ->limit(5)
                    ->get()
                : collect(),
        ];

        if ($canViewInventory) {
            $data += [
                'activeItemCount' => InventoryItem::query()->where('is_active', true)->count(),
                'lowStockCount' => InventoryItem::query()
                    ->where('is_active', true)
                    ->whereColumn('quantity', '<=', 'minimum_quantity')
                    ->count(),
                'lowStockItems' => InventoryItem::query()
                    ->where('is_active', true)
                    ->whereColumn('quantity', '<=', 'minimum_quantity')
                    ->orderBy('quantity')
                    ->limit(6)
                    ->get(),
                'recentMovements' => StockMovement::query()
                    ->with(['inventoryItem', 'user'])
                    ->latest()
                    ->limit(7)
                    ->get(),
            ];
        }

        return view('portal.dashboard', $data);
    }

    public function reservations(): View
    {
        $user = request()->user();

        $reservations = in_array($user->role, [User::ROLE_ADMIN, User::ROLE_OPERATOR], true)
            ? Reservasi::query()->with('customer')->latest('created_at')->get()
            : collect();

        return view('portal.reservations.index', [
            'pendingReservationCount' => $reservations->where('status', 'menunggu')->count(),
            'reservations' => $reservations,
        ]);
    }

    public function updateReservationStatus(Request $request, Reservasi $reservasi)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['menunggu', 'diproses', 'selesai', 'dibatalkan'])],
        ]);

        $reservasi->update(['status' => $validated['status']]);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => $reservasi->status,
                'message' => 'Status reservasi berhasil diperbarui.',
            ]);
        }

        return redirect()->route('reservations.index')->with('status', 'Status reservasi berhasil diperbarui.');
    }

    public function destroyReservation(Reservasi $reservasi)
    {
        $reservasi->delete();

        return redirect()->route('reservations.index')->with('status', 'Reservasi berhasil dihapus.');
    }

    public function report(Request $request): View
    {
        $validated = $request->validate([
            'days' => ['sometimes', 'integer', Rule::in([7, 30, 90])],
        ]);
        $days = (int) ($validated['days'] ?? 30);
        $startDate = today()->subDays($days - 1);

        $ordersByDay = Pesanan::query()
            ->selectRaw(
                'DATE(created_at) as report_date, COUNT(*) as order_count, SUM(CASE WHEN status IN (?, ?) THEN total_harga ELSE 0 END) as revenue',
                ['diproses', 'selesai'],
            )
            ->where('created_at', '>=', $startDate)
            ->groupByRaw('DATE(created_at)')
            ->orderBy('report_date')
            ->get();

        $popularItems = DetailPesanan::query()
            ->whereHas('pesanan', fn (Builder $query) => $query->where('created_at', '>=', $startDate))
            ->select('nama_menu')
            ->selectRaw('SUM(jumlah) as quantity_sold, SUM(subtotal) as revenue')
            ->groupBy('nama_menu')
            ->orderByDesc('quantity_sold')
            ->limit(8)
            ->get();

        return view('portal.reports.index', [
            'days' => $days,
            'startDate' => $startDate,
            'ordersByDay' => $ordersByDay,
            'popularItems' => $popularItems,
            'totalOrders' => $ordersByDay->sum('order_count'),
            'totalRevenue' => $ordersByDay->sum('revenue'),
            'lowStockCount' => InventoryItem::query()
                ->where('is_active', true)
                ->whereColumn('quantity', '<=', 'minimum_quantity')
                ->count(),
        ]);
    }
}
