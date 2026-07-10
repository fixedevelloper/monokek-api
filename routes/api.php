<?php

use App\Http\Controllers\Api\Admin\AccountingController;
use App\Http\Controllers\Api\Admin\CommissionController;
use App\Http\Controllers\Api\Admin\IngredientController;
use App\Http\Controllers\Api\Admin\LicenseController;
use App\Http\Controllers\Api\Admin\ModifierController;
use App\Http\Controllers\Api\Admin\PrinterController;
use App\Http\Controllers\Api\Admin\PurchaseOrderController;
use App\Http\Controllers\Api\Admin\RecipeController;
use App\Http\Controllers\Api\Admin\ReservationController;
use App\Http\Controllers\Api\Admin\SettingsController;
use App\Http\Controllers\Api\Pos\CashController;
use App\Http\Controllers\Api\PrintQueueController;
use App\Models\CashRegister;
use App\Models\PrintQueue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Pos\ProductController;
use App\Http\Controllers\Api\Pos\OrderController;
use App\Http\Controllers\Api\Pos\TableController;
use App\Http\Controllers\Api\Kitchen\TicketController;
use App\Http\Controllers\Api\Admin\ReportController;
use App\Http\Controllers\Api\Admin\InventoryController;
use App\Http\Controllers\Api\Admin\StaffController;

// --- ROUTES PUBLIQUES ---
Route::post('login', [AuthController::class, 'login']);
Route::get('/ping', function () {
    return response()->json(['status' => 'ok']);
});
Route::post('/license/activate', [LicenseController::class, 'activate']);
Route::get('/license/status', [LicenseController::class, 'status']);
Route::post('/print-queue/{job}/dispatch-network', [PrintQueueController::class, 'dispatchNetwork']);
// --- ROUTES PROTÉGÉES (SANCTUM) ---
Route::middleware(['auth:sanctum'])->group(function () {

    Route::post('/auth/update-pin', [AuthController::class, 'updatePin']);
    Route::post('/auth/update-password', [AuthController::class, 'updatePassword']);
    // AUTH - Actions supplémentaires
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('auth/verify-pin', [AuthController::class, 'verifyPin']);
    Route::get('me', function (Request $request) {
        return $request->user();
    });

    // ESPACE POS (Point de Vente)
    Route::prefix('pos')->group(function () {
        // Produits
        Route::get('products', [ProductController::class, 'index']);

        // Tables & Plan de salle
        Route::get('tables', [TableController::class, 'index']);
        Route::get('floors', [TableController::class, 'floors']);
        Route::put('floors/{floor}', [TableController::class, 'updateFloor']);
        Route::post('floors', [TableController::class, 'storeFloor']);
        Route::patch('tables/{table}/status', [TableController::class, 'updateStatus']);
        Route::post('tables/transfer', [TableController::class, 'transfer']);


        // Commandes
        Route::get('orders', [OrderController::class, 'index']);
        Route::get('orders/history', [OrderController::class, 'history']);
        Route::get('waiter/orders', [OrderController::class, 'waiterOrders']);
        Route::post('orders', [OrderController::class, 'store']);
        Route::post('orders/send-round', [OrderController::class, 'sendRound']);
        Route::post('orders/{order}/finalize', [OrderController::class, 'finalizePayment']);
        Route::post('orders/{order}/serve', [OrderController::class, 'markAsServed']);
        Route::get('/tables/{table}/active-order', [OrderController::class, 'getActiveOrder']);
    });
    Route::prefix('cash')->group(function () {
        Route::get('/registers', function () {
            return CashRegister::all();
        });

        // Enregistrer une nouvelle caisse
        Route::post('/registers', [CashController::class, 'storeRegister']);
        // Vérifier l'état de la caisse au chargement de l'app
        Route::get('/status', [CashController::class, 'status']);

        // Ouvrir la caisse
        Route::post('/open', [CashController::class, 'open']);

        // Obtenir le résumé en temps réel (X-Report)
        Route::get('/current-summary', [CashController::class, 'currentSummary']);

        // Fermer la caisse (Z-Report)
        Route::post('/close', [CashController::class, 'close']);
    });
    // ESPACE CUISINE (KDS - Kitchen Display System)
    Route::prefix('kitchen')->group(function () {
        Route::get('stations', [TicketController::class, 'getStations']);
        Route::get('tickets', [TicketController::class, 'index']);
        Route::patch('tickets/{ticket}/status', [TicketController::class, 'updateStatus']);
        Route::patch('tickets/items/{item}/status', [TicketController::class, 'updateItemStatus']);
        Route::patch('tickets-direct/{ticket}/status', [TicketController::class, 'updateStatusDirect']);
    });
    Route::get('/admin/settings', [SettingsController::class, 'index']);
    // ESPACE ADMIN (Accès restreint par rôle)
    // Note: Assure-toi que ton middleware 'role' est bien enregistré dans Kernel.php
    Route::middleware(['auth:sanctum', 'role:admin|manager'])
        ->prefix('admin')
        ->group(function () {
            Route::post('products', [ProductController::class, 'store']);
            Route::patch('products/{id}', [ProductController::class, 'update']);
            Route::delete('products/{id}', [ProductController::class, 'destroy']);
            Route::get('products', [ProductController::class, 'index']);
            Route::get('categories', [ProductController::class, 'categories']);
            Route::post('/products/bulk-import', [ProductController::class, 'bulkImport']);
            Route::post('/products/{product}/sync-modifiers', [ProductController::class, 'syncModifiers']);
          // Routes pour les groupes de modificateurs
            Route::apiResource('modifiers', ModifierController::class);
            Route::apiResource('reservations', ReservationController::class);
            Route::post('reservations/{id}/pay', [ReservationController::class, 'pay']);
            // Routes spécifiques pour les items (options)
            Route::post('modifiers/{modifier}/items', [ModifierController::class, 'addItem']);
            Route::delete('modifier-items/{item}', [ModifierController::class, 'destroyItem']);
            // Rapports & Analytics
            Route::get('reports/dashboard', [ReportController::class, 'dashboardStats']);
            Route::get('reports/categories', [ReportController::class, 'salesByCategory']);
            Route::get('analytics', [ReportController::class, 'getAnalytics']);
            Route::get('reports/closing', [ReportController::class, 'closingReport']);
            Route::apiResource('tables', TableController::class);
            Route::patch('tables/{table}', [TableController::class, 'updateStatus']);
            // Inventaire (CRUD complet + Ajustements)
            Route::apiResource('inventory', InventoryController::class);
            Route::post('inventory/{product}/adjust', [InventoryController::class, 'adjust']);
            Route::patch('inventory/{product}/threshold', [InventoryController::class, 'updateThreshold']);
            Route::get('/staff/roles', [StaffController::class, 'roles']);
            Route::apiResource('staff', StaffController::class);
            // Récupérer la liste brute des permissions (pour le PermissionsModal)
            Route::get('/staff/permissions/list', [StaffController::class, 'getAllPermissions']);
            Route::get('orders/history', [OrderController::class, 'historyAdmin']);
            // Mettre à jour les permissions spécifiques d'un membre
            Route::put('/staff/{uuid}/permissions', [StaffController::class, 'updatePermissions']);

            // Resource complète : index, store, show, update, destroy
            // On utilise {staff:uuid} pour que Laravel fasse le binding auto sur la colonne uuid
            Route::apiResource('staff', StaffController::class)->parameters([
                'staff' => 'staff:uuid'
            ]);
            Route::get('units', [IngredientController::class, 'units']);
            Route::get('stock-movements', [IngredientController::class, 'mouvements']);
            Route::apiResource('ingredients', IngredientController::class);
            Route::post('ingredients/{ingredient}/adjust', [IngredientController::class, 'adjustStock']);

            Route::post('products/{product}/recipe', [RecipeController::class, 'store']);

            Route::apiResource('purchase-orders', PurchaseOrderController::class);
            Route::get('products/{product}/recipe', [RecipeController::class, 'show']);
            Route::post('products/{product}/recipe', [RecipeController::class, 'store']);
            Route::apiResource('commissions', CommissionController::class);
            Route::post('/commissions/settle/{waiterId}', [CommissionController::class, 'settle']);

            Route::post('/settings', [SettingsController::class, 'update']);
            Route::post('/settings', [SettingsController::class, 'update']);
            Route::prefix('settings')->group(function () {
                Route::apiResource('printers', PrinterController::class);
                Route::get('printers/{printer}/test', [PrinterController::class, 'testConnection']);
            });

        });
});

Route::post('/sales/{order}/reprint',[OrderController::class,'reprint']);
Route::middleware(['auth:sanctum'])->prefix('accounting')->group(function () {
    Route::get('/sales-summary', [AccountingController::class, 'salesSummary']);
    Route::get('/payments-breakdown', [AccountingController::class, 'paymentsBreakdown']);
    Route::get('/detailed-sales', [AccountingController::class, 'detailedSales']);
    Route::get('/categories-sales', [AccountingController::class, 'categoriesSales']);
});
Route::get('/print-queue/pending', function() {
    return PrintQueue::where('status', 'pending')->with('printer')->get();
});

Route::post('/print-queue/{job}/mark-success', function(PrintQueue $job) {
    $job->update(['status' => 'completed']);
    return response()->noContent();
});
Route::post('/print-queue/{job}/mark-failed', function(PrintQueue $job) {
    $job->update(['status' => 'failed']);
    return response()->noContent();
});
