<?php

/*
 * Resume App — public site strings (pt-PT, Português (Portugal)), shared by all themes.
 * Draft translation: to be reviewed by a native speaker. Every string can be overridden from the admin
 * (Site languages → Custom site strings).
 */

return [
    'sections' => [
        'about' => 'Perfil',
        'highlights' => 'Em números',
        'experience' => 'Experiência profissional',
        'volunteering' => 'Voluntariado',
        'projects' => 'Projetos',
        'education' => 'Formação',
        'certifications' => 'Certificações',
        'languages' => 'Línguas',
        'publications' => 'Publicações e comunicações',
        'interests' => 'Interesses',
        'blog' => 'Blogue',
    ],

    'actions' => [
        'download_cv' => 'Descarregar CV',
        'download_cv_lang' => 'Descarregar o CV em :language (PDF)',
        'contact' => 'Contactar',
        'call' => 'Ligar',
        'details' => 'Detalhes',
        'read_more' => 'Ler mais',
        'back_to_cv' => 'Voltar ao CV',
        'all_articles' => 'Todos os artigos',
        'verify' => 'Verificar',
        'open_link' => 'Abrir ligação',
        'print' => 'Imprimir',
    ],

    'contact' => [
        'email' => 'E-mail',
        'phone' => 'Telefone',
        'location' => 'Localização',
        'birthdate' => 'Data de nascimento',
        'reveal_hint' => 'Ative o JavaScript para ver os contactos completos',
        'website' => 'Site',
    ],

    'time' => [
        'present' => 'atualmente',
        'in_progress' => 'em curso',
        'years' => '{1} :count ano|[2,*] :count anos',
        'months' => '{1} :count mês|[2,*] :count meses',
        'and' => 'e',
        'less_than_a_month' => 'menos de um mês',
    ],

    'experience' => [
        'skills' => 'Competências',
        'related_projects' => 'Projetos nesta função',
        'client' => 'Cliente',
        'sector' => 'Setor',
        'role' => 'Função',
    ],

    'education' => [
        'grade' => 'Classificação',
        'cum_laude' => 'com louvor',
        'thesis' => 'Tese',
    ],

    'certifications' => [
        'issued' => 'Emitida',
        'expires' => 'Validade',
        'no_expiration' => 'Sem validade',
        'credential_id' => 'ID da credencial',
        'issuer' => 'Entidade emissora',
        'types' => [
            'certification' => 'Certificação',
            'accreditation' => 'Acreditação',
            'course' => 'Curso',
            'workshop' => 'Workshop',
            'training_path' => 'Percurso formativo',
            'exam' => 'Exame',
            'badge' => 'Badge',
        ],
    ],

    'languages' => [
        'native' => 'Língua materna',
        'oral' => 'Oral',
        'written' => 'Escrita',
        'comprehension' => 'Compreensão',
        'cefr' => 'Quadro Europeu Comum de Referência (QECR)',
        'levels' => [
            'A1' => 'A1 - Iniciação',
            'A2' => 'A2 - Elementar',
            'B1' => 'B1 - Limiar',
            'B2' => 'B2 - Vantagem',
            'C1' => 'C1 - Autonomia',
            'C2' => 'C2 - Mestria',
        ],
    ],

    'publications' => [
        'types' => [
            'article' => 'Artigo',
            'paper' => 'Publicação científica',
            'thesis' => 'Tese',
            'book' => 'Livro ou capítulo',
            'talk' => 'Comunicação',
            'webinar' => 'Webinar',
            'presentation' => 'Apresentação',
            'podcast' => 'Podcast',
        ],
        'with' => 'com :authors',
    ],

    'highlights' => [
        'years_experience' => 'anos de experiência',
        'projects_count' => 'projetos',
        'certifications_count' => 'certificações',
        'publications_count' => 'publicações e comunicações',
        'articles_count' => 'artigos',
    ],

    'blog' => [
        'published_on' => 'Publicado a :date',
        'updated_on' => 'Atualizado a :date',
        'reading_time' => '{1} :count minuto de leitura|[2,*] :count minutos de leitura',
        'no_articles' => 'Ainda não há artigos publicados.',
        'feed' => 'Feed RSS',
        'description' => 'Artigos e notas de :name.',
        'page' => 'Página :page',
        'newer' => 'Artigos mais recentes',
        'older' => 'Artigos anteriores',
        'back_to_blog' => 'Voltar ao blogue',
        'pagination' => 'Páginas do blogue',
        'latest' => 'Últimos artigos',
    ],

    'privacy' => [
        'title' => 'Privacidade',
        'link' => 'Privacidade',
        'intro_title' => 'Quem gere os dados',
        'intro' => 'Este site é o currículo online de {name}, responsável pelo tratamento dos dados pessoais. Para qualquer questão sobre privacidade pode escrever para {email}.',
        'cookies_title' => 'Sem cookies',
        'cookies' => 'O site não usa cookies nem outras ferramentas que guardem ou leiam informações no seu dispositivo, e não carrega nada de outros sites (tipos de letra, scripts, vídeos, mapas): mais ninguém recebe dados sobre a sua visita.',
        'logs_title' => 'Registos do servidor',
        'logs' => 'Como em qualquer site, o servidor que o aloja regista os pedidos que recebe (endereço IP, data e hora, página, navegador) por segurança e para o seu funcionamento. Estes registos são geridos pelo fornecedor de alojamento e apagados após o prazo por ele definido.',
        'contact_title' => 'Se me escrever',
        'contact' => 'Uso o seu endereço e o que me envia apenas para lhe responder, e não os passo a ninguém.',
        'rights_title' => 'Os seus direitos',
        'rights' => 'Pode pedir acesso aos seus dados, a sua retificação ou apagamento, e opor-se ao tratamento escrevendo para o endereço indicado acima. Pode também apresentar reclamação à autoridade de proteção de dados (em Portugal, a CNPD).',
        'stats_title' => 'Estatísticas de visitas',
        'stats' => 'Para saber quantas pessoas visitam o site e que páginas leem, registo algumas informações de cada visita: data e hora, página, site de origem e eventuais parâmetros de campanha (UTM), tipo de dispositivo, sistema operativo e navegador.',
        'stats_zone' => 'A partir do endereço IP obtenho também a origem (país, cidade aproximada e rede) com uma base de dados instalada no servidor: o endereço não é enviado a ninguém.',
        'stats_ip' => 'O endereço IP não é guardado por inteiro: é encurtado de imediato (por exemplo 93.44.12.x). Não uso cookies e não reconheço quem volta a visitar o site.',
        'stats_basis' => 'Faço-o com base no interesse legítimo em perceber como o site é usado. Os dados de cada visita são apagados após :months meses; ficam apenas os totais, como as visitas de cada dia.',
        'stats_credit' => 'A origem é obtida com os dados GeoLite2 criados pela MaxMind, disponíveis em https://www.maxmind.com.',
        'contact_fallback' => 'os contactos indicados no site',
    ],

    'footer' => [
        'last_updated' => 'CV atualizado a :date',
        'images_owners' => 'As imagens e os logótipos pertencem aos respetivos proprietários.',
        'made_with' => 'Feito com Resume App',
    ],

    'a11y' => [
        'skip_to_content' => 'Saltar para o conteúdo',
        'main_menu' => 'Menu principal',
        'social_links' => 'Perfis nas redes sociais',
        'language_switcher' => 'Idioma do site',
        'back_to_top' => 'Voltar ao topo',
        'opens_new_tab' => '(abre num novo separador)',
    ],

    'errors' => [
        'not_found_title' => 'Página não encontrada',
        'not_found_text' => 'A página que procura não existe ou foi movida.',
        'maintenance_title' => 'Site em atualização',
        'maintenance_text' => 'Estamos a atualizar o site: volte dentro de alguns minutos.',
    ],
];
