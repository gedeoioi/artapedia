<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url><loc>{{ url('/') }}</loc></url>
    <url><loc>{{ route('support.faq') }}</loc></url>
    <url><loc>{{ route('support.contact') }}</loc></url>
    <url><loc>{{ route('legal.refund') }}</loc></url>
    <url><loc>{{ route('legal.terms') }}</loc></url>
    <url><loc>{{ route('legal.privacy') }}</loc></url>
    @foreach($products as $p)
    <url><loc>{{ route('checkout.show', $p) }}</loc><lastmod>{{ $p->updated_at->toAtomString() }}</lastmod></url>
    @endforeach
</urlset>
