<?php

/*
 * Resume App — public site strings (en-US), shared by all themes.
 * Themes only add their own strings. Every string can be overridden from the admin.
 */

return [
    'sections' => [
        'about' => 'Profile',
        'highlights' => 'At a glance',
        'experience' => 'Experience',
        'volunteering' => 'Volunteering',
        'projects' => 'Projects',
        'education' => 'Education',
        'certifications' => 'Certifications',
        'languages' => 'Languages',
        'publications' => 'Publications and talks',
        'interests' => 'Interests',
        'blog' => 'Blog',
    ],

    'actions' => [
        'download_cv' => 'Download résumé',
        'download_cv_lang' => 'Download the résumé in :language (PDF)',
        'contact' => 'Contact me',
        'call' => 'Call',
        'details' => 'Details',
        'read_more' => 'Read more',
        'back_to_cv' => 'Back to résumé',
        'all_articles' => 'All articles',
        'verify' => 'Verify',
        'open_link' => 'Open link',
        'print' => 'Print',
    ],

    'contact' => [
        'email' => 'Email',
        'phone' => 'Phone',
        'location' => 'Location',
        'birthdate' => 'Date of birth',
        'reveal_hint' => 'Enable JavaScript to see the full contact details',
    ],

    'time' => [
        'present' => 'present',
        'in_progress' => 'in progress',
        'years' => '{1} :count year|[2,*] :count years',
        'months' => '{1} :count month|[2,*] :count months',
        'and' => 'and',
        'less_than_a_month' => 'less than a month',
    ],

    'experience' => [
        'skills' => 'Skills',
        'related_projects' => 'Projects in this role',
        'client' => 'Client',
        'sector' => 'Sector',
        'role' => 'Role',
    ],

    'education' => [
        'grade' => 'Grade',
        'cum_laude' => 'cum laude',
        'thesis' => 'Thesis',
    ],

    'certifications' => [
        'issued' => 'Issued',
        'expires' => 'Expires',
        'no_expiration' => 'No expiration',
        'credential_id' => 'Credential ID',
        'issuer' => 'Issuer',
        'types' => [
            'certification' => 'Certification',
            'accreditation' => 'Accreditation',
            'course' => 'Course',
            'workshop' => 'Workshop',
            'training_path' => 'Training path',
            'exam' => 'Exam',
            'badge' => 'Badge',
        ],
    ],

    'languages' => [
        'native' => 'Native',
        'oral' => 'Spoken',
        'written' => 'Written',
        'comprehension' => 'Understanding',
        'cefr' => 'Common European Framework of Reference (CEFR)',
        'levels' => [
            'A1' => 'A1 - Beginner',
            'A2' => 'A2 - Elementary',
            'B1' => 'B1 - Intermediate',
            'B2' => 'B2 - Upper intermediate',
            'C1' => 'C1 - Advanced',
            'C2' => 'C2 - Proficient',
        ],
    ],

    'publications' => [
        'types' => [
            'article' => 'Article',
            'paper' => 'Paper',
            'thesis' => 'Thesis',
            'book' => 'Book or chapter',
            'talk' => 'Talk',
            'webinar' => 'Webinar',
            'presentation' => 'Presentation',
            'podcast' => 'Podcast',
        ],
        'with' => 'with :authors',
    ],

    'highlights' => [
        'years_experience' => 'years of experience',
        'projects_count' => 'projects',
        'certifications_count' => 'certifications',
        'publications_count' => 'publications and talks',
        'articles_count' => 'articles',
    ],

    'blog' => [
        'published_on' => 'Published on :date',
        'updated_on' => 'Updated on :date',
        'reading_time' => '{1} :count minute read|[2,*] :count minute read',
        'no_articles' => 'No articles published yet.',
        'feed' => 'RSS feed',
        'description' => 'Articles and notes by :name.',
        'page' => 'Page :page',
        'newer' => 'Newer articles',
        'older' => 'Older articles',
        'back_to_blog' => 'Back to the blog',
        'pagination' => 'Blog pages',
        'latest' => 'Latest articles',
    ],

    'privacy' => [
        'title' => 'Privacy',
        'link' => 'Privacy',
        'intro_title' => 'Who looks after the data',
        'intro' => 'This site is the online CV of {name}, who is the controller of the personal data. For any question about privacy you can write to {email}.',
        'cookies_title' => 'No cookies',
        'cookies' => 'The site uses no cookies or other tools that store or read information on your device, and it loads nothing from other sites (fonts, scripts, videos, maps): nobody else receives data about your visit.',
        'logs_title' => 'Server logs',
        'logs' => 'Like any website, the server that hosts it records the requests it receives (IP address, date and time, page, browser) for security and to keep it running. These logs are managed by the hosting provider and deleted after the period the provider sets.',
        'contact_title' => 'If you write to me',
        'contact' => 'I use your address and what you send me only to answer you, and I do not pass them on to anyone.',
        'rights_title' => 'Your rights',
        'rights' => 'You can ask to see the data about you, to correct it or to delete it, and you can object to its use by writing to the address above. If you think your data is not handled properly you can contact your data protection authority.',
        'stats_title' => 'Visit statistics',
        'stats' => 'To know how many people visit the site and which pages they read, I record some information about each visit: date and time, page, the site you came from and any campaign parameters (UTM), type of device, operating system and browser.',
        'stats_zone' => 'From the IP address I also work out where the visit comes from (country, approximate city and network), with a database installed on the server: the address is not sent to anyone.',
        'stats_ip' => 'The IP address is not stored in full: it is shortened straight away (for example 93.44.12.x). I use no cookies and I do not recognise people who come back.',
        'stats_basis' => 'I do this for the legitimate interest of understanding how the site is used. The data of each visit is deleted after :months months; only the totals remain, such as the visits of each day.',
        'stats_credit' => 'The location is worked out with GeoLite2 data created by MaxMind, available from https://www.maxmind.com.',
        'contact_fallback' => 'the contact details on this site',
    ],

    'footer' => [
        'last_updated' => 'Résumé last updated :date',
        'images_owners' => 'Images and logos belong to their respective owners.',
        'made_with' => 'Built with Resume App',
    ],

    'a11y' => [
        'skip_to_content' => 'Skip to content',
        'main_menu' => 'Main menu',
        'social_links' => 'Social profiles',
        'language_switcher' => 'Site language',
        'back_to_top' => 'Back to top',
        'opens_new_tab' => '(opens in a new tab)',
    ],

    'errors' => [
        'not_found_title' => 'Page not found',
        'not_found_text' => 'The page you are looking for does not exist or has been moved.',
        'maintenance_title' => 'Site being updated',
        'maintenance_text' => 'The site is being updated: please come back in a few minutes.',
    ],
];
