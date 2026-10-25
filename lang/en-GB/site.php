<?php

/*
 * Resume App — public site strings (en-GB), shared by all themes.
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
        'download_cv' => 'Download CV',
        'download_cv_lang' => 'Download the CV in :language (PDF)',
        'contact' => 'Contact me',
        'call' => 'Call',
        'details' => 'Details',
        'read_more' => 'Read more',
        'back_to_cv' => 'Back to CV',
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

    'footer' => [
        'last_updated' => 'CV last updated :date',
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
