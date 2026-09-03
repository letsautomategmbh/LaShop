@extends('layouts.app')

@section('title', __('Store'))

{{-- Die Seitenleiste der Modulverwaltung, damit der Sprung von dort hierher
     den Zusammenhang behält: wer aus "Modules" kommt, findet den Weg zurück
     an derselben Stelle. --}}
@section('sidebar')
    @include('partials/sidebar_menu_toggle')
    @include('lastore::partials.sidebar_menu')
@endsection

@section('content')

    {{-- Der Hinweis auf eine neue Fassung von LaShop selbst.

         Er erscheint von SELBST, sobald etwas bereitliegt -- das ist der ganze
         Sinn. Ein Weg, den man suchen muss, wird einmal gegangen und dann nie
         wieder; ein Modul, das nie aktualisiert wird, bekommt keine
         Sicherheitskorrektur und lehnt nach einem Schluesselwechsel jedes
         Paket ab.

         Kein eigener Netzaufruf hier: der Controller hat den Befund
         hinterlegt, die Ansicht liest ihn nur. --}}
    @if (!empty($selbstNeu))
        <div class="alert alert-info">
            <strong>{{ __('Für LaShop liegt Fassung :v bereit.', ['v' => $selbstNeu]) }}</strong>
            <form method="POST" action="{{ route('lastore.self_update') }}" class="form-inline" style="display:inline-block;margin-left:10px">
                {{ csrf_field() }}
                <button type="submit" class="btn btn-primary btn-sm">{{ __('Jetzt aktualisieren') }}</button>
            </form>
            {{-- Was sich aendert, nicht nur DASS sich etwas aendert.

                 Aufklappbar und nicht offen: der Hinweis steht ueber allem
                 anderen auf der Seite, und eine Notiz von zehn Zeilen waere
                 dort eine Wand. Wer wissen will, was kommt, klickt einmal.

                 Die Notiz kommt aus derselben Antwort wie die Fassung und
                 liegt neben ihr in einer Option -- kein zweiter Netzaufruf
                 beim Zeichnen der Seite. --}}
            @if (!empty($selbstNotiz))
                <details style="margin-top:6px">
                    <summary style="cursor:pointer"><small>{{ __('Was sich ändert') }}</small></summary>
                    <div class="text-muted" style="margin-top:4px;max-width:68em">
                        <small>{!! nl2br(e($selbstNotiz)) !!}</small>
                    </div>
                </details>
            @endif

            <div class="text-muted" style="margin-top:6px">
                <small>{{ __('Das Paket wird geprüft, bevor es ausgepackt wird: erst die Signatur, dann die Prüfsumme. Der bisherige Stand bleibt als Sicherung liegen.') }}</small>
            </div>
        </div>
    @endif
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
                 haben aber noch keine Lizenz aus dem Store.

                 Hier steht nur noch der HINWEIS. Das Formular dazu stand
                 vorher doppelt - einmal hier und einmal in der Tabelle
                 darunter -, und zwei Stellen für dieselbe Handlung sind eine
                 Stelle zu viel: der Kunde fragt sich, ob es einen Unterschied
                 gibt. Getragen wird es jetzt von der Spalte "Aktion". --}}
            <div class="alert alert-info margin-bottom">
                <strong>{{ trans_choice('{1}Ein Modul läuft schon, noch ohne Lizenz aus dem Store.|[2,*]:count Module laufen schon, noch ohne Lizenz aus dem Store.', count($adoptable), ['count' => count($adoptable)]) }}</strong>
                <br>{{ __('Sie laufen unverändert weiter. Mit dem Schlüssel ändert sich nur, woher sie ihre Updates beziehen — unten in der Liste unter „Lizenz übernehmen".') }}
            </div>
        @endif

        {{-- Karten statt Tabelle, im Stil der FreeScout-Module: Sinnbild,
             Beschreibung, installierte Fassung, Katalogfassung, Zustand.
             Eine Tabelle zeigt Spalten; eine Karte zeigt ein Modul. --}}
        <div class="row">
            @foreach ($inventory as $row)
                @if ($row['state'] === \Modules\LaStore\Support\InstalledModules::STATE_FOREIGN)
                    @continue
                @endif
                @include('lastore::partials.module_card', ['row' => $row])
            @endforeach
        </div>

        {{-- Hier stand die Warnung "N Module tragen noch Zugangsdaten in
             ihrer module.json". Sie sagte die Wahrheit, aber sie stand am
             falschen Ort: ändern kann das nur, wer die Module baut, nicht
             wer sie betreibt. Der Verwalter einer Installation las eine
             Warnung, gegen die er nichts tun konnte.

             Die Prüfung selbst ist NICHT weg -- InstalledModules::
             withCredentials() gibt es weiterhin und sie ist geprüft. Sie
             gehört in den Betrieb bei uns, nicht auf den Bildschirm des
             Kunden. --}}
        <p class="text-muted" style="margin-bottom:12px">
            <small>
                {{ __('Powered by') }}
                <a href="https://letsautomate.ch" target="_blank" rel="noopener noreferrer">let&rsquo;s automate gmbh</a>
            </small>
        </p>

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

        {{-- Der Offline-Weg als Popup, aufgerufen aus der Werkzeugleiste.

             Vorher stand er zugeklappt am Fuss der Seite, unter der
             Modulliste. Das war zweimal falsch: er sah aus wie ein Anhang,
             obwohl er eine Handlung ist, und seine Aufschrift "Server ohne
             Internetverbindung" las sich als Befund über diesen Server statt
             als Frage.

             Bootstraps eigenes Modal, kein eigenes Javascript: FreeScout
             bringt es mit, und der Knopf braucht nur data-toggle. --}}

@endsection

{{-- Das Popup gehoert an das ENDE des <body>, nicht in den Inhalt.

     Der Grund ist eine Stapelfalle: ein fest positioniertes Element gilt mit
     seinem z-index nur INNERHALB des naechsten Elternteils, das selbst einen
     Stapelzusammenhang aufmacht. FreeScouts Inhaltsbehaelter tut das, und die
     Kopfleiste (z-index 1000, drei Reihen, 203 Pixel hoch) lag damit ueber
     dem Popup -- der Titel war halb verdeckt, egal welchen z-index das Modal
     selbst trug. Auch 10050 half nichts.

     `body_bottom` ist der Haken im Layout des Kerns, direkt vor den Skripten
     und ausserhalb aller Behaelter. Dort gilt der z-index gegen alles. --}}
@section('body_bottom')
        {{-- Eigene Stile, knapp und nur fuer dieses Fenster:

             z-index ueber ALLES. FreeScout haelt ein festes Element mit 9999
             auf jeder Seite (der Behaelter fuer die schwebenden Meldungen).
             Es ist null Pixel hoch und stoert nicht, aber ein Popup, das
             darunter liegen KANN, ist eine Wanze, die man nur bei bestimmten
             Fenstergroessen sieht. Darum 10050 statt Bootstraps 1050.

             Abstand nach oben, damit der Titel nicht an der Kopfleiste klebt
             -- Andre sah ihn dort halb verdeckt.

             Und eine Hoehengrenze mit eigenem Rollbereich: bei kleinem Fenster
             schob der Absendeknopf sonst aus dem Bild, und ein Knopf, den man
             nicht erreicht, ist kein Knopf. --}}
        <style>
            #lastore-offline { z-index: 10050; }
            #lastore-offline + .modal-backdrop, .modal-backdrop.lastore { z-index: 10040; }
            #lastore-offline .modal-dialog { margin-top: 60px; }
            #lastore-offline .modal-body { max-height: calc(100vh - 220px); overflow-y: auto; }
            @media (max-height: 600px) {
                #lastore-offline .modal-dialog { margin-top: 20px; }
            }
        </style>

        <div class="modal fade" id="lastore-offline" tabindex="-1" role="dialog"
             aria-labelledby="lastore-offline-titel">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('Schliessen') }}">
                            <span aria-hidden="true">&times;</span>
                        </button>
                        <h4 class="modal-title" id="lastore-offline-titel">{{ __('Lizenz ohne Internetverbindung einlesen') }}</h4>
                    </div>

                    <div class="modal-body">
                        <p class="text-muted">
                            {{ __('Das Kundenportal erzeugt aus der Kennung dieses Servers eine signierte Lizenzdatei. Diese hier hochladen.') }}
                        </p>

                        {{-- isRegistered() und installation_id, NICHT ->uuid: die
                             Spalte heisst installation_id, und ->uuid gibt es
                             nicht. Mein erster Entwurf fragte danach, bekam
                             darum immer null und hätte den Upload jedem Kunden
                             verborgen. --}}
                        @if ($installation->isRegistered())
                            <p>
                                {{ __('Kennung dieses Servers') }}:
                                <code class="mono">{{ $installation->installation_id }}</code>
                            </p>

                            <form method="POST" action="{{ route('lastore.licenses.offline') }}" enctype="multipart/form-data">
                                {{ csrf_field() }}
                                <input type="file" name="license_file" class="form-control input-sm" accept=".txt,.lic,text/plain" required>
                                <button type="submit" class="btn btn-primary btn-sm margin-top">{{ __('Lizenzdatei einlesen') }}</button>
                            </form>
                        @else
                            {{-- Ohne Kennung gibt es nichts zu erzeugen. Die Kennung
                                 entsteht bei der ersten Anmeldung am Shop — vorher ist
                                 der Offline-Weg gar nicht gangbar, und ein leeres
                                 Feld hier wäre eine Einladung zum Rätseln. --}}
                            <p class="text-muted">
                                {{ __('Dieser Server ist noch nicht am Shop angemeldet. Die Kennung entsteht mit der ersten Lizenz — danach steht sie hier.') }}
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
@endsection
