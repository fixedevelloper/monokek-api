<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * base_migrations.php
 * Restaurant POS + Cuisine + Stock + Multi-role + LAN + Mobile
 * Ultra Professional Single File Migration Pack
 */

return new class extends Migration {
    public function up(): void
    {

        /*
        |--------------------------------------------------------------------------
        | USERS / SECURITY
        |--------------------------------------------------------------------------
        */

        /*         Schema::create('roles', function (Blueprint $table) {
                    $table->id();
                    $table->string('name')->unique(); // admin cashier waiter kitchen manager
                    $table->timestamps();
                });

                Schema::create('permissions', function (Blueprint $table) {
                    $table->id();
                    $table->string('name')->unique();
                    $table->timestamps();
                });

                Schema::create('role_permissions', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('role_id')->constrained()->cascadeOnDelete();
                    $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
                }); */

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('pin_code')->nullable();
            $table->string('email')->nullable()->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('device_name');
            $table->string('device_type'); // mobile,pos,kitchen
            $table->string('device_uuid')->unique();
            $table->string('ip_address')->nullable();
            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | BUSINESS / BRANCHES
        |--------------------------------------------------------------------------
        */

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->timestamps();
        });

        Schema::create('workstations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // caisse1 cuisine1
            $table->string('type'); // pos kitchen admin
            $table->string('ip')->nullable();
            $table->timestamps();
        });

        Schema::create('printers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type'); // escpos
            $table->string('connection'); // usb/lan
            $table->string('ip')->nullable();
            $table->string('port')->nullable();
            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | FLOOR / TABLES
        |--------------------------------------------------------------------------
        */

        Schema::create('floors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('restaurant_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('floor_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->integer('seats')->default(4);
            $table->string('status')->default('free');
            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | PRODUCTS
        |--------------------------------------------------------------------------
        */

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kitchen_station_id')->nullable();

            $table->string('name')->unique(); // Ex: Cuisine, Boissons, Entrées
            $table->string('slug')->unique(); // Ex: cuisine, boissons
            $table->string('description')->nullable();
            $table->string('icon')->nullable(); // Pour stocker le nom d'une icône Lucide
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('sku')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2);
            $table->decimal('incentive_amount', 10, 2)->nullable()->default(0);
            $table->string('image')->nullable();
            $table->enum('type', ['storable', 'consumable', 'service'])->default('storable');
            $table->integer('stock_count')->default(0);
            $table->integer('alert_stock')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('track_stock')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // Large Medium
            $table->decimal('price', 12, 2);
            $table->timestamps();
        });

        Schema::create('modifiers', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // extras
            $table->timestamps();
        });

        Schema::create('modifier_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('modifier_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('price', 12, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('modifier_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('modifier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['modifier_id', 'product_id']);
        });
        /*
        |--------------------------------------------------------------------------
        | CUSTOMERS
        |--------------------------------------------------------------------------
        */

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('phone')->nullable()->index();
            $table->string('email')->nullable();
            $table->integer('points')->default(0);
            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | ORDERS
        |--------------------------------------------------------------------------
        */

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('reference')->unique();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('table_id')->nullable()->constrained('restaurant_tables')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();//waiter_id
            $table->foreignId('cashier_id')->constrained('users')->cascadeOnDelete();
            $table->string('type')->default('dinein'); // takeaway delivery
            $table->enum('status', ['draft', 'pending_payment', 'pending', 'billing', 'reserved', 'paid', 'completed', 'cancelled', 'ready', 'preparing'])->default('draft');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('source')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'status']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->integer('qty');
            $table->decimal('price', 12, 2);
            $table->decimal('total', 12, 2);
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        Schema::create('order_item_modifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('modifier_item_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 12, 2)->default(0);
        });

        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');

            $table->dateTime('pickup_date'); // Date et heure de la venue
            $table->integer('guests_count')->default(1);
            $table->text('manager_notes')->nullable();
            $table->enum('reservation_status', ['confirmed', 'arrived', 'no_show'])->default('confirmed');

            $table->timestamps();
        });
        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('order_id')->constrained();
            $table->foreignId('order_item_id')->nullable()->constrained()->onDelete('cascade');

            $table->decimal('amount', 10, 2);
            $table->float('percentage')->nullable();

            // Type : 'global' (Option A) ou 'incentive' (Option C)
            $table->enum('type', ['global', 'incentive'])->default('global');

            $table->enum('status', ['pending', 'paid'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
        /*
        |--------------------------------------------------------------------------
        | KITCHEN
        |--------------------------------------------------------------------------
        */

        Schema::create('kitchen_stations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('kitchen_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('station_id')->constrained('kitchen_stations')->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | CASHIER / PAYMENTS
        |--------------------------------------------------------------------------
        */

        Schema::create('cash_registers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('register_id')->constrained('cash_registers')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('opening_amount', 12, 2);
            $table->decimal('closing_amount', 12, 2)->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->decimal('expected_amount', 12, 2)->nullable(); // Somme théorique calculée par le système
            $table->text('note')->nullable(); // Pour expliquer un écart à la fermeture
            $table->timestamps();
        });

        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // cash momo card
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_method_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cash_session_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->decimal('change_due', 12, 2);
            $table->decimal('amount_received', 12, 2);
            $table->string('reference')->nullable();
            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | STOCK
        |--------------------------------------------------------------------------
        */

        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // kg l pcs
        });

        Schema::create('ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('units');
            $table->string('name');
            $table->decimal('stock', 12, 3)->default(0);
            $table->decimal('alert_qty', 12, 3)->default(0);
            $table->timestamps();
        });

        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('recipe_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->decimal('qty', 12, 3);
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('draft');
            $table->decimal('total', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->decimal('qty', 12, 3);
            $table->decimal('price', 12, 2);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // in,out,adjust
            $table->decimal('qty', 12, 3);
            $table->text('reason')->nullable();
            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | PROMO / CRM
        |--------------------------------------------------------------------------
        */

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->decimal('amount', 12, 2);
            $table->timestamp('expires_at')->nullable();
        });

        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->integer('points');
            $table->string('type'); // earn/redeem
            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | LOGS / SETTINGS
        |--------------------------------------------------------------------------
        */

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('action');
            $table->timestamps();
        });

        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('device_uuid');
            $table->string('status');
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        $tables = [
            'sync_logs',
            'activity_logs',
            'settings',
            'loyalty_transactions',
            'coupons',
            'stock_movements',
            'purchase_order_items',
            'purchase_orders',
            'suppliers',
            'recipe_items',
            'recipes',
            'ingredients',
            'units',
            'payments',
            'payment_methods',
            'cash_sessions',
            'cash_registers',
            'kitchen_tickets',
            'kitchen_stations',
            'order_status_histories',
            'order_item_modifiers',
            'order_items',
            'orders',
            'customers',
            'modifier_items',
            'modifiers',
            'product_variants',
            'products',
            'categories',
            'restaurant_tables',
            'floors',
            'printers',
            'workstations',
            'branches',
            'companies',
            'devices',
            'user_roles',
            'users',
            'role_permissions',
            'permissions',
            'roles'
        ];

        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }

        Schema::enableForeignKeyConstraints();
    }
};
