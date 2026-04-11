<div class="block w-full pt-1x pb-1x bg-[#ffffff]" data-header-color="default">
    <div class="max-w-full mx-auto px-4">
        <div class="flex flex-row flex-wrap gap-6 md:gap-8">
            <div class="w-full flex flex-col bg-transparent">
                <div class="w-full">
                    <div class="w-full">
                        
                        <!-- Desktop Version -->
                        <div class="hidden md:block bg-transparent">
                            <div class="flex flex-row flex-wrap gap-6 md:gap-2">
                                <div class="w-1/2 text-left md:text-left">
                                    <div class="w-full">
                                        <div class="w-full">
                                            <h3 class="text-sm font-jost">
                                                <span>Copyright © 2005-{{ now()->year }} EdVenture School. Toate drepturile rezervate.</span>
                                            </h3>
                                        </div>
                                    </div>
                                </div>

                                <div class="w-1/2 text-right md:text-right">
                                    <div class="w-full">
                                        <div class="text-sm font-jost space-x-6">
                                            <nav class="inline-block">
                                                <ul class="flex gap-6">
                                                    <li class="menu-item">
                                                        <a href="{{ route('privacy') }}">
                                                            Politica de confidențialitate
                                                        </a>
                                                    </li>
                                                    <li class="menu-item">
                                                        <a href="https://anpc.ro/" target="_blank" rel="nofollow">
                                                            ANPC
                                                        </a>
                                                    </li>
                                                    <li class="menu-item">
                                                        <a href="{{ route('cookies') }}">
                                                            Cookies
                                                        </a>
                                                    </li>
                                                </ul>
                                            </nav>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Mobile Version -->
                        <div class="block md:hidden bg-transparent">
                            <div class="flex flex-col gap-4">
                                <div class="w-full text-center">
                                    <div class="w-full">
                                        <div class="w-full">
                                            <h3 class="text-sm font-jost text-center">
                                                <span>Copyright © 2005-{{ now()->year }} EdVenture School. Toate drepturile rezervate.</span>
                                            </h3>
                                            <div class="text-sm font-jost text-center space-y-2 mt-2">
                                                <nav>
                                                    <ul class="flex flex-col items-center gap-2">
                                                        <li class="menu-item">
                                                            <a href="{{ route('privacy') }}">Politica de confidențialitate</a>
                                                        </li>
                                                        <li class="menu-item">
                                                            <a href="https://anpc.ro/" target="_blank" rel="nofollow">ANPC</a>
                                                        </li>
                                                        <li class="menu-item">
                                                            <a href="{{ route('cookies') }}">Cookies</a>
                                                        </li>
                                                    </ul>
                                                </nav>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ANPC & EU Complaint Links -->
                        <div class="flex justify-center mt-[10px]">
                            <a href="https://anpc.ro/ce-este-sal/" target="_blank" rel="nofollow" class="pr-2">
                                <img src="{{ asset('images/anpc-sal.png') }}" alt="ANPC SAL" width="201" height="47" />
                            </a>
                            <a href="https://ec.europa.eu/consumers/odr/main/index.cfm?event=main.home2.show&lng=RO" target="_blank" rel="nofollow" class="pr-2">
                                <img src="{{ asset('images/eu-complaint.png') }}" alt="EU Complaint Resolution" width="201" height="47" />
                            </a>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
