<?php

return [
    'title' => 'Resume App installation',
    'intro' => 'A few details and your site is ready. You need a MySQL database (empty, or the one of a previous installation: its content is kept) (from the hosting control panel: create a database and a user, then give the user all privileges on the database).',
    'requirements_failed' => 'The server does not meet all the requirements: ask your hosting provider to enable them (usually in the PHP settings of the hosting control panel).',
    'submit' => 'Install',

    'sections' => [
        'requirements' => 'Server requirements',
        'site' => 'Site',
        'database' => 'MySQL database',
        'admin' => 'Administrator',
        'addresses' => 'Addresses',
    ],

    'fields' => [
        'url' => 'Site address',
        'owner' => 'Full name',
        'default_language' => 'Main language',
        'languages' => 'Other site languages (can be changed later in the admin)',
        'demo' => 'Load a sample CV (can be deleted later)',
        'db_host' => 'Server',
        'db_port' => 'Port',
        'db_database' => 'Database name',
        'db_username' => 'User',
        'db_password' => 'Password',
        'email' => 'Login email',
        'password' => 'Password (at least 10 characters)',
        'password_confirmation' => 'Repeat the password',
        'admin_path' => 'Admin area path',
        'force_https' => 'Always use HTTPS (recommended when the SSL certificate is active)',
        'strip_www' => 'Use the domain without "www"',
    ],

    'help' => [
        'url' => 'Full address, without trailing slash (e.g. https://jane.doe.com).',
        'database' => 'On shared hosting the server is almost always "localhost"; database and user names often start with the account name (e.g. account_resume).',
        'admin_path' => 'Lowercase letters, numbers and hyphens only. A path other than "admin" makes the area less exposed to automated attempts.',
        'addresses' => 'The rules are written in the .htaccess file, in a separate block: lines added by the hosting control panel are left untouched.',
    ],

    'errors' => [
        'title' => 'Installation not completed',
        'core_exposed' => 'The "core" folder can be reached from the browser: the server does not apply .htaccess files. It is not safe to continue: ask your hosting provider to enable AllowOverride (Apache) or to block the core folder.',
        'database_connection' => 'Could not connect to the database: :message',
        'database_setup' => 'Error while preparing the database: :message',
    ],

    'done' => [
        'title' => 'Installation complete',
        'text' => 'Your site is ready. Sign in to the admin area with the email and password you just chose.',
        'admin' => 'Go to the admin area',
        'site' => 'Open the site',
        'existing' => 'The database already contained content: it has been kept. The administrator with this email has the new password (two-factor authentication, if any, must be turned on again).',
        'admin_address' => 'Admin area address: :url (bookmark it).',
        'next' => 'Next steps',
        'step_mfa' => 'Turn on two-factor authentication: user menu → Profile.',
        'step_profile' => 'Fill in the profile and the CV sections.',
        'step_cv' => 'Upload the CV PDF for each language (CV PDFs).',
    ],
];
