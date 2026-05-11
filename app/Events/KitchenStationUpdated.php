<?php

namespace App\Events;

use App\Models\KitchenStation;
use App\Models\KitchenTicket;
use Illuminate\Broadcasting\Channel;
use Illuminate\Support\Facades\DB;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class KitchenStationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $kitchenStation;

    /**
     * @param KitchenStation $kitchenStation
     */
    public function __construct(KitchenStation $kitchenStation)
    {
        // On passe l'instance de la station
        $this->kitchenStation = $kitchenStation;
    }

    /**
     * Les canaux sur lesquels l'événement doit être diffusé.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('kitchen.stations'),
            // Optionnel : canal spécifique pour ne pas saturer les autres stations
            new Channel('kitchen.station.' . $this->kitchenStation->id)
        ];
    }

    /**
     * Nom de l'événement diffusé.
     */
    public function broadcastAs(): string
    {
        return 'station.updated';
    }

    /**
     * Données à envoyer au frontend.
     * On calcule le count ici pour qu'il soit frais au moment de l'envoi.
     */
    public function broadcastWith(): array
    {
        $count = KitchenTicket::where('station_id', $this->kitchenStation->id)
            ->whereIn('status', ['pending', 'preparing'])
            ->count();

        return [
            'id' => $this->kitchenStation->id,
            'name' => $this->kitchenStation->name,
            'pending_tickets_count' => (int) $count,
            'updated_at' => now()->format('H:i:s'),
        ];
    }
}
