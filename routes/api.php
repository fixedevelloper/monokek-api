<?php

use App\Http\Controllers\Api\Admin\IngredientController;
use App\Http\Controllers\Api\Admin\PurchaseOrderController;
use App\Http\Controllers\Api\Admin\RecipeController;
use App\Http\Controllers\Api\Admin\SettingsController;
use App\Http\Controllers\Api\Pos\CashController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Imports des Controllers
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Pos\ProductController;
use App\Http\Controllers\Api\Pos\OrderController;
use App\Http\Controllers\Api\Pos\TableController;
use App\Http\Controllers\Api\Kitchen\TicketController;
use App\Http\Controllers\Api\Admin\ReportController;
use App\Http\Controllers\Api\Admin\InventoryController;
use App\Http\Controllers\Api\Admin\StaffController;

//Route::prefix('v1')->group(function () {

// --- ROUTES PUBLIQUES ---
Route::post('login', [AuthController::class, 'login'])->name('login');
Route::get('/ping', function () {
    return response()->json(['status' => 'ok']);
});
// --- ROUTES PROTÉGÉES (SANCTUM) ---
Route::middleware(['auth:sanctum'])->group(function () {

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
        Route::post('orders/request-bill', [OrderController::class, 'requestBill']);
        Route::post('orders/{order}/finalize', [OrderController::class, 'finalizePayment']);
        Route::post('orders/{order}/serve', [OrderController::class, 'markAsServed']);

    });
    Route::prefix('cash')->group(function () {
        Route::get('/registers', function () {
            return \App\Models\CashRegister::all();
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
        // Liste des stations (pour la page de sélection /kitchen)
        Route::get('stations', [TicketController::class, 'getStations']);

        // Liste des tickets pour une station précise
        Route::get('tickets', [TicketController::class, 'index']);

        // Mise à jour du statut d'un ticket complet (ex: de 'pending' à 'ready')
        // On utilise l'ID du KitchenTicket
        Route::patch('tickets/{ticket}/status', [TicketController::class, 'updateStatus']);

        // Mise à jour du statut d'un article précis dans un ticket
        // Utile si le cuisinier veut marquer un plat comme "en cours" individuellement
        Route::patch('tickets/items/{item}/status', [TicketController::class, 'updateItemStatus']);
    });

    // ESPACE ADMIN (Accès restreint par rôle)
    // Note: Assure-toi que ton middleware 'role' est bien enregistré dans Kernel.php
    Route::middleware(['auth:sanctum', 'role:admin|manager'])
        ->prefix('admin')
        ->group(function () {
            Route::post('products', [ProductController::class, 'store']);
            Route::get('products', [ProductController::class, 'index']);
            Route::get('categories', [ProductController::class, 'categories']);
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
            Route::get('/settings', [SettingsController::class, 'index']);
            Route::post('/settings', [SettingsController::class, 'update']);
        });
});
//});