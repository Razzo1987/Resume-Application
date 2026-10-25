<?php

/*
 * Resume App — stringhe del sito pubblico (it-IT), comuni a tutti i temi.
 * I temi aggiungono solo le stringhe proprie. Ogni stringa è sovrascrivibile da admin.
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
            'A1' => 'A1 – Base',
            'A2' => 'A2 – Elementare',
            'B1' => 'B1 – Intermedio',
            'B2' => 'B2 – Intermedio superiore',
            'C1' => 'C1 – Avanzato',
            'C2' => 'C2 – Padronanza',
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
