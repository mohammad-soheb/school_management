{{-- Sample fee receipt on A5, to preview <x-layouts.print>. --}}
<x-layouts.print title="Receipt R-2026-1042" paper="a5" back="{{ url('/_ui') }}">
    <div class="flex items-start justify-between gap-4 border-b border-slate-300 pb-4">
        <div>
            <p class="text-lg font-semibold">Sunrise Public School</p>
            <p class="text-xs text-slate-600">12 Lake Road, Pune &middot; Phone 020 5550 1234</p>
        </div>
        <div class="text-right">
            <p class="text-xs uppercase tracking-wide text-slate-500">Fee receipt</p>
            <p class="text-base font-semibold tabular-nums">R-2026-1042</p>
            <p class="text-xs text-slate-600">29 Oct 2026</p>
        </div>
    </div>

    <dl class="mt-4 grid grid-cols-2 gap-x-6 gap-y-2 text-sm">
        <div><dt class="text-xs text-slate-500">Student</dt><dd class="font-medium">Aarav Sharma</dd></div>
        <div><dt class="text-xs text-slate-500">Admission no.</dt><dd class="font-medium tabular-nums">1042</dd></div>
        <div><dt class="text-xs text-slate-500">Class</dt><dd class="font-medium">5-A</dd></div>
        <div><dt class="text-xs text-slate-500">Mode</dt><dd class="font-medium">UPI</dd></div>
    </dl>

    <table class="mt-5 w-full border-collapse text-sm">
        <thead>
            <tr class="border-y border-slate-300 bg-slate-100 text-left text-xs uppercase tracking-wide text-slate-600">
                <th class="px-2 py-2 font-semibold">Fee head</th>
                <th class="px-2 py-2 text-right font-semibold">Amount</th>
            </tr>
        </thead>
        <tbody class="tabular-nums">
            <tr class="border-b border-slate-200"><td class="px-2 py-2">Tuition fee &middot; Term 2</td><td class="px-2 py-2 text-right">&#8377;8,500.00</td></tr>
            <tr class="border-b border-slate-200"><td class="px-2 py-2">Transport &middot; Oct</td><td class="px-2 py-2 text-right">&#8377;1,200.00</td></tr>
            <tr class="border-b border-slate-200"><td class="px-2 py-2">Late fine</td><td class="px-2 py-2 text-right">&#8377;50.00</td></tr>
        </tbody>
        <tfoot>
            <tr class="font-semibold"><td class="px-2 py-3 text-right">Total received</td><td class="px-2 py-3 text-right text-base">&#8377;9,750.00</td></tr>
        </tfoot>
    </table>

    <p class="mt-1 text-xs text-slate-500">Rupees nine thousand seven hundred fifty only.</p>

    <div class="mt-12 flex items-end justify-between text-xs text-slate-600">
        <p>This is a computer generated receipt.</p>
        <p class="border-t border-slate-400 px-6 pt-1">Accountant</p>
    </div>
</x-layouts.print>
