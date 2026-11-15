<?php

/*
 * Demo personas a visitor can sign in as without a password.
 *
 * Each persona names its tenant by slug as well as its email: emails are only
 * unique per tenant (sandboxes clone these personas), so the email alone is ambiguous.
 */
return [
    'personas' => [
        'acme-uploader' => ['tenant' => 'acme', 'email' => 'maya@acmelogistics.com'],
        'acme-reviewer' => ['tenant' => 'acme', 'email' => 'jordan@acmelogistics.com'],
        'acme-admin' => ['tenant' => 'acme', 'email' => 'priya@acmelogistics.com'],
        'globex-admin' => ['tenant' => 'globex', 'email' => 'sam@globexfoods.com'],
    ],
];
