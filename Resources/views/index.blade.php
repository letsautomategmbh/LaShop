@extends('layouts.app')

@section('title', __('Store'))

{{-- Kein @section('sidebar'): das Layout reserviert dafuer eine zweite Spalte,
     und der Katalog hat dort nichts unterzubringen. --}}

@section('content')
    <div class="section-heading">
        {{ __('Store') }}
    </div>

    @include('partials/flash_messages')

    <div class="row-container form-container margin-top">
        @include('lastore::partials.toolbar')

        @if ($error)
            <div class="alert alert-warning">
                <strong>{{ __('Der Shop war nicht erreichbar.') }}</strong> {{ $error }}
                <br><small>{{ __('Gezeigt wird der zuletzt bekannte Stand.') }}</small>
            </div>
        @endif

        @if ($adoptable)
            {{-- Der Fall, der beim Umstieg zuerst eintritt: Module laufen schon,
                 haben aber noch keine Lizenz aus dem Store. Sie laufen dabei
                 ununterbrochen weiter - es aendert sich nur, woher die Updates
                 kommen. Deshalb steht das oben und nicht als Warnung. --}}
            <div class="panel panel-default margin-bottom">
                <div class="panel-heading">
                    <strong>{{ __('Bereits installiert, noch ohne Lizenz') }}</strong>
                </div>
                <div class="panel-body">
                    <p class="text-muted">
                        {{ __('Diese Module laufen unverändert weiter. Mit dem Schlüssel ändert sich nur, woher sie ihre Updates beziehen.') }}
                    </p>

                    @foreach ($adoptable as $row)
                        <form method="POST" action="{{ route('lastore.licenses.activate') }}" class="form-inline" style="margin-bottom:8px">
                            {{ csrf_field() }}
                            <input type="hidden" name="product_alias" value="{{ $row['alias'] }}">
                            <span class="mono" style="display:inline-block;min-width:200px"><strong>{{ $row['alias'] }}</strong></span>
                            <span class="text-muted" style="display:inline-block;min-width:80px">{{ $row['installed'] }}</span>
                            <input type="text" class="form-control input-sm mono" name="license_key" style="width:330px"
                                   placeholder="LA-XXXXX-XXXXX-XXXXX-XXXXX-XXXXX" autocomplete="off">
                            <button type="submit" class="btn btn-primary btn-sm">{{ __('Übernehmen') }}</button>
                        </form>
                    @endforeach
                </div>
            </div>
        @endif

        <table class="table table-striped">
            <thead>
                <tr>
                    <th>{{ __('Modul') }}</th>
                    <th>{{ __('Installiert') }}</th>
                    <th>{{ __('Im Katalog') }}</th>
                    <th>{{ __('Zustand') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($inventory as $row)
                    @if ($row['state'] === \Modules\LaStore\Support\InstalledModules::STATE_FOREIGN)
                        @continue
                    @endif
                    @php $badge = $labels[$row['state']]; @endphp
                    <tr>
                        <td>
                            @if ($row['in_catalog'])
                                <a href="{{ route('lastore.product', $row['alias']) }}"><strong>{{ $row['name'] }}</strong></a>
                            @else
                                <strong>{{ $row['name'] }}</strong>
                            @endif
                            <br><small class="text-muted mono">{{ $row['alias'] }}</small>
                        </td>
                        <td class="nowrap">
                            {{ $row['installed'] ?: '—' }}
                            @if ($row['installed'] && !$row['active'])
                                <br><small class="text-muted">{{ __('nicht aktiviert') }}</small>
                            @endif
                        </td>
                        <td class="nowrap">{{ $row['available'] ?: '—' }}</td>
                        <td class="nowrap"><span class="label label-{{ $badge[0] }}">{{ __($badge[1]) }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if ($credentials)
            {{-- Der Grund, warum die Uebernahme ueberhaupt gemacht wird. Steht
                 bewusst hier und nicht in einer Datei, die niemand liest. --}}
            <div class="alert alert-warning">
                <strong>{{ trans_choice('{1}Ein Modul trägt noch Zugangsdaten in seiner module.json.|[2,*]:count Module tragen noch Zugangsdaten in ihrer module.json.', count($credentials), ['count' => count($credentials)]) }}</strong>
                <br>
                {{ __('Sie stehen damit auch in der Git-Historie der jeweiligen Repositories. Sobald alle Installationen übernommen sind, kann das Token dort entfernt werden — dieses Modul holt Updates dann über die Lizenz.') }}
                <br>
                <small class="mono">{{ implode(', ', array_column($credentials, 'alias')) }}</small>
            </div>
        @endif

        <p class="text-muted">
            <small>
                {{ __('Installation') }}:
                @if ($installation->isRegistered())
                    <span class="mono">{{ $installation->installation_id }}</span>
                @else
                    {{ __('noch nicht angemeldet') }}
                @endif
                @if ($installation->isOffline()) · <strong>{{ __('Offline-Betrieb') }}</strong> @endif
            </small>
        </p>
    </div>
@endsection
