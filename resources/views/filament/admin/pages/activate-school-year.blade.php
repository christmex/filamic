<x-filament-panels::page>
    <div
        x-data="{}"
        x-on:process-next-chunk.window="$wire.processChunk()"
    >

        {{-- ═══════════════════════════════════════════════════
             ERROR STATE
        ═══════════════════════════════════════════════════ --}}
        @if ($state === 'error')
            <x-filament::section>
                <div class="flex flex-col items-center gap-4 py-6 text-center sm:flex-row sm:text-left sm:py-2">
                    <div class="flex size-14 shrink-0 items-center justify-center rounded-full bg-danger-50 dark:bg-danger-950 mx-auto sm:mx-0">
                        <x-filament::icon icon="tabler-circle-x" class="size-8 text-danger-500" />
                    </div>
                    <div class="flex-1">
                        <p class="text-base font-semibold text-danger-600 dark:text-danger-400">Terjadi Kesalahan</p>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $errorMessage }}</p>
                    </div>
                </div>

                <div class="mt-4 flex items-center justify-end gap-3 border-t border-gray-100 dark:border-gray-800 pt-4">
                    {{ $this->resetStateAction }}
                    <x-filament::button
                        tag="a"
                        href="{{ \App\Filament\Admin\Resources\SchoolYears\SchoolYearResource::getUrl('index') }}"
                        color="gray"
                        icon="tabler-arrow-left"
                    >
                        Kembali ke Tahun Ajaran
                    </x-filament::button>
                </div>
            </x-filament::section>

        {{-- ═══════════════════════════════════════════════════
             IDLE STATE
        ═══════════════════════════════════════════════════ --}}
        @elseif ($state === 'idle')
            <x-filament::section>
                <x-slot name="heading">Ringkasan</x-slot>
                <x-slot name="description">
                    Periksa data sebelum memulai proses kenaikan kelas ke tahun ajaran
                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $schoolYearName }}</span>.
                </x-slot>

                {{-- Stat Cards --}}
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">

                    {{-- Total Drafts --}}
                    <div class="flex items-center gap-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 p-4">
                        <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary-100 dark:bg-primary-950">
                            <x-filament::icon icon="tabler-users" class="size-5 text-primary-600 dark:text-primary-400" />
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white leading-none">{{ number_format($total) }}</p>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Total Siswa Draft</p>
                        </div>
                    </div>

                    {{-- Missing Classroom --}}
                    <div class="flex items-center gap-4 rounded-xl border p-4
                        {{ $missingClassroomCount > 0
                            ? 'border-danger-200 dark:border-danger-800 bg-danger-50 dark:bg-danger-950'
                            : 'border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900' }}">
                        <div class="flex size-10 shrink-0 items-center justify-center rounded-full
                            {{ $missingClassroomCount > 0
                                ? 'bg-danger-100 dark:bg-danger-900'
                                : 'bg-success-100 dark:bg-success-950' }}">
                            <x-filament::icon
                                icon="{{ $missingClassroomCount > 0 ? 'tabler-alert-triangle' : 'tabler-circle-check' }}"
                                class="size-5 {{ $missingClassroomCount > 0 ? 'text-danger-500' : 'text-success-500' }}"
                            />
                        </div>
                        <div>
                            <p class="text-2xl font-bold leading-none
                                {{ $missingClassroomCount > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-success-600 dark:text-success-400' }}">
                                {{ number_format($missingClassroomCount) }}
                            </p>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Belum Ada Kelas Tujuan</p>
                        </div>
                    </div>

                    {{-- Ready to Process --}}
                    <div class="flex items-center gap-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 p-4">
                        <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-success-100 dark:bg-success-950">
                            <x-filament::icon icon="tabler-checks" class="size-5 text-success-600 dark:text-success-400" />
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white leading-none">
                                {{ number_format($total - $missingClassroomCount) }}
                            </p>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Siap Diproses</p>
                        </div>
                    </div>
                </div>

                {{-- Warning Banner --}}
                @if ($missingClassroomCount > 0)
                    <div class="mt-4 flex items-start gap-3 rounded-xl border border-danger-200 dark:border-danger-800 bg-danger-50 dark:bg-danger-950 p-4">
                        <x-filament::icon icon="tabler-alert-triangle" class="size-5 text-danger-500 shrink-0 mt-0.5" />
                        <p class="text-sm text-danger-700 dark:text-danger-300">
                            <strong>{{ number_format($missingClassroomCount) }} siswa</strong> belum memiliki kelas tujuan.
                            Tentukan kelas tujuan semua siswa sebelum melanjutkan.
                        </p>
                    </div>
                @elseif ($total === 0)
                    <div class="mt-4 flex items-start gap-3 rounded-xl border border-warning-200 dark:border-warning-800 bg-warning-50 dark:bg-warning-950 p-4">
                        <x-filament::icon icon="tabler-info-circle" class="size-5 text-warning-500 shrink-0 mt-0.5" />
                        <p class="text-sm text-warning-700 dark:text-warning-300">
                            Tidak ada siswa draft yang perlu diproses untuk tahun ajaran ini.
                        </p>
                    </div>
                @endif

                {{-- Actions --}}
                <div class="mt-6 flex items-center justify-between border-t border-gray-100 dark:border-gray-800 pt-4">
                    <x-filament::button
                        tag="a"
                        href="{{ \App\Filament\Admin\Resources\SchoolYears\SchoolYearResource::getUrl('index') }}"
                        color="gray"
                        icon="tabler-arrow-left"
                    >
                        Kembali
                    </x-filament::button>

                    {{ $this->startActivationAction }}
                </div>
            </x-filament::section>

        {{-- ═══════════════════════════════════════════════════
             PROCESSING STATE
        ═══════════════════════════════════════════════════ --}}
        @elseif ($state === 'processing')
            <x-filament::section>
                <x-slot name="heading">Sedang Memproses...</x-slot>
                <x-slot name="description">Jangan tutup atau refresh halaman ini hingga proses selesai.</x-slot>

                <div class="space-y-6">

                    {{-- Progress Bar --}}
                    <div class="space-y-2">
                        <div class="flex items-center justify-between text-sm">
                            <span class="font-medium text-gray-700 dark:text-gray-300">
                                <span wire:loading.remove wire:target="processChunk">
                                    {{ number_format($processed) }} / {{ number_format($total) }} siswa
                                </span>
                                <span wire:loading wire:target="processChunk" class="flex items-center gap-1.5">
                                    <x-filament::loading-indicator class="size-3.5" />
                                    Memproses batch...
                                </span>
                            </span>
                            <span class="tabular-nums font-semibold text-primary-600 dark:text-primary-400">
                                {{ $this->getProgress() }}%
                            </span>
                        </div>

                        <div class="h-3 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                            <div
                                class="h-3 rounded-full bg-primary-500 transition-all duration-500 ease-out"
                                style="width: {{ max(2, $this->getProgress()) }}%"
                            ></div>
                        </div>
                    </div>

                    {{-- Live Breakdown --}}
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div class="rounded-lg bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 p-3 text-center">
                            <p class="text-lg font-bold text-gray-900 dark:text-white tabular-nums">{{ number_format($processed) }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Diproses</p>
                        </div>
                        <div class="rounded-lg bg-success-50 dark:bg-success-950 border border-success-200 dark:border-success-800 p-3 text-center">
                            <p class="text-lg font-bold text-success-600 dark:text-success-400 tabular-nums">{{ number_format($promoted) }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Naik Kelas</p>
                        </div>
                        <div class="rounded-lg bg-warning-50 dark:bg-warning-950 border border-warning-200 dark:border-warning-800 p-3 text-center">
                            <p class="text-lg font-bold text-warning-600 dark:text-warning-400 tabular-nums">{{ number_format($stayed) }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Tinggal Kelas</p>
                        </div>
                        <div class="rounded-lg bg-info-50 dark:bg-info-950 border border-info-200 dark:border-info-800 p-3 text-center">
                            <p class="text-lg font-bold text-info-600 dark:text-info-400 tabular-nums">{{ number_format($graduated) }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Lulus</p>
                        </div>
                    </div>

                </div>
            </x-filament::section>

        {{-- ═══════════════════════════════════════════════════
             COMPLETED STATE
        ═══════════════════════════════════════════════════ --}}
        @elseif ($state === 'completed')
            <x-filament::section>

                {{-- Success Header --}}
                <div class="flex flex-col items-center gap-3 py-4 text-center sm:flex-row sm:text-left sm:py-0">
                    <div class="flex size-14 shrink-0 items-center justify-center rounded-full bg-success-100 dark:bg-success-950 mx-auto sm:mx-0">
                        <x-filament::icon icon="tabler-circle-check" class="size-8 text-success-500" />
                    </div>
                    <div>
                        <p class="text-lg font-bold text-gray-900 dark:text-white">Aktivasi Berhasil!</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Tahun ajaran <span class="font-semibold text-gray-700 dark:text-gray-300">{{ $schoolYearName }}</span> kini aktif.
                        </p>
                    </div>
                </div>

                {{-- Result Stats --}}
                <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">

                    <div class="flex flex-col items-center gap-2 rounded-xl border border-success-200 dark:border-success-800 bg-success-50 dark:bg-success-950 p-4">
                        <x-filament::icon icon="tabler-trending-up" class="size-6 text-success-500" />
                        <p class="text-2xl font-bold text-success-600 dark:text-success-400 tabular-nums">{{ number_format($promoted) }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 text-center">Naik Kelas</p>
                    </div>

                    <div class="flex flex-col items-center gap-2 rounded-xl border border-warning-200 dark:border-warning-800 bg-warning-50 dark:bg-warning-950 p-4">
                        <x-filament::icon icon="tabler-refresh" class="size-6 text-warning-500" />
                        <p class="text-2xl font-bold text-warning-600 dark:text-warning-400 tabular-nums">{{ number_format($stayed) }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 text-center">Tinggal Kelas</p>
                    </div>

                    <div class="flex flex-col items-center gap-2 rounded-xl border border-info-200 dark:border-info-800 bg-info-50 dark:bg-info-950 p-4">
                        <x-filament::icon icon="tabler-school" class="size-6 text-info-500" />
                        <p class="text-2xl font-bold text-info-600 dark:text-info-400 tabular-nums">{{ number_format($graduated) }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 text-center">Lulus</p>
                    </div>

                    <div class="flex flex-col items-center gap-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 p-4">
                        <x-filament::icon icon="tabler-user-off" class="size-6 text-gray-400" />
                        <p class="text-2xl font-bold text-gray-600 dark:text-gray-400 tabular-nums">{{ number_format($deactivated) }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 text-center">Tidak Aktif</p>
                    </div>

                </div>

                {{-- Back Action --}}
                <div class="mt-6 flex justify-end border-t border-gray-100 dark:border-gray-800 pt-4">
                    <x-filament::button
                        tag="a"
                        href="{{ \App\Filament\Admin\Resources\SchoolYears\SchoolYearResource::getUrl('index') }}"
                        color="primary"
                        icon="tabler-arrow-right"
                        icon-position="after"
                    >
                        Lihat Daftar Tahun Ajaran
                    </x-filament::button>
                </div>

            </x-filament::section>
        @endif

    </div>
</x-filament-panels::page>
