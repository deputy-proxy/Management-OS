<div class="block w-full bg-[#f9f9f9] text-black pt-[65px] pb-[50px]">
    <div class="max-w-full mx-auto px-4">
        <div class="flex flex-row flex-wrap gap-6 md:gap-8">
            
            <!-- Left Column: Logo & About -->
            <div class="w-1/3 md:w-1/2 flex flex-col text-center md:text-left">
                <div class="w-full">
                    <div class="w-full">
                        <!-- Logo -->
                        <div class="mb-[30px] flex justify-center md:justify-start">
                            <a href="{{ url('/') }}" title="{{ config('app.name') }}">
                                <img src="{{ asset('images/logo-20-edv-no-bkg.png') }}" 
                                     alt="{{ config('app.name') }}" 
                                     decoding="async" 
                                     style="max-width: 200px;" />
                            </a>
                        </div>

                        <!-- About Text -->
                        <div class="text-base">
                            <p>EdVenture este o școală de limbi străine fondată în 2005, cu sediul în București. Te invităm să descoperi programele noastre de cursuri.</p>
                        </div>

                        <!-- Addresses -->
                        <div class="mt-[20px] text-base">
                            <nav>
                                <ul class="space-y-2">
                                    <li>
                                        <a href="{{ route('contact') }}#adresa" class="inline-flex items-start gap-2">
                                            <i class="fa fa-map-pin"></i>
                                            <span>EdVenture School Cotroceni: Strada Dr. Staicovici nr. 4</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('contact') }}#adresa" class="inline-flex items-start gap-2">
                                            <i class="fa fa-map-pin"></i>
                                            <span>EdVenture School Tineretului: Strada Vitejescu nr. 19</span>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Links -->
            <div class="w-2/3 md:w-1/2 flex flex-col">
                <div class="w-full">
                    <div class="w-full">
                        <div class="w-full">
                            <div class="flex flex-row flex-wrap gap-4 md:gap-6">
                                
                                <!-- Pages Column -->
                                <div class="w-1/3 md:w-1/3">
                                    <h3 class="text-3xl font-bold mb-[18px]">Pagini</h3>
                                    <nav>
                                        <ul class="space-y-2">
                                            <li><a href="{{ route('about') }}">Despre noi</a></li>
                                            <li><a href="{{ route('careers') }}">Cariere</a></li>
                                            <li><a href="{{ route('blog') }}" target="_blank">Blog</a></li>
                                            <li><a href="{{ route('contact') }}">Contact</a></li>
                                        </ul>
                                    </nav>
                                </div>

                                <!-- Social Media Column -->
                                <div class="w-1/3 md:w-1/3">
                                    <h3 class="text-3xl font-bold mb-[18px]">Social media</h3>
                                    <nav>
                                        <ul class="space-y-2">
                                            <li><a href="https://www.facebook.com/EdVenture.ro" target="_blank" rel="nofollow">Facebook</a></li>
                                            <li><a href="https://www.instagram.com/edventure_ro/" target="_blank" rel="nofollow">Instagram</a></li>
                                            <li><a href="https://www.linkedin.com/company/edventureschool" target="_blank" rel="nofollow">LinkedIn</a></li>
                                            <li><a href="https://www.google.com/maps/place//data=!4m2!3m1!1s0x40b1ff6716e44b9b:0x5f312ec1ae12a881?source=g.page.default" target="_blank" rel="nofollow">Google</a></li>
                                        </ul>
                                    </nav>
                                </div>

                                <!-- Contact Column -->
                                <div class="w-1/3 md:w-1/3">
                                    <h3 class="text-3xl font-bold mb-[18px]">Contactează-ne</h3>
                                    <nav>
                                        <ul class="space-y-2">
                                            <li><a href="{{ route('contact') }}"><i class="fa fa-envelope-o"></i> Pagina de contact</a></li>
                                            <li><a href="tel:0213129036"><i class="fa fa-phone"></i> 021 312 9036</a></li>
                                            <li><a href="tel:0740085809"><i class="fa fa-phone"></i> 0740 085 809</a></li>
                                        </ul>
                                    </nav>
                                </div>
                                
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
