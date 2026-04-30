<?php
namespace App\Events;

use App\Models\KitchenStation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class KitchenStationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

public $kitchenStation;
    // On définit explicitement la propriété pour éviter l'erreur
    public $pending_tickets_count; 

    public function __construct(KitchenStation $kitchenStation)
    {
        $this->kitchenStation = $kitchenStation;

        // On compte manuellement
        $this->pending_tickets_count = \DB::table('kitchen_tickets')
            ->where('station_id', $kitchenStation->id)
            ->whereIn('status', ['pending', 'preparing'])
            ->count();
    }

    public function broadcastOn(): array
    {
        // On peut diffuser sur un canal global ou spécifique à la station
        return [new Channel('kitchen.stations')];
    }

    public function broadcastAs(): string
    {
        return 'station.updated';
    }

public function broadcastWith(): array
    {
        return [
            'id' => $this->kitchenStation->id,
            'name' => $this->kitchenStation->name,
            // On utilise la propriété de la classe
            'pending_tickets_count' => (int) $this->pending_tickets_count
        ];
    }
}