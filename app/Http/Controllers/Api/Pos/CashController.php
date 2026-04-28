<?php

namespace App\Http\Controllers\Api\Pos;

use App\Http\Controllers\Controller;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CashController extends Controller
{
    // App/Http/Controllers/Api/CashController.php

public function storeRegister(Request $request)
{
    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'branch_id' => 'required|exists:branches,id',
    ]);

    $register = CashRegister::create($validated);

    return response()->json([
        'message' => 'Caisse enregistrée avec succès',
        'register' => $register
    ], 201);
}
    /**
     * Vérifier si l'utilisateur a une session ouverte
     */
    public function status()
    {
        $session = CashSession::with('register')
            ->where('user_id', auth()->id())
            ->whereNull('closed_at')
            ->first();

        return response()->json([
            'is_open' => !!$session,
            'session' => $session
        ]);
    }

    /**
     * Ouvrir une nouvelle session de caisse
     */
    public function open(Request $request)
    {
        logger($request->all());
        $request->validate([
            'register_id' => 'required|exists:cash_registers,id',
            'opening_amount' => 'required|numeric|min:0',
        ]);

        // Vérifier si une session est déjà ouverte
        $existing = CashSession::where('user_id', auth()->id())
            ->whereNull('closed_at')
            ->first();

        if ($existing) {
            return response()->json(['message' => 'Une caisse est déjà ouverte'], 422);
        }

        $session = CashSession::create([
            'register_id' => $request->register_id,
            'user_id' => auth()->id(),
            'opening_amount' => $request->opening_amount,
            'opened_at' => now(),
        ]);

        return response()->json([
            'message' => 'Caisse ouverte avec succès',
            'session' => $session->load('register')
        ], 201);
    }

    /**
     * Fermer la session de caisse (X-Report)
     */
    public function close(Request $request)
    {
        $request->validate([
            'closing_amount' => 'required|numeric|min:0', // Montant réel compté
            'note' => 'nullable|string'
        ]);

        $session = CashSession::where('user_id', auth()->id())
            ->whereNull('closed_at')
            ->first();

        if (!$session) {
            return response()->json(['message' => 'Aucune session active trouvée'], 404);
        }

        // Calculer le montant théorique attendu
        // Total = Fond de caisse + Somme des paiements
        $totalPayments = Payment::where('cash_session_id', $session->id)->sum('amount');
        $expectedAmount = $session->opening_amount + $totalPayments;

        $session->update([
            'closing_amount' => $request->closing_amount,
            'expected_amount' => $expectedAmount,
            'closed_at' => now(),
            'note' => $request->note
        ]);

        return response()->json([
            'message' => 'Caisse fermée avec succès',
            'report' => [
                'opened_at' => $session->opened_at,
                'closed_at' => $session->closed_at,
                'opening_amount' => $session->opening_amount,
                'total_payments' => $totalPayments,
                'expected_total' => $expectedAmount,
                'actual_total' => $request->closing_amount,
                'difference' => $request->closing_amount - $expectedAmount,
                'note' => $request->note
            ]
        ]);
    }

    /**
     * Obtenir le récapitulatif actuel sans fermer (Z-Report)
     */
    public function currentSummary()
    {
        $session = CashSession::where('user_id', auth()->id())
            ->whereNull('closed_at')
            ->first();

        if (!$session) return response()->json(['message' => 'Caisse fermée'], 404);

        // Détail par méthode de paiement (Cash, MoMo, Orange, etc.)
        $paymentsByMethod = Payment::where('cash_session_id', $session->id)
            ->join('payment_methods', 'payments.payment_method_id', '=', 'payment_methods.id')
            ->select('payment_methods.name', DB::raw('SUM(amount) as total'))
            ->groupBy('payment_methods.name')
            ->get();

        return response()->json([
            'session' => $session,
            'payments_detail' => $paymentsByMethod,
            'total_sales' => $paymentsByMethod->sum('total')
        ]);
    }
}