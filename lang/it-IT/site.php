<?php

/*
 * Resume App — public site strings (it-IT), shared by all themes.
 * Themes only add their own strings. Every string can be overridden from the admin.
 */

return [
    'sections' => [
        'about' => 'Profilo',
        'highlights' => 'In numeri',
        'experience' => 'Esperienze',
        'volunteering' => 'Volontariato',
        'projects' => 'Progetti',
        'education' => 'Formazione',
        'certifications' => 'Certificazioni',
        'languages' => 'Lingue',
        'publications' => 'Pubblicazioni e interventi',
        'interests' => 'Interessi',
        'blog' => 'Blog',
    ],

    'actions' => [
        'download_cv' => 'Scarica CV',
        'download_cv_lang' => 'Scarica il CV in :language (PDF)',
        'contact' => 'Contattami',
        'call' => 'Chiama',
        'details' => 'Dettagli',
        'read_more' => 'Leggi tutto',
        'back_to_cv' => 'Torna al CV',
        'all_articles' => 'Tutti gli articoli',
        'verify' => 'Verifica',
        'open_link' => 'Apri il link',
        'print' => 'Stampa',
    ],

    'contact' => [
        'email' => 'E-mail',
        'phone' => 'Telefono',
        'location' => 'Località',
        'birthdate' => 'Data di nascita',
        'reveal_hint' => 'Attiva JavaScript per vedere il contatto completo',
    ],

    'time' => [
        'present' => 'in corso',
        'in_progress' => 'in corso',
        'years' => '{1} :count anno|[2,*] :count anni',
        'months' => '{1} :count mese|[2,*] :count mesi',
        'and' => 'e',
        'less_than_a_month' => 'meno di un mese',
    ],

    'experience' => [
        'skills' => 'Competenze',
        'related_projects' => 'Progetti in questa esperienza',
        'client' => 'Cliente',
        'sector' => 'Settore',
        'role' => 'Ruolo',
    ],

    'education' => [
        'grade' => 'Voto',
        'cum_laude' => 'e lode',
        'thesis' => 'Tesi',
    ],

    'certifications' => [
        'issued' => 'Rilasciata',
        'expires' => 'Scadenza',
        'no_expiration' => 'Senza scadenza',
        'credential_id' => 'ID credenziale',
        'issuer' => 'Ente',
        'types' => [
            'certification' => 'Certificazione',
            'accreditation' => 'Accreditamento',
            'course' => 'Corso',
            'workshop' => 'Workshop',
            'training_path' => 'Percorso formativo',
            'exam' => 'Esame',
            'badge' => 'Badge',
        ],
    ],

    'languages' => [
        'native' => 'Madrelingua',
        'oral' => 'Orale',
        'written' => 'Scritto',
        'comprehension' => 'Comprensione',
        'cefr' => 'Quadro comune europeo di riferimento (QCER)',
        'levels' => [
            'A1' => 'A1 - Base',
            'A2' => 'A2 - Elementare',
            'B1' => 'B1 - Intermedio',
            'B2' => 'B2 - Intermedio superiore',
            'C1' => 'C1 - Avanzato',
            'C2' => 'C2 - Padronanza',
        ],
    ],

    'publications' => [
        'types' => [
            'article' => 'Articolo',
            'paper' => 'Pubblicazione scientifica',
            'thesis' => 'Tesi',
            'book' => 'Libro o capitolo',
            'talk' => 'Intervento',
            'webinar' => 'Webinar',
            'presentation' => 'Presentazione',
            'podcast' => 'Podcast',
        ],
        'with' => 'con :authors',
    ],

    'highlights' => [
        'years_experience' => 'anni di esperienza',
        'projects_count' => 'progetti',
        'certifications_count' => 'certificazioni',
        'publications_count' => 'pubblicazioni e interventi',
        'articles_count' => 'articoli',
    ],

    'blog' => [
        'published_on' => 'Pubblicato il :date',
        'updated_on' => 'Aggiornato il :date',
        'reading_time' => '{1} :count minuto di lettura|[2,*] :count minuti di lettura',
        'no_articles' => 'Nessun articolo pubblicato.',
        'feed' => 'Feed RSS',
        'description' => 'Articoli e note di :name.',
        'page' => 'Pagina :page',
        'newer' => 'Articoli più recenti',
        'older' => 'Articoli meno recenti',
        'back_to_blog' => 'Torna al blog',
        'pagination' => 'Pagine del blog',
        'latest' => 'Ultimi articoli',
    ],

    'privacy' => [
        'title' => 'Privacy',
        'link' => 'Privacy',
        'intro_title' => 'Chi gestisce i dati',
        'intro' => 'Questo sito è il curriculum online di {name}, titolare del trattamento dei dati personali. Per qualsiasi domanda sulla privacy puoi scrivere a {email}.',
        'cookies_title' => 'Niente cookie',
        'cookies' => 'Il sito non usa cookie né altri strumenti che salvano o leggono informazioni sul tuo dispositivo, e non carica nulla da altri siti (font, script, video, mappe): nessun altro riceve dati sulla tua visita.',
        'logs_title' => 'Registri del server',
        'logs' => 'Come per ogni sito, il server che lo ospita registra le richieste che riceve (indirizzo IP, data e ora, pagina, browser) per motivi di sicurezza e di funzionamento. Questi registri sono gestiti dal fornitore dell\'hosting e vengono cancellati dopo il periodo previsto dal fornitore.',
        'contact_title' => 'Se mi scrivi',
        'contact' => 'Uso il tuo indirizzo e le informazioni che mi mandi solo per risponderti, e non li passo ad altri.',
        'rights_title' => 'I tuoi diritti',
        'rights' => 'Puoi chiedere di vedere i dati che ti riguardano, di correggerli o di cancellarli, e puoi opporti al loro trattamento scrivendo all\'indirizzo indicato qui sopra. Se pensi che i tuoi dati non siano trattati correttamente puoi rivolgerti al Garante per la protezione dei dati personali.',
        'stats_title' => 'Statistiche delle visite',
        'stats' => 'Per sapere quante persone visitano il sito e quali pagine leggono, registro alcune informazioni su ogni visita: data e ora, pagina, sito di provenienza ed eventuali parametri di campagna (UTM), tipo di dispositivo, sistema operativo e browser.',
        'stats_zone' => 'Dall\'indirizzo IP ricavo anche la zona di provenienza (Paese, città approssimativa e rete), con un database installato sul server: l\'indirizzo non viene inviato a nessuno.',
        'stats_ip' => 'L\'indirizzo IP non viene salvato per intero: viene accorciato subito (per esempio 93.44.12.x). Non uso cookie e non riconosco chi torna a visitare il sito.',
        'stats_basis' => 'Lo faccio per il legittimo interesse a capire come viene usato il sito. I dati di ogni visita vengono cancellati dopo :months mesi; restano solo i totali, come le visite di ogni giorno.',
        'stats_credit' => 'La zona di provenienza è ricavata con i dati GeoLite2 creati da MaxMind, disponibili su https://www.maxmind.com.',
        'contact_fallback' => 'i contatti indicati nel sito',
    ],

    'footer' => [
        'last_updated' => 'CV aggiornato a :date',
        'images_owners' => 'Immagini e loghi appartengono ai rispettivi proprietari.',
        'made_with' => 'Realizzato con Resume App',
    ],

    'a11y' => [
        'skip_to_content' => 'Vai al contenuto',
        'main_menu' => 'Menu principale',
        'social_links' => 'Profili social',
        'language_switcher' => 'Lingua del sito',
        'back_to_top' => 'Torna su',
        'opens_new_tab' => '(si apre in una nuova scheda)',
    ],

    'errors' => [
        'not_found_title' => 'Pagina non trovata',
        'not_found_text' => 'La pagina che cerchi non esiste o è stata spostata.',
        'maintenance_title' => 'Sito in aggiornamento',
        'maintenance_text' => 'Stiamo aggiornando il sito: torna tra qualche minuto.',
    ],
];
