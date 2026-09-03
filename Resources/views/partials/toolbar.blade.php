{{-- Eine Seite, keine Reiter.

     Vorher standen hier "Katalog" und "Lizenzen". Die Lizenzseite ist weg -
     Lizenzen verwaltet der Kunde im Portal -, und eine Reiterleiste mit einem
     Reiter ist keine Navigation, sondern Zierde. Stattdessen die zwei Wege
     nach draussen, wie FreeScout sie bei seinen eigenen Modulen auch hat
     ("Lizenz erwerben", "Details ansehen"). --}}
<div class="btn-toolbar margin-bottom">
    <div class="btn-group btn-group-sm">
        <a href="{{ config('lastore.shop_url') }}" target="_blank" rel="noopener" class="btn btn-default">
            <i class="glyphicon glyphicon-shopping-cart"></i> {{ __('Module kaufen') }}
        </a>
        <a href="{{ config('lastore.portal_url') }}" target="_blank" rel="noopener" class="btn btn-default">
            <i class="glyphicon glyphicon-certificate"></i> {{ __('Lizenzen im Portal') }}
        </a>
    </div>

    <form method="POST" action="{{ route('lastore.licenses.refresh') }}" class="btn-group btn-group-sm" style="margin-left:10px">
        {{ csrf_field() }}
        <button type="submit" class="btn btn-default">
            <i class="glyphicon glyphicon-refresh"></i> {{ __('Lizenzen jetzt prüfen') }}
        </button>
    </form>

    {{-- Autopilot. Ein Schalter und kein Kästchen mit Speichern-Knopf: es ist
         eine einzige Entscheidung, und ein zweiter Druck zum Bestätigen wäre
         einer zu viel.

         Der Zustand steht im Text des Knopfes, nicht nur in seiner Farbe —
         "an" und "aus" sind an einer eingefärbten Fläche allein nicht
         ablesbar, und wer den Unterschied nicht sieht, drückt zweimal. --}}
    @php $autopilot = (bool) \Option::get('lastore.autopilot'); @endphp
    <form method="POST" action="{{ route('lastore.autopilot') }}" class="btn-group btn-group-sm" style="margin-left:10px">
        {{ csrf_field() }}
        <input type="hidden" name="autopilot" value="{{ $autopilot ? 0 : 1 }}">
        <button type="submit" class="btn {{ $autopilot ? 'btn-success' : 'btn-default' }}"
                title="{{ __('Aktualisiert nachts um 03:20 alle lizenzierten Module — Signaturprüfung inbegriffen.') }}">
            <i class="glyphicon {{ $autopilot ? 'glyphicon-ok-circle' : 'glyphicon-record' }}"></i>
            {{ $autopilot ? __('Autopilot: an') : __('Autopilot: aus') }}
        </button>
    </form>

    {{-- Der Offline-Weg gehoert hierher, zu den Handlungen -- nicht an den
         Fuss der Seite. Er wird selten gebraucht, aber wenn, dann sucht man
         ihn oben. Das Popup selbst steht in index.blade.php. --}}
    <div class="btn-group btn-group-sm" style="margin-left:10px">
        <button type="button" class="btn btn-default" data-toggle="modal" data-target="#lastore-offline"
                title="{{ __('Für Server ohne Internetverbindung: signierte Lizenzdatei aus dem Portal einlesen.') }}">
            <i class="glyphicon glyphicon-import"></i> {{ __('Lizenzdatei einlesen') }}
        </button>
    </div>

    @if (config('lastore.transport') === 'static')
        <span class="label label-warning" style="display:inline-block;margin-left:10px;padding:6px 10px;">
            {{ __('Abgelegte Antworten — kein Server') }}
        </span>
    @endif
</div>
