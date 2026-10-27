<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Pesanan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DebitCreditReportController extends Controller
{
    public function index(Request $request): View
    {
        ['startDate' => $startDate, 'endDate' => $endDate] = $this->dateRange($request);

        $debitTotal = $this->salesQuery($startDate, $endDate)->sum('total_harga');
        $creditTotal = Expense::query()
            ->where('transaction_date', '>=', $startDate->toDateString())
            ->where('transaction_date', '<', $endDate->copy()->addDay()->toDateString())
            ->sum('amount');

        $transactions = $this->transactionsQuery($startDate, $endDate)
            ->paginate(20)
            ->withQueryString();

        return view('portal.reports.debit-credit', [
            'categories' => Expense::CATEGORIES,
            'creditTotal' => $creditTotal,
            'debitTotal' => $debitTotal,
            'endDate' => $endDate,
            'netBalanceCents' => $this->amountInCents($debitTotal) - $this->amountInCents($creditTotal),
            'startDate' => $startDate,
            'transactions' => $transactions,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        ['startDate' => $startDate, 'endDate' => $endDate] = $this->dateRange($request);
        $transactions = $this->transactionsQuery($startDate, $endDate);

        return response()->streamDownload(function () use ($transactions): void {
            $output = fopen('php://output', 'w');

            if ($output === false) {
                throw new \RuntimeException('Tidak dapat menyiapkan file ekspor laporan.');
            }

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Tanggal', 'Tipe', 'Kategori', 'Keterangan', 'Jumlah (Rp)'], ',', '"', '');

            foreach ($transactions->cursor() as $transaction) {
                fputcsv($output, [
                    $transaction->transaction_date,
                    strtoupper($transaction->transaction_type),
                    $this->spreadsheetSafe((string) $transaction->category),
                    $this->spreadsheetSafe((string) $transaction->description),
                    $transaction->amount,
                ], ',', '"', '');
            }

            fclose($output);
        }, sprintf(
            'laporan-debit-kredit-%s-sampai-%s.csv',
            $startDate->toDateString(),
            $endDate->toDateString(),
        ), ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array{startDate: Carbon, endDate: Carbon}
     */
    private function dateRange(Request $request): array
    {
        $validated = $request->validate([
            'start_date' => ['sometimes', 'required', 'date', 'before_or_equal:today'],
            'end_date' => [
                'sometimes',
                'required',
                'date',
                'before_or_equal:today',
                Rule::when(
                    $request->filled('start_date'),
                    ['after_or_equal:start_date'],
                ),
            ],
        ]);

        $endDate = Carbon::parse($validated['end_date'] ?? today()->toDateString())->endOfDay();
        $startDate = Carbon::parse(
            $validated['start_date'] ?? $endDate->copy()->startOfMonth()->toDateString(),
        )->startOfDay();

        return compact('startDate', 'endDate');
    }

    private function salesQuery(Carbon $startDate, Carbon $endDate): Builder
    {
        return Pesanan::query()
            ->whereIn('status', ['diproses', 'selesai'])
            ->whereBetween('created_at', [$startDate, $endDate]);
    }

    private function transactionsQuery(Carbon $startDate, Carbon $endDate): QueryBuilder
    {
        $sales = $this->salesQuery($startDate, $endDate)
            ->selectRaw(
                "'debit' AS transaction_type, id, kode_pesanan AS description, ? AS category, total_harga AS amount, DATE(created_at) AS transaction_date, created_at AS sort_date",
                ['Penjualan pesanan'],
            )
            ->toBase();

        $expenses = Expense::query()
            ->where('transaction_date', '>=', $startDate->toDateString())
            ->where('transaction_date', '<', $endDate->copy()->addDay()->toDateString())
            ->selectRaw(
                "'credit' AS transaction_type, id, description, category, amount, transaction_date, created_at AS sort_date",
            )
            ->toBase();

        return DB::query()
            ->fromSub($sales->unionAll($expenses), 'financial_transactions')
            ->orderByDesc('transaction_date')
            ->orderByDesc('sort_date')
            ->orderByDesc('id')
            ->orderBy('transaction_type');
    }

    private function spreadsheetSafe(string $value): string
    {
        return preg_match('/^[\x00-\x20]*[=+\-@]/', $value) === 1
            ? "'".$value
            : $value;
    }

    private function amountInCents(int|float|string $amount): int
    {
        $normalized = is_float($amount)
            ? number_format($amount, 2, '.', '')
            : (string) $amount;

        if (str_contains(strtolower($normalized), 'e')) {
            $normalized = number_format((float) $normalized, 2, '.', '');
        }

        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '00');
        $fraction = str_pad(substr($fraction, 0, 2), 2, '0');

        return (int) $whole * 100 + (int) $fraction;
    }
}
