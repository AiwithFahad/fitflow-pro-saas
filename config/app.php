<?php
/**
 * FitFlow Pro — Application configuration.
 * Values are read from the environment (.env via config loader in bootstrap)
 * with safe local-development defaults. Never commit real secrets.
 */
return [
    'name'        => getenv('APP_NAME') ?: 'FitFlow Pro',
    'env'         => getenv('APP_ENV') ?: 'local',
    'debug'       => filter_var(getenv('APP_DEBUG') ?: 'true', FILTER_VALIDATE_BOOLEAN),
    'url'         => getenv('APP_URL') ?: 'http://localhost/fitflow-pro-saas/public',
    'timezone'    => 'UTC',
    'currency'    => 'USD',
    'trial_days'  => 14,
    'grace_days'  => 7,
    'upload_max'  => 5 * 1024 * 1024, // 5 MB
    'mail_from'   => getenv('MAIL_FROM') ?: 'no-reply@fitflowpro.example',
];
