<nav id="grve-main-menu" class="grve-header-menu grve-horizontal-menu grve-position-left" aria-label="Main">
    <ul id="menu-meniu-nou-edventure" class="grve-menu">
        <li class="menu-item grve-menu-area-item menu-item-has-children grve-menu-size-fullwidth grve-first-level grve-menu-no-link">
            <div class="grve-link-wrapper">
                <a href="#" title="About Us">
                    <span class="grve-item">Despre noi</span>
                </a>
                <div class="grve-arrow"></div>
            </div>
            @include('components.navigation.megamenu', [
                'title' => 'EdVenture School este o școală de limbi străine care cultivă pasiunea pentru învățarea limbilor străine de peste 20 ani',
                'columns' => [
                    [
                        'title' => 'Despre noi',
                        'content' => 'EdVenture School este o școală de limbi străine care cultivă pasiunea pentru învățare de peste 20 ani.'
                    ],
                    [
                        'title' => 'Valorile noastre:',
                        'content' => '✅ Profesionalism<br>✅ Pasiune și implicare<br>✅ Focus pe nevoile cursantului<br>✅ Respect'
                    ]
                ]
            ])
        </li>

        <li class="menu-item grve-menu-area-item menu-item-has-children grve-menu-size-fullwidth grve-first-level grve-menu-no-link">
            <div class="grve-link-wrapper">
                <a href="#" title="Cambridge Exams">
                    <span class="grve-item">Cambridge</span>
                </a>
                <div class="grve-arrow"></div>
            </div>
            @include('components.navigation.megamenu', [
                'columns' => [
                    ['title' => 'Copii clasele 0-4', 'links' => [['text' => 'Cursuri Engleză Copii – Young Learners (YLE)', 'url' => '#']]],
                    ['title' => 'Copii clasele 5-8', 'links' => [['text' => 'Key for Schools (KET)', 'url' => '#'], ['text' => 'Preliminary for Schools (PET)', 'url' => '#']]],
                    ['title' => 'Liceeni clasele 9-12', 'links' => [['text' => 'Advanced (CAE)', 'url' => '#'], ['text' => 'First for Schools (FCE)', 'url' => '#']]]
                ]
            ])
        </li>

        <li class="menu-item grve-menu-area-item menu-item-has-children grve-menu-size-fullwidth grve-first-level grve-menu-no-link">
            <div class="grve-link-wrapper">
                <a href="#" title="Kids & Teens">
                    <span class="grve-item">Copii și adolescenți</span>
                </a>
                <div class="grve-arrow"></div>
            </div>
            @include('components.navigation.megamenu', [
                'columns' => [
                    [
                        'title' => 'Cursuri pentru copii',
                        'content' => 'Cursuri Engleză Copii<br>Cursuri Germană Copii<br>Cursuri Franceză Copii<br>Cursuri Japoneză Copii'
                    ],
                    [
                        'title' => 'Cursuri pentru adolescenți',
                        'links' => [
                            ['text' => 'Debate Club for Teens', 'url' => '#'],
                            ['text' => 'Model United Nations pentru liceeni', 'url' => '#'],
                        ]
                    ]
                ]
            ])
        </li>

        <li class="menu-item grve-menu-area-item menu-item-has-children grve-menu-size-fullwidth grve-first-level grve-menu-no-link">
            <div class="grve-link-wrapper">
                <a href="#" title="Adults">
                    <span class="grve-item">Adulți</span>
                </a>
                <div class="grve-arrow"></div>
            </div>
            @include('components.navigation.megamenu', [
                'columns' => [
                    [
                        'title' => 'Cursuri Engleză Online',
                        'content' => 'Cursuri de engleză online pentru adulți<br>Cursuri individuale de limba engleză<br>Curs engleză conversațională'
                    ]
                ]
            ])
        </li>

        <li class="menu-item grve-menu-area-item menu-item-has-children grve-menu-size-fullwidth grve-first-level grve-menu-no-link">
            <div class="grve-link-wrapper">
                <a href="#" title="Exams">
                    <span class="grve-item">Examene</span>
                </a>
                <div class="grve-arrow"></div>
            </div>
            @include('components.navigation.megamenu', [
                'columns' => [
                    [
                        'title' => 'Cursuri de Pregătire',
                        'links' => [
                            ['text' => 'Curs IELTS online', 'url' => '#'],
                            ['text' => 'Curs TOEFL online', 'url' => '#'],
                            ['text' => 'Curs SAT online', 'url' => '#'],
                            ['text' => 'Cursuri Individuale de Pregătire', 'url' => '#'],
                        ]
                    ],
                    [
                        'title' => 'Examene de limba engleză LanguageCert',
                        'links' => [
                            ['text' => '⚡ Examene de engleză LanguageCert International ESOL', 'url' => '#'],
                        ]
                    ]
                ]
            ])
        </li>

        <li class="menu-item grve-menu-area-item menu-item-has-children grve-menu-size-fullwidth grve-first-level grve-menu-no-link">
            <div class="grve-link-wrapper">
                <a href="#" title="Companies">
                    <span class="grve-item">Companii</span>
                </a>
                <div class="grve-arrow"></div>
            </div>
            @include('components.navigation.megamenu', [
                'columns' => [
                    [
                        'title' => 'Cursuri Corporate – B2B',
                        'content' => 'Cursuri de Engleză pentru Companii<br>Cursuri de limbi străine pentru Afterschools și Școli'
                    ]
                ]
            ])
        </li>

        <li class="menu-item grve-menu-area-item menu-item-has-children grve-menu-size-fullwidth grve-first-level">
            <div class="grve-link-wrapper">
                <a href="#" title="Events">
                    <span class="grve-item">Evenimente</span>
                </a>
                <div class="grve-arrow"></div>
            </div>
            @include('components.navigation.megamenu', [
                'columns' => [
                    [
                        'title' => 'Teacher Trainings',
                        'links' => [
                            ['text' => 'Episodul 2: Engaging the learners of tomorrow!', 'url' => '#'],
                            ['text' => 'Episodul 3: Feedback that works!', 'url' => '#'],
                        ]
                    ],
                    [
                        'title' => 'Alte evenimente',
                        'links' => [
                            ['text' => 'Club de lectură pentru copii', 'url' => '#'],
                            ['text' => 'Tabere de engleză EdVenture', 'url' => '#'],
                            ['text' => 'Concurs EdVenture Ask-Think-Write-Cup', 'url' => '#'],
                        ]
                    ]
                ]
            ])
        </li>
    </ul>
</nav>