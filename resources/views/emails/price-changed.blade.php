<x-mail::message>
# {{ $newPrice < $oldPrice ? '📉 Price Drop!' : '📈 Price Increase' }}

**{{ $product->product_name }}**

| | |
|---|---|
| Old price | {{ number_format($oldPrice, 2, ',', '.') }} {{ $product->currency }} |
| New price | {{ number_format($newPrice, 2, ',', '.') }} {{ $product->currency }} |
| Difference | {{ $newPrice < $oldPrice ? '-' : '+' }}{{ number_format(abs($newPrice - $oldPrice), 2, ',', '.') }} {{ $product->currency }} |

<x-mail::button :url="$product->url">
View Product
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
