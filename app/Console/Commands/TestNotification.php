<?php


namespace App\Console\Commands;

use App\Events\OrderCreated;
use App\Http\Resources\OrderResource;
use Illuminate\Console\Command;
use App\Models\Order;
use App\Models\Notification;
use App\Events\NotificationEvent;

class TestNotification extends Command
{
    protected $signature = 'test:notification {user_id=1} {table_id=1}';
    protected $description = 'Créer une commande test et envoyer une notification Reverb';

    public function handle()
    {
        $userId  = $this->argument('user_id');
        $tableId = $this->argument('table_id');

        $this->info("Création d'une commande pour user_id=$userId et table_id=$tableId...");

        // 1️⃣ Créer la commande
        $order = Order::create([
            'table_id'   => $tableId,
            'order_type' => 'dine_in',
            'status'     => 'pending',
            'total'      => 1000,
            'server_id'  => $userId,
        ]);

        $this->info("Commande #{$order->id} créée.");

        // 2️⃣ Créer la notification
        $notification = Notification::create([
            'recipient_type' => 'admin',
            'recipient_id'   => $userId,
            'order_id'       => $order->id,
            'title'          => 'Nouvelle commande',
            'message'        => "Une commande #{$order->id} vient d’être assignée à vous.",
            'status'         => 'sent',
            'sent_at'        => now(),
        ]);

        $this->info("Notification créée : {$notification->title}");

        $data=[
            'items'=>[]
        ];
        // 3️⃣ Diffuser l’événement
       // event(new NotificationEvent($notification));
        $payload = [
            'order_id' => $order->id,
            'table' => $order->table,
            'items' => $data['items'],
            'priority' => 'priority',
            'timestamp' => now()->toIso8601String(),
            'message_id' => (string) \Str::uuid(),
        ];
        logger((array)new OrderResource($order));
      //  broadcast(new OrderCreated((array)new OrderResource($order), $order->server_id));
        $orderResource = new OrderResource($order);

// Broadcast sur le channel privé correspondant au serveur / KDS
        broadcast(new OrderCreated($orderResource->toArray(null), $order->server_id));
        $this->info("Notification broadcastée avec succès !");
    }
}

