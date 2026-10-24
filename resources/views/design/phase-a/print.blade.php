<x-print.layout title="Print layout specimen" document-title="Print specimen · TG Inventory">
    <p class="print-screen-only">Fictional data; nothing is saved. Use Print to inspect page breaks and repeating collection headings. <a href="/style-guide/workflow">Return to workflow components</a>.</p>
    <x-print.header :values="['Source'=>'Sample source','Destination'=>'Sample destination','Requester'=>'Sample requester','Requested'=>'23 Oct 2026']" notes="" />
    <table class="print-material">
        <colgroup><col><col class="print-quantity-column"><col class="print-quantity-column"></colgroup>
        <thead><tr><th>Item</th><th class="print-quantity-heading">Requested</th><th class="print-quantity-heading">Fulfilled</th></tr></thead>
        <tbody>
        @foreach(['Sample category : Collection A', 'Sample category : Collection B'] as $collection)
            <tr class="print-collection"><td colspan="3"><table>
                <colgroup><col><col class="print-quantity-column"><col class="print-quantity-column"></colgroup>
                <thead><tr class="print-group-row"><th colspan="3">{{ $collection }}</th></tr></thead>
                <tbody>@foreach(range(1, 24) as $number)
                    <tr><td class="print-item">Sample item {{ $number }}</td><td class="print-quantity">{{ $number * 10 }}</td><td class="print-quantity"></td></tr>
                @endforeach</tbody>
            </table></td></tr>
        @endforeach
        </tbody>
    </table>
</x-print.layout>
