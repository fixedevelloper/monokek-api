<?php


namespace App\Http\Controllers\Api\Admin;


use App\Http\Controllers\Controller;
use App\Http\Services\CommissionService;
use App\Models\Commission;
use App\Http\Resources\CommissionResource;
use Illuminate\Http\Request;

class CommissionController extends Controller
{
// CommissionController.php

    public function index(Request $request)
    {
        $request->validate([
            'month' => 'nullable|integer|min:1|max:12',
            'year' => 'nullable|integer',
        ]);

        $commissions = Commission::with(['waiter', 'order', 'orderItem.product'])
            ->when($request->month, function ($query) use ($request) {
                return $query->whereMonth('created_at', $request->month);
            })
            ->when($request->year, function ($query) use ($request) {
                return $query->whereYear('created_at', $request->year);
            })
            ->latest()
            ->get(); // On peut enlever la pagination si on veut un rapport complet par mois

        return CommissionResource::collection($commissions);
    }

    public function getStats()
    {
        // Petite API pour un Dashboard rapide
        return response()->json([
            'total_pending' => Commission::pending()->sum('amount'),
            'total_paid' => Commission::where('status', 'paid')->sum('amount'),
            'top_waiter' => Commission::selectRaw('user_id, SUM(amount) as total')
                ->groupBy('user_id')
                ->with('waiter:id,name')
                ->orderByDesc('total')
                ->first()
        ]);
    }
    public function settle($waiterId, CommissionService $service) {
        $service->settleWaiterCommissions($waiterId);
        return response()->json(['message' => 'Commissions réglées']);
    }
}
