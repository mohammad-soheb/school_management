<x-dev.section id="dataviz" title="Charts & progress" description="Chart.js loads lazily when a chart scrolls near the viewport; colours follow the theme. Each chart carries a hidden data table for screen readers.">
    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.card title="Fee collection" description="Collected vs due, by month (₹)">
            <x-ui.chart type="bar" label="Fee collection by month" :labels="['Jun', 'Jul', 'Aug', 'Sep', 'Oct']" :datasets="[
                ['label' => 'Collected', 'data' => [412000, 380500, 296000, 351000, 402500]],
                ['label' => 'Due', 'data' => [40000, 52000, 61000, 38000, 21000]],
            ]" />
        </x-ui.card>

        <x-ui.card title="Attendance" description="Daily present %, last two weeks">
            <x-ui.chart type="line" label="Daily attendance percentage" :labels="['14', '15', '16', '17', '18', '21', '22', '23', '24', '25']" :datasets="[
                ['label' => 'Present %', 'data' => [93, 95, 91, 94, 89, 96, 95, 92, 94, 95]],
            ]" />
        </x-ui.card>

        <x-ui.card title="Payment modes">
            <x-ui.chart type="doughnut" height="240" label="Payments by mode" :labels="['Cash', 'UPI', 'Cheque', 'Bank transfer']" :datasets="[
                ['label' => 'Receipts', 'data' => [182, 240, 31, 46]],
            ]" />
        </x-ui.card>

        <x-ui.card title="Progress">
            <div class="space-y-5">
                <div>
                    <p class="label">Fees collected (term 2)</p>
                    <x-ui.progress :value="72" tone="success" label="Fees collected" show-value />
                </div>
                <div>
                    <p class="label">Marks entered</p>
                    <x-ui.progress :value="38" :max="52" tone="brand" label="Marks entered" show-value />
                </div>
                <div>
                    <p class="label">Storage used</p>
                    <x-ui.progress :value="91" tone="danger" label="Storage used" show-value large />
                </div>
                <div>
                    <p class="label">Import</p>
                    <x-ui.progress :value="55" tone="warning" label="Import progress" />
                </div>
            </div>
        </x-ui.card>
    </div>
</x-dev.section>
