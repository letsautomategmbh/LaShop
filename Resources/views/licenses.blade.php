@extends('layouts.app')

@section('title', __('Lizenzen'))

@section('content')
    <div class="section-heading">
        {{ __('Lizenzen') }}
    </div>

    @include('partials/flash_messages')

    <div class="row-container form-container margin-top">
        @include('lastore::partials.toolbar')

        @if ($licenses->isEmpty())
            <p class="text-muted">{{ __('Noch keine Lizenz hinterlegt.') }}</p>
        @else
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>{{ __('Modul') }}</th>
                        <th>{{ __('Schlüssel') }}</th>
                        <th>{{ __('Zustand') }}</th>
                        <th class="text-right">{{ __('Sitze') }}</th>
                        <th>{{ __('Gültig bis') }}</th>
                        <th>{{ __('Geprüft') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($licenses as $license)
                        @php
                            $check = $verified[$license->product_alias];
                            $badge = $badges[$check->status()] ?? ['default', $check->status()];
                        @endphp
                        <tr>
                            <td><strong>{{ $license->product_alias }}</strong></td>
                            <td class="mono">{{ $license->keyPrefix() ?: '—' }}</td>
                            <td>
                                <span class="label label-{{ $badge[0] }}">{{ __($badge[1]) }}</span>
                                @if ($check->isOffline())
                                    <span class="label label-default">{{ __('Offline') }}</span>
                                @endif
                                @if ($license->last_error)
                                    <br><small class="text-danger">{{ $license->last_error }}</small>
                                @endif
                            </td>
                            <td class="text-right">{{ $license->seats ?: '—' }}</td>
                            <td class="nowrap">
                                {{-- Die Vertragslaufzeit, nicht die Gültigkeit des Tokens. --}}
                                {{ $license->valid_until ? $license->valid_until->format('d.m.Y') : __('unbefristet') }}
                                @if ($check->inGrace())
                                    <br><small class="text-warning">{{ __('Gnadenfrist bis') }} {{ $license->grace_until ? $license->grace_until->format('d.m.Y') : '' }}</small>
                                @elseif ($license->token_expires_at)
                                    <br><small class="text-muted">{{ __('nächste Prüfung vor') }} {{ $license->token_expires_at->format('d.m.Y') }}</small>
                                @endif
                            </td>
                            <td class="nowrap">{{ $license->checked_at ? $license->checked_at->format('d.m.Y H:i') : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if (!$installation->isOffline())
                <form method="POST" action="{{ route('lastore.licenses.refresh') }}" style="display:inline">
                    {{ csrf_field() }}
                    <button type="submit" class="btn btn-default btn-sm">{{ __('Jetzt prüfen') }}</button>
                </form>
            @endif
        @endif

        <hr>

        <h3>{{ __('Schlüssel eingeben') }}</h3>
        <form method="POST" action="{{ route('lastore.licenses.activate') }}" class="form-inline margin-bottom">
            {{ csrf_field() }}
            <div class="form-group">
                <label for="alias">{{ __('Modul-Alias') }}</label>
                <input type="text" class="form-control input-sm mono" id="alias" name="product_alias" style="width:200px" placeholder="bexiosubscriptions">
            </div>
            <div class="form-group">
                <label for="key">{{ __('Lizenzschlüssel') }}</label>
                <input type="text" class="form-control input-sm mono" id="key" name="license_key" style="width:340px"
                       placeholder="LA-XXXXX-XXXXX-XXXXX-XXXXX-XXXXX" autocomplete="off">
            </div>
            <button type="submit" class="btn btn-primary btn-sm">{{ __('Aktivieren') }}</button>
        </form>

        <h3>{{ __('Ohne Internetverbindung') }}</h3>
        <p class="text-muted">
            {{ __('Steht dieser Server ohne Verbindung nach draussen, erzeugt das Kundenportal aus der Installations-Kennung eine signierte Lizenzdatei. Diese hier hochladen.') }}
        </p>
        <p>
            {{ __('Installations-Kennung dieses Servers') }}:
            <code>{{ $installation->installation_id ?: __('noch keine — beim ersten Abgleich vergeben') }}</code>
        </p>
        <form method="POST" action="{{ route('lastore.licenses.offline') }}" enctype="multipart/form-data" class="form-inline">
            {{ csrf_field() }}
            <div class="form-group">
                <input type="file" name="license_file" accept=".lalic,text/plain">
            </div>
            <button type="submit" class="btn btn-default btn-sm">{{ __('Lizenzdatei übernehmen') }}</button>
        </form>
    </div>
@endsection
