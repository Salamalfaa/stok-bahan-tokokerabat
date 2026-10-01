<?php

namespace App\Http\Controllers;

use App\Models\Pesanan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerOrderController extends Controller
{
    public function index(Request $request): View
    {
        $orders = Pesanan::query()
            ->whereHas('customer', fn (Builder $query) => $query->where('user_id', $request->user()->id))
            ->with('detailPesanans')
            ->latest()
            ->paginate(10);

        return view('account.orders.index', compact('orders'));
    }
}
