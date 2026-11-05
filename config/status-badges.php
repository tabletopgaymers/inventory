<?php

// One presentation mapping for server-rendered and client-updated status badges.
return [
    'default' => 'pending',
    'labels' => ['request' => 'Requested'],
    'statuses' => [
        'new' => 'pending',
        'draft' => 'pending',
        'request' => 'pending',
        'requested' => 'pending',
        'planning' => 'pending',
        'unverified' => 'pending',
        'active' => 'active',
        'accepted' => 'active',
        'ordered' => 'active',
        'shipped' => 'active',
        'fulfilled' => 'active',
        'receiving' => 'active',
        'received' => 'complete',
        'finalized' => 'complete',
        'complete' => 'complete',
        'completed' => 'complete',
        'cancelled' => 'inactive',
        'inactive' => 'inactive',
        'archived' => 'inactive',
    ],
];
