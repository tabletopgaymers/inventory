<?php

return [
    // Stable route keys; presentation labels shared by request/order/receipt views.
    'tabs' => [
        'basic' => 'Basic info',
        'material' => 'Material',
        'review' => 'Request',
        'order' => 'Procurement',
        'invoice' => 'Line Items',
        'charges' => 'Fees',
        'order-review' => 'Order',
        'receive' => 'Receive',
    ],
    // First panel headings can differ from navigation labels independently.
    'panels' => [
        'basic' => 'Basic information',
        'material' => 'Requested material',
        'review' => 'Review purchase request',
        'order' => 'Procurement and supplier review',
        'invoice' => 'Update line items',
        'charges' => 'Update order fees',
        'order-review' => 'Review and place order',
        'receive' => 'Receive delivery',
    ],
    'buttons' => [
        'basic' => 'Next ▷',
        'material' => 'Next ▷',
        'review' => 'Submit Request ↪',
        'order' => 'Next ▷',
        'invoice' => 'Next ▷',
        'charges' => 'Next ▷',
        'order-review' => 'Mark Ordered ✓',
        'receive' => 'Mark Completed ✓',
    ],
];
