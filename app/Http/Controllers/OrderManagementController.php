<?php

namespace App\Http\Controllers;

use App\Models\Pesanan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderManagementController extends Controller
{
    public function index(): View
    {
        $orders = Pesanan::query()
            ->with(['customer', 'detailPesanans'])
            ->where(function (Builder $query): void {
                $query->whereNotIn('status', ['selesai', 'dibatalkan'])
                    ->orWhere('created_at', '>', now()->subDay());
            })
            ->latest()
            ->paginate(15);

        return view('portal.orders.index', compact('orders'));
    }

    public function receipt(Pesanan $pesanan): View
    {
        $pesanan->load(['customer', 'detailPesanans']);

        return view('portal.orders.receipt', compact('pesanan'));
    }

    public function archive(Request $request): View
    {
        $selectedDate = $request->validate([
            'date' => ['sometimes', 'required', 'date_format:Y-m-d', 'before:today'],
        ])['date'] ?? now()->subDay()->toDateString();

        $orders = $this->archivedOrders($selectedDate)
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('portal.orders.archive', compact('orders', 'selectedDate'));
    }

    public function previewArchive(Request $request): View
    {
        $selectedDate = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'before:today'],
        ])['date'];
        $orders = $this->archivedOrders($selectedDate)
            ->latest('created_at')
            ->get();

        $summary = [
            'orderCount' => $orders->count(),
            'revenue' => $orders
                ->where('status', 'selesai')
                ->sum(fn (Pesanan $order): float => (float) $order->total_harga),
            'completedCount' => $orders->where('status', 'selesai')->count(),
            'cancelledCount' => $orders->where('status', 'dibatalkan')->count(),
        ];

        return view('portal.orders.archive-preview', compact('orders', 'selectedDate', 'summary'));
    }

    public function exportArchive(Request $request): StreamedResponse
    {
        $selectedDate = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'before:today'],
        ])['date'];

        return response()->streamDownload(function () use ($selectedDate): void {
            $output = fopen('php://output', 'w');

            if ($output === false) {
                return;
            }

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Tanggal WIB', 'Kode Pesanan', 'Pelanggan', 'No. Telepon', 'Detail Pesanan', 'Total (Rp)', 'Status']);

            $this->archivedOrders($selectedDate)
                ->orderBy('id')
                ->chunkById(200, function ($orders) use ($output): void {
                    foreach ($orders as $order) {
                        $details = $order->detailPesanans
                            ->map(fn ($detail): string => $detail->jumlah.' x '.$detail->nama_menu)
                            ->implode('; ');

                        fputcsv($output, [
                            $order->created_at->format('d/m/Y H:i'),
                            $this->safeCsvValue($order->kode_pesanan),
                            $this->safeCsvValue($order->customer?->nama),
                            $this->safeCsvValue($order->customer?->no_telepon),
                            $this->safeCsvValue($details),
                            number_format((float) $order->total_harga, 2, '.', ''),
                            $this->safeCsvValue($order->status),
                        ]);
                    }
                });

            fclose($output);
        }, 'rekap-pesanan-'.$selectedDate.'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function updateStatus(Request $request, Pesanan $pesanan): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['menunggu', 'diproses', 'selesai', 'dibatalkan'])],
        ]);

        $pesanan->update(['status' => $validated['status']]);

        return redirect()->route('orders.index')->with('status', 'Status pesanan berhasil diperbarui.');
    }

    /**
     * @return Builder<Pesanan>
     */
    private function archivedOrders(string $date): Builder
    {
        return Pesanan::query()
            ->with(['customer', 'detailPesanans'])
            ->whereIn('status', ['selesai', 'dibatalkan'])
            ->where('created_at', '<=', now()->subDay())
            ->whereDate('created_at', $date);
    }

    private function safeCsvValue(?string $value): string
    {
        $value ??= '';

        return preg_match('/^[=+@\-\t\r]/', $value) === 1 ? "'".$value : $value;
    }
}
