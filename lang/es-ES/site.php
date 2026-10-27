<?php

/*
 * Resume App — public site strings (es-ES, Español), shared by all themes.
 * Draft translation: to be reviewed by a native speaker. Every string can be overridden from the admin
 * (Site languages → Custom site strings).
 */

return [
    'sections' => [
        'about' => 'Perfil',
        'highlights' => 'En cifras',
        'experience' => 'Experiencia',
        'volunteering' => 'Voluntariado',
        'projects' => 'Proyectos',
        'education' => 'Formación',
        'certifications' => 'Certificaciones',
        'languages' => 'Idiomas',
        'publications' => 'Publicaciones y ponencias',
        'interests' => 'Intereses',
        'blog' => 'Blog',
    ],

    'actions' => [
        'download_cv' => 'Descargar CV',
        'download_cv_lang' => 'Descargar el CV en :language (PDF)',
        'contact' => 'Contactar',
        'call' => 'Llamar',
        'details' => 'Detalles',
        'read_more' => 'Leer más',
        'back_to_cv' => 'Volver al CV',
        'all_articles' => 'Todos los artículos',
        'verify' => 'Verificar',
        'open_link' => 'Abrir el enlace',
        'print' => 'Imprimir',
    ],

    'contact' => [
        'email' => 'Correo electrónico',
        'phone' => 'Teléfono',
        'location' => 'Ubicación',
        'birthdate' => 'Fecha de nacimiento',
        'reveal_hint' => 'Activa JavaScript para ver los datos de contacto completos',
    ],

    'time' => [
        'present' => 'actualidad',
        'in_progress' => 'en curso',
        'years' => '{1} :count año|[2,*] :count años',
        'months' => '{1} :count mes|[2,*] :count meses',
        'and' => 'y',
        'less_than_a_month' => 'menos de un mes',
    ],

    'experience' => [
        'skills' => 'Competencias',
        'related_projects' => 'Proyectos en este puesto',
        'client' => 'Cliente',
        'sector' => 'Sector',
        'role' => 'Rol',
    ],

    'education' => [
        'grade' => 'Calificación',
        'cum_laude' => 'cum laude',
        'thesis' => 'Tesis',
    ],

    'certifications' => [
        'issued' => 'Emitida',
        'expires' => 'Caducidad',
        'no_expiration' => 'Sin caducidad',
        'credential_id' => 'ID de credencial',
        'issuer' => 'Entidad emisora',
        'types' => [
            'certification' => 'Certificación',
            'accreditation' => 'Acreditación',
            'course' => 'Curso',
            'workshop' => 'Taller',
            'training_path' => 'Itinerario formativo',
            'exam' => 'Examen',
            'badge' => 'Insignia',
        ],
    ],

    'languages' => [
        'native' => 'Lengua materna',
        'oral' => 'Oral',
        'written' => 'Escrito',
        'comprehension' => 'Comprensión',
        'cefr' => 'Marco Común Europeo de Referencia (MCER)',
        'levels' => [
            'A1' => 'A1 - Acceso',
            'A2' => 'A2 - Plataforma',
            'B1' => 'B1 - Umbral',
            'B2' => 'B2 - Avanzado',
            'C1' => 'C1 - Dominio operativo eficaz',
            'C2' => 'C2 - Maestría',
        ],
    ],

    'publications' => [
        'types' => [
            'article' => 'Artículo',
            'paper' => 'Publicación científica',
            'thesis' => 'Tesis',
            'book' => 'Libro o capítulo',
            'talk' => 'Ponencia',
            'webinar' => 'Webinar',
            'presentation' => 'Presentación',
            'podcast' => 'Pódcast',
        ],
        'with' => 'con :authors',
    ],

    'highlights' => [
        'years_experience' => 'años de experiencia',
        'projects_count' => 'proyectos',
        'certifications_count' => 'certificaciones',
        'publications_count' => 'publicaciones y ponencias',
        'articles_count' => 'artículos',
    ],

    'blog' => [
        'published_on' => 'Publicado el :date',
        'updated_on' => 'Actualizado el :date',
        'reading_time' => '{1} :count minuto de lectura|[2,*] :count minutos de lectura',
        'no_articles' => 'Todavía no hay artículos publicados.',
        'feed' => 'Feed RSS',
        'description' => 'Artículos y notas de :name.',
        'page' => 'Página :page',
        'newer' => 'Artículos más recientes',
        'older' => 'Artículos anteriores',
        'back_to_blog' => 'Volver al blog',
        'pagination' => 'Páginas del blog',
        'latest' => 'Últimos artículos',
    ],

    'privacy' => [
        'title' => 'Privacidad',
        'link' => 'Privacidad',
        'intro_title' => 'Quién gestiona los datos',
        'intro' => 'Este sitio es el currículum en línea de {name}, responsable del tratamiento de los datos personales. Para cualquier pregunta sobre privacidad puedes escribir a {email}.',
        'cookies_title' => 'Sin cookies',
        'cookies' => 'El sitio no usa cookies ni otras herramientas que guarden o lean información en tu dispositivo, y no carga nada de otros sitios (fuentes, scripts, vídeos, mapas): nadie más recibe datos de tu visita.',
        'logs_title' => 'Registros del servidor',
        'logs' => 'Como cualquier sitio web, el servidor que lo aloja registra las peticiones que recibe (dirección IP, fecha y hora, página, navegador) por seguridad y para su funcionamiento. Estos registros los gestiona el proveedor de alojamiento y se borran después del plazo que este establece.',
        'contact_title' => 'Si me escribes',
        'contact' => 'Uso tu dirección y lo que me envías solo para responderte, y no se lo paso a nadie.',
        'rights_title' => 'Tus derechos',
        'rights' => 'Puedes pedir acceso a tus datos, su rectificación o supresión, y oponerte a su tratamiento escribiendo a la dirección indicada arriba. También puedes presentar una reclamación ante la autoridad de protección de datos (en España, la AEPD).',
        'stats_title' => 'Estadísticas de visitas',
        'stats' => 'Para saber cuántas personas visitan el sitio y qué páginas leen, registro algunos datos de cada visita: fecha y hora, página, sitio de procedencia y posibles parámetros de campaña (UTM), tipo de dispositivo, sistema operativo y navegador.',
        'stats_zone' => 'A partir de la dirección IP también obtengo la procedencia (país, ciudad aproximada y red) con una base de datos instalada en el servidor: la dirección no se envía a nadie.',
        'stats_ip' => 'La dirección IP no se guarda completa: se acorta de inmediato (por ejemplo 93.44.12.x). No uso cookies y no reconozco a quien vuelve a visitar el sitio.',
        'stats_basis' => 'Lo hago por el interés legítimo de entender cómo se usa el sitio. Los datos de cada visita se borran después de :months meses; solo quedan los totales, como las visitas de cada día.',
        'stats_credit' => 'La procedencia se obtiene con los datos GeoLite2 creados por MaxMind, disponibles en https://www.maxmind.com.',
        'contact_fallback' => 'los datos de contacto indicados en el sitio',
    ],

    'footer' => [
        'last_updated' => 'CV actualizado el :date',
        'images_owners' => 'Las imágenes y los logotipos pertenecen a sus respectivos propietarios.',
        'made_with' => 'Hecho con Resume App',
    ],

    'a11y' => [
        'skip_to_content' => 'Saltar al contenido',
        'main_menu' => 'Menú principal',
        'social_links' => 'Perfiles sociales',
        'language_switcher' => 'Idioma del sitio',
        'back_to_top' => 'Volver arriba',
        'opens_new_tab' => '(se abre en una pestaña nueva)',
    ],

    'errors' => [
        'not_found_title' => 'Página no encontrada',
        'not_found_text' => 'La página que buscas no existe o se ha movido.',
        'maintenance_title' => 'Sitio en actualización',
        'maintenance_text' => 'Estamos actualizando el sitio: vuelve en unos minutos.',
    ],
];
