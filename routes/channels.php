<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::routes(['middleware' => ['auth:sanctum']]);
Broadcast::channel('kds.{kitchenId}', function ($user, $kitchenId) {

    return true;
});
Broadcast::channel('app_notifications.{recipient_type}.{recipient_id}', function ($user, $recipient_type, $recipient_id) {
    logger()->info('Broadcast check', [
        'user' => $user?->id,
        'role' => $user?->role,
        'recipient_type' => $recipient_type,
        'recipient_id' => $recipient_id,
    ]);

    if ($recipient_type === 'server' && $user->role === 'server') {
        return (int)$user->id === (int)$recipient_id;
    }
    if ($recipient_type === 'cashier' && $user->role === 'cashier') {
        return (int)$user->id === (int)$recipient_id;
    }
    if ($recipient_type === 'admin' && $user->role === 'admin') {
        return (int)$user->id === (int)$recipient_id;
    }
    return false;
});
