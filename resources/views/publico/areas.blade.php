    <section id="areas" class="zoo-areas" aria-labelledby="areas-titulo">
        <div class="max-w-xl">
            <h2 id="areas-titulo" class="titular-nota text-[28px] leading-[1.2] text-tinta sm:text-[36px]">
                Las áreas del ZooMAT
            </h2>
            <p class="mt-4 text-base leading-[1.55] text-pizarra">
                Explora las áreas del zoológico y empieza a imaginar tu visita.
                Elige una para conocer sus especies.
            </p>
        </div>

        <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach (config('recorridos') as $recorrido)
                <a href="#ficha-{{ $recorrido['slug'] }}"
                   data-ficha="ficha-{{ $recorrido['slug'] }}"
                   class="tarjeta-recorrido group {{ $recorrido['tinte'] }} rounded-[12px] p-8
                          focus:outline-none focus-visible:ring-3 focus-visible:ring-jade/30">


                    <span class="relative block text-[22px] font-semibold leading-[1.3] text-carbon">
                        {{ $recorrido['nombre'] }}
                    </span>
                    <span class="relative mt-3 block max-w-[26ch] text-sm leading-[1.5] text-carbon/70">
                        {{ $recorrido['resumen'] }}
                    </span>
                    <span class="relative mt-5 inline-flex items-center gap-1.5 text-[13px] font-semibold text-carbon">
                        Ver especies
                        <span class="transition-transform group-hover:translate-x-0.5" aria-hidden="true">→</span>
                    </span>

                    @if ($recorrido['arte'])
                        <img src="{{ asset($recorrido['arte']) }}" alt="" loading="lazy" decoding="async"
                             class="arte-recorrido">
                    @endif
                </a>
            @endforeach
        </div>
    </section>


    @foreach (config('recorridos') as $recorrido)
        <dialog id="ficha-{{ $recorrido['slug'] }}" class="ficha"
                aria-labelledby="ficha-{{ $recorrido['slug'] }}-titulo">
            <article class="p-6 sm:p-8">

                <header class="flex items-start justify-between gap-6">
                    <div>
                        <h2 id="ficha-{{ $recorrido['slug'] }}-titulo"
                            class="titular-nota text-[24px] leading-[1.2] text-tinta">
                            {{ $recorrido['nombre'] }}
                        </h2>
                        <p class="mt-2 max-w-md text-sm leading-[1.5] text-pizarra">
                            {{ $recorrido['resumen'] }}
                        </p>
                    </div>


                    <button type="button" data-cerrar
                            class="btn-nota-secundario min-h-[44px] shrink-0">Cerrar</button>
                </header>

                <ul class="mt-8 grid gap-6 sm:grid-cols-2">
                    @foreach ($recorrido['especies'] as $especie)
                        <li>
                            @if ($especie['foto'])
                                <img src="{{ asset($especie['foto']) }}"
                                     alt="{{ $especie['nombre'] }} en el ZooMAT"
                                     loading="lazy" decoding="async"
                                     class="aspect-[4/3] w-full rounded-[8px] border border-filete object-cover">

                            @endif

                            <p class="mt-3 text-[15px] font-medium text-tinta">{{ $especie['nombre'] }}</p>
                            @if ($especie['cientifico'])
                                <p class="text-[13px] italic text-acero">{{ $especie['cientifico'] }}</p>
                            @endif
                            <p class="mt-1.5 text-sm leading-[1.5] text-pizarra">{{ $especie['nota'] }}</p>
                        </li>
                    @endforeach
                </ul>
            </article>
        </dialog>
    @endforeach

