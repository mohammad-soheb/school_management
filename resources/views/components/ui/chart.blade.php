{{--
    <x-ui.chart type="bar" :labels="['Apr', 'May', 'Jun']" :datasets="[
        ['label' => 'Collected', 'data' => [120000, 98000, 143500]],
        ['label' => 'Due',       'data' => [20000, 41000, 12000]],
    ]" height="280" label="Fee collection by month" />

    type      bar | line | doughnut | pie
    datasets  Chart.js dataset arrays ('label', 'data', and any Chart.js dataset option). Colours default to the palette.
    options   Chart.js options merged over the defaults (e.g. ['scales' => ['y' => ['stacked' => true]]])
    height    px (number) or any CSS length string
    label     accessible name; a hidden data table with the same numbers is rendered for screen readers.
    Chart.js is downloaded lazily, only when a chart is near the viewport.
--}}
@props(['type' => 'bar', 'labels' => [], 'datasets' => [], 'height' => 280, 'options' => [], 'label' => null])
@php
    $labels = array_values($labels);
    $config = [
        'type' => $type,
        'labels' => $labels,
        'datasets' => array_values($datasets),
        'options' => $options === [] ? new \stdClass : $options,
        'height' => $height,
    ];
    $name = $label ?? 'Chart';
@endphp
<figure {{ $attributes->class(['chart-box']) }} x-data="chart({{ \Illuminate\Support\Js::from($config) }})">
    <canvas x-ref="canvas" role="img" aria-label="{{ $name }}"></canvas>
    <table class="sr-only">
        <caption>{{ $name }}</caption>
        <thead>
            <tr>
                <th scope="col">Label</th>
                @foreach ($datasets as $dataset)
                    <th scope="col">{{ $dataset['label'] ?? 'Value' }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($labels as $index => $labelText)
                <tr>
                    <th scope="row">{{ $labelText }}</th>
                    @foreach ($datasets as $dataset)
                        <td>{{ $dataset['data'][$index] ?? '' }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</figure>
