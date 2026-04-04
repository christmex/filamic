@use('App\Filament\Finance\Resources\Students\Pages\EditStudent')
@use('App\Enums\InvoiceTypeEnum')
<div>
    <!-- Header -->
    <div class="flex items-start justify-between">
        <a href="{{ EditStudent::getUrl(['record' => $getRecord()->getKey()]) }}" class="flex items-center gap-3 group"
            x-tooltip="{
                    content: '{{ $getRecord()->name }}',
                    theme: $store.theme,
                }">
            <div
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-linear-to-br from-indigo-50 to-purple-50 text-indigo-600 dark:from-indigo-900/20 dark:to-purple-900/20 dark:text-indigo-400 group-hover:outline-orange-500 group-hover:outline">
                <span class="text-sm font-medium">{{ $getRecord()->initials }}</span>
            </div>
            <div>
                <div
                    class="text-base font-medium text-gray-900 dark:text-white group-hover:underline transition-all duration-300">
                    {{ $getRecord()->displayName }}
                </div>
                {{-- TODO: Please implement the right current classroom name --}}
                <p
                    class="text-xs text-gray-500 group-hover:text-gray-900 dark:group-hover:text-gray-100 transition-all duration-300">
                    {{ $getRecord()->school?->name }}
                    ({{ $getRecord()->classroom?->name }})
                </p>
            </div>
        </a>
        {{-- <div
            class="rounded-full border border-gray-200 p-1.5 text-gray-400 hover:text-gray-600 dark:border-white/10 dark:text-gray-600 dark:hover:text-gray-400 cursor-pointer"
            x-tooltip="{
                content: 'Tampilkan Detail Tagihan',
                theme: $store.theme,
            }">
            <x-tabler-chevrons-down width="16" height="16"></x-tabler-chevrons-down>
        </div> --}}
    </div>

    {{-- <div class="mt-5 rounded-lg border border-gray-100 bg-gray-50 p-2.5 dark:border-white/5 dark:bg-[#18181b]/50">
        <div class="flex items-center justify-between">
            <span class="text-[10px] font-medium uppercase tracking-wide text-gray-400">NIS</span>
            <span class="text-xs font-medium text-gray-900 dark:text-white">{{ $getRecord()->nis }}</span>
        </div>
        <div class="mt-1 flex items-center justify-between">
            <span class="text-[10px] font-medium uppercase tracking-wide text-gray-400">NISN</span>
            <span class="text-xs text-gray-500">{{ $getRecord()->nisn }}</span>
        </div>
    </div> --}}

    @if ($getRecord()->getMissingData()->isNotEmpty())
        <div class="mt-5 rounded-lg border border-amber-100 bg-amber-50 p-3 dark:border-amber-900/20 dark:bg-amber-900/10">
            <div class="flex items-start gap-2.5">
                <x-tabler-bell class="shrink-0 text-amber-600 dark:text-amber-500" width="18"></x-tabler-bell>
                <div>
                    {{-- <p class="text-xs font-medium text-amber-900 dark:text-amber-400">Enrollment Pending</p> --}}
                    <p class="mt-0.5 text-xs leading-relaxed text-amber-900/70 dark:text-amber-500/70">
                        {{ $getRecord()->getMissingData()->first() }}
                    </p>
                </div>
            </div>
        </div>
    @endif

    <!-- Virtual Accounts -->
    <div class="mt-5 grid grid-cols-2 gap-4">
        <div class="rounded-lg border border-blue-500 bg-blue-100 p-3  dark:border-blue/5 dark:bg-[#18181b]">
            <div class="mb-1 flex items-center gap-1.5 text-xs font-medium text-blue-500">
                <x-dynamic-component :component="InvoiceTypeEnum::MONTHLY_FEE->getIcon()" width="14"
                    height="14"></x-dynamic-component>
                <span>VA {{ InvoiceTypeEnum::MONTHLY_FEE->getShortLabel() }}</span>
            </div>
            <p class="font-mono text-xs font-medium text-gray-900 dark:text-gray-200">
                {{ $getRecord()->monthly_fee_virtual_account ?? '-' }}
            </p>
        </div>
        <div class="rounded-lg border border-orange-500 bg-orange-100 p-3  dark:border-orange/5 dark:bg-[#18181b]">
            <div class="mb-1 flex items-center gap-1.5 text-xs font-medium text-orange-500">
                <x-dynamic-component :component="InvoiceTypeEnum::BOOK_FEE->getIcon()" width="14"
                    height="14"></x-dynamic-component>
                <span>VA {{ InvoiceTypeEnum::BOOK_FEE->getShortLabel() }}</span>
            </div>
            <p class="font-mono text-xs font-medium text-gray-900 dark:text-gray-200">
                {{ $getRecord()->book_fee_virtual_account ?? '-' }}
            </p>
        </div>
    </div>

    @if (!$getRecord()->hasUnpaidInvoice())
        {{-- <div
            class="mt-6 flex flex-col items-center justify-center border-t border-gray-100 pt-4 text-center dark:border-white/5">
            <x-tabler-circle-check class="mb-1 text-emerald-500" width="20"></x-tabler-circle-check>
            <p class="text-xs text-gray-500">Tidak Ada Tagihan</p>
        </div> --}}
        <x-filament::empty-state icon="tabler-circle-check" heading="" description="Tidak Ada Tagihan" :contained="false"
            icon-color="success" class="mt-6 items-center justify-center border-t border-gray-100">
        </x-filament::empty-state>
    @else
        <!-- Total Unpaid -->
        <div class="mt-6 border-t border-gray-100 pt-4 dark:border-white/5">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-[10px] uppercase tracking-wide text-gray-400">Total SPP</p>
                    <p class="mt-0.5 text-sm font-medium text-gray-900 dark:text-white">
                        {{ $getRecord()->getTotalUnpaidMonthlyFee(true) }}
                    </p>
                </div>
                <div class="text-right">
                    <p class="text-[10px] uppercase tracking-wide text-gray-400">Total Buku</p>
                    <p class="mt-0.5 text-sm font-medium text-gray-900 dark:text-white">
                        {{ $getRecord()->getTotalUnpaidBookFee(true) }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Invoices -->
        <div class="mt-6 border-b border-gray-100 pb-4" x-data="{ open: false }">
            <div @click="open = !open" class="flex items-center justify-between cursor-pointer text-gray-400 mb-3">
                <p class="text-[10px] font-medium uppercase tracking-wider ">Daftar Tagihan</p>
                <div class="flex items-center gap-1">
                    <x-tabler-chevron-up class="w-4 h-4" x-show="open" x-cloak></x-tabler-chevron-up>
                    <x-tabler-chevron-down class="w-4 h-4" x-show="!open"></x-tabler-chevron-down>
                </div>
            </div>
            <div class="space-y-3" x-show="open" x-cloak>
                @foreach ($getRecord()->unpaidInvoices as $invoice)
                    <div class="flex items-center justify-between text-xs">
                        <div class="flex items-center gap-2">
                            {{-- <div class="h-1.5 w-1.5 rounded-full bg-{{ $invoice->type->getColor() }}-500"></div> --}}
                            <span class="text-gray-600 dark:text-gray-400">
                                {{ $loop->iteration }}.
                                {{ $invoice->type->getShortLabel() }}
                                ({{ filled($invoice->month) ? $invoice->month->getLabel() . ' - ' : 'TA' }}
                                {{ $invoice->school_year_name }})
                            </span>
                        </div>
                        <span class="font-medium text-gray-900 dark:text-white">
                            {{ $invoice->formatted_total_amount }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

</div>