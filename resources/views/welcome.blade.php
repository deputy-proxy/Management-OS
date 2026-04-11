@extends('layouts.app')

@section('content')
    <!-- Hero Section -->
    @include('components.hero.hero-slider', [
        'slides' => [
            [
                'id' => 'slide-1',
                'image' => asset('images/slider-1.png'),
                'title' => 'Bine venit la EdVenture School',
                'description' => 'Descoperă cursurile de limbi străine perfect adaptate pentru tine',
                'button' => [
                    'text' => 'Explore our courses',
                    'url' => route('courses.index')
                ]
            ],
            [
                'id' => 'slide-2',
                'image' => asset('images/slider-2.png'),
                'title' => 'Invatură Engleza cu Pasiune',
                'description' => 'Pregătire de calitate pentru examene Cambridge, IELTS, TOEFL',
                'button' => [
                    'text' => 'Mai multe informatii',
                    'url' => route('courses.english')
                ]
            ]
        ]
    ])

    <!-- Welcome Section -->
    @component('components.sections.section-with-gradient')
        <h2 class="text-8xl font-bold font-jost text-center mb-[30px]">
            <span>Despre EdVenture School</span>
        </h2>
        
        <div class="text-base font-jost">
            <p class="text-center">
                EdVenture School este o școală de limbi străine care cultivă pasiunea pentru învățarea limbilor străine de peste 20 ani. 
                Oferim cursuri de calitate pentru copii, adolescenți și adulți.
            </p>
        </div>
    @endcomponent

    <!-- Two Column Section -->
    @component('components.sections.section-default')
        @component('components.sections.column', ['columnClass' => 'w-1/2'])
            <h3 class="text-6xl font-bold font-jost mb-[30px]">
                <span>Cursuri pentru Copii și Adolescenți</span>
            </h3>
            
            <div class="text-base font-jost">
                <p>
                    Descoperim limba engleză, germană, franceză și japoneză împreună. 
                    Cursurile noastre sunt interactive, divertisante și adaptate vârstei.
                </p>
            </div>

            <a href="{{ route('courses.kids') }}" class="inline-block bg-primary-1 text-white py-3 px-6 rounded">
                Afla mai mult
            </a>
        @endcomponent

        @component('components.sections.column', ['columnClass' => 'w-1/2'])
            <img src="{{ asset('images/kids-learning.jpg') }}" 
                alt="Copii invatand limbi" 
                class="attachment-full size-full" />
        @endcomponent
    @endcomponent

    <!-- Testimonials Section -->
    @include('components.sections.testimonials', [
        'testimonials' => [
            [
                'content' => 'Cursurile la EdVenture sunt excelente! Profesorii sunt dedicate și lecțiile sunt foarte interactive.',
                'author' => 'Maria Popescu',
                'role' => 'Student'
            ],
            [
                'content' => 'Am trecut cu succes examenul Cambridge B1. Mulțumesc pentru pregătire de calitate!',
                'author' => 'Alex Ionescu',
                'role' => 'Student'
            ],
            [
                'content' => 'Cursurile corporate au ajutat echipa noastră să navigheze mai bine comunicarea în engleză.',
                'author' => 'Ion Mihai',
                'role' => 'Manager HR'
            ]
        ]
    ])

    <!-- Call to Action -->
    @component('components.sections.section-colored', ['backgroundColor' => '#f9f9f9'])
        <div class="w-full bg-transparent">
            <div class="w-full">
                <div class="w-full text-center">
                    <h2 class="text-8xl font-bold font-jost text-center mb-[30px]">
                        <span>Pregătit să înveți o limbă nouă?</span>
                    </h2>
                    
                    <p class="text-[18px] mb-[30px]">
                        Alătură-te zecilor de cursanți care au transformat modul în care vorbesc o limbă străină.
                    </p>

                    <a href="{{ route('contact') }}" class="inline-block bg-primary-1 text-white py-4 px-8 rounded text-lg">
                        Contactează-ne pentru o lecție de încercare
                    </a>
                </div>
            </div>
        </div>
    @endcomponent

@endsection
