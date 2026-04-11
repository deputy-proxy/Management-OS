@if(!empty($testimonials))
<section class="block w-full pt-2x bg-[#f9f9f9]" data-header-color="default">
    <div class="max-w-full mx-auto px-4">
        <div class="flex flex-row flex-wrap gap-6 md:gap-8">
            <div class="w-full flex flex-col bg-transparent">
                <div class="w-full">
                    <div class="w-full">
                        <h2 class="text-8xl font-bold font-jost text-center mb-[60px];">
                            <span>Ce spun cursanții noștri</span>
                        </h2>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex flex-row flex-wrap gap-6 md:gap-8">
            @foreach($testimonials as $testimonial)
            <div class="w-1/3 flex flex-col bg-white">
                <div class="bg-white p-[30px] rounded-[3px]">
                    <div class="w-full">
                        <div class="text-base font-jost">
                            <p>{{ $testimonial['content'] }}</p>
                        </div>

                        <div class="mt-[20px] bg-transparent">
                            <div class="flex flex-row flex-wrap gap-6">
                                <div class="w-full">
                                    <div class="w-full">
                                        <div class="w-full">
                                            <h4 class="text-3xl font-bold font-jost">
                                                <span>{{ $testimonial['author'] }}</span>
                                            </h4>
                                            <span class="text-sm font-jost">{{ $testimonial['role'] ?? 'Cursant' }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif
