<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Salesforce Connection
    |--------------------------------------------------------------------------
    |
    | The default Salesforce connection to use. This can be overridden at runtime.
    |
    */
    'default' => env('SALESFORCE_CONNECTION', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Salesforce Connections
    |--------------------------------------------------------------------------
    |
    | Here you may configure multiple Salesforce connections. Each connection
    | can have its own set of credentials and settings.
    |
    */
    'connections' => [
        'default' => [
            /*
            |--------------------------------------------------------------------------
            | Salesforce Application Authentication (Optional)
            |--------------------------------------------------------------------------
            |
            | These credentials are used for application-level authentication in your
            | Salesforce Apex Classes. Only required if your Apex endpoints implement
            | custom application authentication.
            |
            */
            'app_uuid' => env('SALESFORCE_APP_UUID'),
            'app_key' => env('SALESFORCE_APP_KEY'),

            /*
            |--------------------------------------------------------------------------
            | Salesforce OAuth Authentication
            |--------------------------------------------------------------------------
            |
            | These credentials are required for authenticating with the Salesforce API.
            |
            */
            'client_id' => env('SALESFORCE_CLIENT_ID'),
            'client_secret' => env('SALESFORCE_CLIENT_SECRET'),
            'username' => env('SALESFORCE_USERNAME'),
            'password' => env('SALESFORCE_PASSWORD'),
            'security_token' => env('SALESFORCE_SECURITY_TOKEN'),

            /*
            |--------------------------------------------------------------------------
            | API Endpoints
            |--------------------------------------------------------------------------
            |
            | The URIs for the Salesforce OAuth token and Apex REST endpoints.
            |
            */
            'token_uri' => env('SALESFORCE_TOKEN_URI', 'https://test.salesforce.com/services/oauth2/token'),
            'apex_uri' => env('SALESFORCE_APEX_URI', 'https://test.salesforce.com/services/apexrest'),

            /*
            |--------------------------------------------------------------------------
            | SSL Certificate Authentication (Optional)
            |--------------------------------------------------------------------------
            |
            | If you're using certificate-based authentication, specify the certificate
            | and private key filenames here. Files should be stored in storage/certificates.
            |
            */
            'certificate' => env('SALESFORCE_CERTIFICATE'),
            'certificate_key' => env('SALESFORCE_CERTIFICATE_KEY'),

            /*
            |--------------------------------------------------------------------------
            | Default User Email (Optional)
            |--------------------------------------------------------------------------
            |
            | The default user email to use when making API requests. This can be
            | overridden using the setEmail() method.
            |
            */
            'default_user_email' => env('SALESFORCE_DEFAULT_USER_EMAIL'),
        ],

        // Example of another connection configuration
        // 'sandbox' => [
        //     'app_uuid' => env('SALESFORCE_SANDBOX_APP_UUID'),
        //     'app_key' => env('SALESFORCE_SANDBOX_APP_KEY'),
        //     'client_id' => env('SALESFORCE_SANDBOX_CLIENT_ID'),
        //     'client_secret' => env('SALESFORCE_SANDBOX_CLIENT_SECRET'),
        //     'username' => env('SALESFORCE_SANDBOX_USERNAME'),
        //     'password' => env('SALESFORCE_SANDBOX_PASSWORD'),
        //     'security_token' => env('SALESFORCE_SANDBOX_SECURITY_TOKEN'),
        //     'token_uri' => env('SALESFORCE_SANDBOX_TOKEN_URI', 'https://test.salesforce.com/services/oauth2/token'),
        //     'apex_uri' => env('SALESFORCE_SANDBOX_APEX_URI', 'https://test.salesforce.com/services/apexrest'),
        //     'certificate' => env('SALESFORCE_SANDBOX_CERTIFICATE'),
        //     'certificate_key' => env('SALESFORCE_SANDBOX_CERTIFICATE_KEY'),
        //     'default_user_email' => env('SALESFORCE_SANDBOX_DEFAULT_USER_EMAIL'),
        // ],
    ],
];
