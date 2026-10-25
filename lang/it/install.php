<?php

return [
    'title' => 'Installazione di Resume App',
    'intro' => 'Pochi dati e il sito è pronto. Serve un database MySQL (vuoto, oppure quello di un\'installazione precedente: i contenuti restano) (da cPanel: Database MySQL → crea database e utente, poi assegna all\'utente tutti i privilegi sul database).',
    'requirements_failed' => 'Il server non soddisfa tutti i requisiti: chiedi all\'hosting di attivarli (di solito da cPanel → Seleziona versione PHP).',
    'submit' => 'Installa',

    'sections' => [
        'requirements' => 'Requisiti del server',
        'site' => 'Sito',
        'database' => 'Database MySQL',
        'admin' => 'Amministratore',
        'addresses' => 'Indirizzi',
    ],

    'fields' => [
        'url' => 'Indirizzo del sito',
        'owner' => 'Nome e cognome',
        'default_language' => 'Lingua principale',
        'languages' => 'Altre lingue del sito (si possono cambiare dopo dall\'admin)',
        'demo' => 'Carica un CV di esempio (si può cancellare dopo)',
        'db_host' => 'Server',
        'db_port' => 'Porta',
        'db_database' => 'Nome del database',
        'db_username' => 'Utente',
        'db_password' => 'Password',
        'email' => 'Email per l\'accesso',
        'password' => 'Password (almeno 10 caratteri)',
        'password_confirmation' => 'Ripeti la password',
        'admin_path' => 'Percorso dell\'area admin',
        'force_https' => 'Usa sempre HTTPS (consigliato se il certificato SSL è attivo)',
        'strip_www' => 'Usa il dominio senza "www"',
    ],

    'help' => [
        'url' => 'Indirizzo completo, senza slash finale (es. https://mario.rossi.it).',
        'database' => 'Su cPanel il server è quasi sempre "localhost"; nome del database e utente hanno davanti il nome dell\'account (es. utente_resume).',
        'admin_path' => 'Solo lettere minuscole, numeri e trattini. Un percorso diverso da "admin" rende l\'area meno esposta ai tentativi automatici.',
        'addresses' => 'Le regole vengono scritte nel file .htaccess, in un blocco separato: le righe aggiunte da cPanel restano intatte.',
    ],

    'errors' => [
        'title' => 'Installazione non completata',
        'core_exposed' => 'La cartella "core" è raggiungibile dal browser: il server non applica i file .htaccess. Non è sicuro continuare: chiedi all\'hosting di attivare AllowOverride (Apache) o di bloccare la cartella core.',
        'database_connection' => 'Connessione al database non riuscita: :message',
        'database_setup' => 'Errore durante la preparazione del database: :message',
    ],

    'done' => [
        'title' => 'Installazione completata',
        'text' => 'Il sito è pronto. Entra nell\'area admin con l\'email e la password appena scelte.',
        'admin' => 'Vai all\'area admin',
        'site' => 'Apri il sito',
        'existing' => 'Il database conteneva già dei contenuti: sono stati mantenuti. L\'amministratore con questa email ha la nuova password (se aveva la verifica in due passaggi, va riattivata).',
        'admin_address' => 'Indirizzo dell\'area admin: :url (salvalo nei preferiti).',
        'next' => 'Prossimi passi',
        'step_mfa' => 'Attiva l\'autenticazione a due fattori: menu utente → Profilo.',
        'step_profile' => 'Compila il profilo e le sezioni del CV.',
        'step_cv' => 'Carica il CV in PDF per ogni lingua (CV in PDF).',
    ],
];
