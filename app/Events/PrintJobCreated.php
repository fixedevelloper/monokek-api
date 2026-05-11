<?php


namespace App\Events;

use App\Models\PrintQueue;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PrintJobCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $job;

    public function __construct(PrintQueue $job)
    {
        // On charge la relation printer pour connaître la branche
        $this->job = $job->load('printer');
    }

    public function broadcastOn()
    {
        // On diffuse sur le canal de la branche (ex: branch.1)
        return new Channel('branch.' . $this->job->printer->branch_id);
    }

    public function broadcastAs()
    {
        return 'PrintJobCreated';
    }
}
