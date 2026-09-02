{{-- Dieselbe Seitenleiste wie auf FreeScouts Modulseite, damit der Sprung
     dorthin den Zusammenhang nicht verliert.

     NACHGEBAUT und nicht `modules/sidebar_menu` eingebunden - aus einem
     Grund, der nicht offensichtlich ist: dort sind die ersten zwei Einträge
     Sprungmarken auf derselben Seite (`#installed`, `#directory`). Von hier
     aus zeigen sie ins Leere. Sie brauchen die volle Adresse, und damit ist
     die Datei ohnehin eine andere.

     Der Preis: ändert FreeScout seine Seitenleiste, wandert das hier nicht
     mit. Dafür bricht auch nichts - es sind zwei Verweise auf eine Seite,
     die es gibt. --}}
<div class="sidebar-title">
    {{ __('Modules') }}
</div>
<ul class="sidebar-menu">
    <li>
        <a href="{{ route('modules') }}#installed">
            <i class="glyphicon glyphicon-saved"></i> {{ __('Installed Modules') }}
        </a>
    </li>
    <li>
        <a href="{{ route('modules') }}#directory">
            <i class="glyphicon glyphicon-briefcase"></i> {{ __('Modules Directory') }}
        </a>
    </li>
    <li class="active">
        <a href="{{ route('lastore.index') }}">
            <i class="glyphicon glyphicon-shopping-cart"></i> {{ __('LaShop') }}
        </a>
    </li>
</ul>
