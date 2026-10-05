<?php

return [
    'scopes' => ['openid', 'profile', 'email', 'User.Read'],
    'post_logout_redirect_uri' => env('MICROSOFT_POST_LOGOUT_REDIRECT_URI'),
    'bootstrap_tenant' => env('MICROSOFT_BOOTSTRAP_TENANT_ID'),
    'bootstrap_object' => env('MICROSOFT_BOOTSTRAP_OBJECT_ID'),
];
