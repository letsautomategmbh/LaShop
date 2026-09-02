@extends('layouts.app')

@section('title', $product['name'] ?? __('Produkt'))

@section('content')
    <div class="section-heading">
        {{ $product['name'] ?? __('Produkt') }}
    </div>

    @include('partials/flash_messages')

    <div class="row-container form-container margin-top">
        <p><a href="{{ route('lastore.index') }}">&larr; {{ __('Zurück zum Katalog') }}</a></p>

        <p>{{ $product['summary'] ?? '' }}</p>

        <table class="table">
            <tr><th style="width:220px">{{ __('Alias') }}</th><td class="mono">{{ $product['alias'] ?? '' }}</td></tr>
            <tr><th>{{ __('Aktuelle Version') }}</th><td>{{ $product['version'] ?? '—' }}</td></tr>
            <tr><th>{{ __('Benötigt FreeScout') }}</th><td>{{ $product['min_app_version'] ?? '—' }}</td></tr>
            <tr><th>{{ __('Benötigt PHP') }}</th><td>{{ $product['min_php'] ?? '—' }}</td></tr>
            <tr>
                <th>{{ __('Benötigt Module') }}</th>
                <td>
                    @php $requires = (array) ($product['requires'] ?? []); @endphp
                    @if ($requires)
                        @foreach ($requires as $need => $version)
                            <span class="mono">{{ $need }}</span> ({{ $version }})@if (!$loop->last), @endif
                        @endforeach
                        <br><small class="text-muted">{{ __('Der Store löst die Kette selbst auf und installiert in Reihenfolge.') }}</small>
                    @else
                        —
                    @endif
                </td>
            </tr>
        </table>

        @if (!$license)
            <form method="POST" action="{{ route('lastore.licenses.activate') }}" class="form-inline">
                {{ csrf_field() }}
                <input type="hidden" name="product_alias" value="{{ $product['alias'] ?? '' }}">
                <div class="form-group">
                    <label for="license_key">{{ __('Lizenzschlüssel') }}</label>
                    <input type="text" class="form-control input-sm mono" id="license_key" name="license_key"
                           style="width:340px" placeholder="LA-XXXXX-XXXXX-XXXXX-XXXXX-XXXXX" autocomplete="off">
                </div>
                <button type="submit" class="btn btn-primary btn-sm">{{ __('Aktivieren') }}</button>
            </form>
        @else
            <p class="text-success">{{ __('Lizenziert.') }} <a href="{{ route('lastore.licenses') }}">{{ __('Zu den Lizenzen') }}</a></p>
        @endif
    </div>
@endsection
