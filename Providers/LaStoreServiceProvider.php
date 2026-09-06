<?php

namespace Modules\LaStore\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Modules\LaStore\Console\AdoptCommand;
use Modules\LaStore\Console\ResetInstallationCommand;
use Modules\LaStore\Console\UpdateCommand;
use Modules\LaStore\Console\SelfUpdateCommand;
use Modules\LaStore\Console\SyncCommand;

class LaStoreServiceProvider extends ServiceProvider
{
    const MODULE_NAME = 'LaStore';
    const MODULE_ALIAS = 'lastore';

    public function boot()
    {
        $this->loadViews();
        $this->loadRoutes();
        $this->loadMigrations();
        $this->loadTranslations();
        $this->registerCommands();
        $this->registerHooks();
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../Config/config.php', self::MODULE_ALIAS);
    }

    protected function loadViews()
    {
        $this->loadViewsFrom(__DIR__.'/../Resources/views', self::MODULE_ALIAS);
    }

    protected function loadRoutes()
    {
        // Ein einzelnes require ohne umschliessende Route::group(), weil eine
        // reine Namespace-Gruppe RouteGroup::formatPrefix() unter PHP 8.1+
        // zerlegt - dieselbe Stelle wie in Bexio, Products und Hr.
        require __DIR__.'/../Http/routes.php';
    }

    protected function loadMigrations()
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
    }

    protected function loadTranslations()
    {
        $this->app['translator']->addJsonPath(__DIR__.'/../Resources/lang');
    }

    protected function registerCommands()
    {
        $this->commands([
            SyncCommand::class,
            ResetInstallationCommand::class,
            AdoptCommand::class,
            UpdateCommand::class,
            SelfUpdateCommand::class,
        ]);
    }

    protected function registerHooks()
    {
        \Eventy::addFilter('schedule', function ($schedule) {
            $schedule->command(SyncCommand::class)->dailyAt('05:30')->withoutOverlapping();

            /*
             * Autopilot: Updates ueber Nacht, wenn der Verwalter es
             * eingeschaltet hat. Standard ist AUS.
             *
             * 03:20 und nicht 05:30: der Katalogabgleich laeuft um 05:30, und
             * ein Update, das gleichzeitig Dateien tauscht, waehrend der
             * Abgleich den Katalog schreibt, ist eine Verschraenkung, die
             * niemand braucht. Und 03:20 statt 03:00, damit es nicht mit
             * jedem anderen Nachtlauf der Welt zusammenfaellt.
             *
             * --yes, weil niemand danebensitzt. Die Signaturpruefung faellt
             * damit NICHT weg: sie steckt in fetchVerified() und ist keine
             * Rueckfrage, sondern eine Bedingung.
             */
            if (\Option::get('lastore.autopilot')) {
                /*
                 * Der Schalter steht IM Befehl und nicht als Feld im Array.
                 *
                 * array('--yes' => true) baut die Zeile "lastore:update
                 * --yes='1'", und eine Schalter-Option nimmt keinen Wert:
                 * "The --yes option does not accept a value." Der Nachtlauf
                 * waere jede Nacht gescheitert, ohne dass es jemand sieht -
                 * genau die Art Fehler, die ein Autopilot nicht haben darf.
                 * Ausprobiert, nicht vermutet.
                 */
                $schedule->command('lastore:update --yes')
                    ->dailyAt('03:20')->withoutOverlapping();

                /*
                 * LaShop selbst NACH den uebrigen Modulen und in einem
                 * eigenen Lauf.
                 *
                 * Der Grund ist nicht Hoeflichkeit: dieser Befehl tauscht
                 * das Modul aus, in dem er steht. Waere er Teil desselben
                 * Durchlaufs, wechselte mitten darin der Boden -- danach
                 * findet der Autoloader nichts mehr. Als eigener Aufruf um
                 * 03:40 ist es die letzte Handlung eines eigenen Prozesses.
                 */
                $schedule->command('lastore:self-update --yes')
                    ->dailyAt('03:40')->withoutOverlapping();
            }

            /*
             * Nachsehen taeglich, unabhaengig vom Autopilot: nur so kann die
             * Oberflaeche "Fassung X liegt bereit" melden, ohne dass jemand
             * eingeschaltet hat, dass ueber Nacht getauscht wird. Die Sorge
             * war ja, dass ein Modul einmal installiert und nie mehr
             * angesehen wird -- ein Hinweis, der von selbst erscheint, ist
             * dagegen das Mindeste.
             */
            $schedule->command('lastore:self-update --pruefen')
                ->dailyAt('05:20')->withoutOverlapping();

            return $schedule;
        });

        /*
         * Unter Verwaltung, direkt hinter "Modules" - und NICHT als eigener
         * Punkt in der Hauptleiste.
         *
         * Dort gehoert es hin, weil es dasselbe tut: Module installieren,
         * aktualisieren, lizenzieren. Ein eigener Punkt neben "Mailbox" und
         * "Kunden" stellte es auf eine Stufe mit der taeglichen Arbeit,
         * obwohl ein Verwalter ihn ein paar Mal im Jahr braucht - und die
         * Hauptleiste ist bei jedem FreeScout mit Modulen ohnehin voll.
         *
         * Der Haken heisst 'menu.manage.append' und steht im Layout des
         * Kerns hinter Modules, Translate, Logs und System.
         */
        /*
         * Auf FreeScouts eigener Modulseite einen Eintrag in die Seitenleiste
         * haengen - neben "Installed Modules" und "Modules Directory".
         *
         * Das geht nur mit einem kleinen Skript, und zwar aus einem Grund, der
         * hier stehen soll: `resources/views/modules/sidebar_menu.blade.php`
         * im Kern hat KEINEN Eventy-Haken. Die Alternative waere, diese
         * Ansicht aus dem Modul zu ueberschreiben - dann fror unsere Kopie
         * FreeScouts Fassung ein, und eine Aenderung dort waere still
         * ueberstimmt. Ein angehaengtes <li> ist der kleinere Eingriff:
         * passt der Waehler eines Tages nicht mehr, fehlt ein Verweis - die
         * Seite bleibt heil.
         *
         * Nur auf dieser einen Seite, nicht global: der Haken 'javascript'
         * feuert auf jeder Seite.
         */
        \Eventy::addAction('javascript', function () {
            if (\Route::currentRouteName() !== 'modules') {
                return;
            }

            $url = route('lastore.index');
            $text = __('LaShop');

            /*
             * BLANKER Code, KEIN <script>-Tag.
             *
             * Der Haken steht im Layout INNERHALB von <script>…</script>
             * (app.blade.php, direkt vor </body>). Ein eigenes Tag hier
             * schliesst den umgebenden Block vorzeitig - und dann bricht
             * nicht nur der eigene Eintrag, sondern alles, was danach in
             * diesem Block steht, auch von anderen Modulen. Genau so ist es
             * beim Bauen passiert: das Skript stand im HTML und tat nichts.
             */
            echo '(function(){'
                .'var ul=document.querySelector("ul.sidebar-menu");'
                .'if(!ul){return;}'
                .'var li=document.createElement("li");'
                .'var a=document.createElement("a");'
                .'a.href='.json_encode($url).';'
                .'a.innerHTML=\'<i class="glyphicon glyphicon-shopping-cart"></i> \';'
                .'a.appendChild(document.createTextNode('.json_encode($text).'));'
                .'li.appendChild(a);ul.appendChild(li);'
                .'})();';
        });

        \Eventy::addAction('menu.manage.append', function () {
            $active = Str::startsWith(\Route::currentRouteName() ?? '', 'lastore.') ? 'active' : '';

            /*
             * KEIN data-menu-key.
             *
             * Das Themes-Modul sammelt mit theme-menu.js alle
             * `.navbar-nav [data-menu-key]` ein und setzt sie nach einer
             * gespeicherten Anordnung neu. Ein Schluessel, fuer den es dort
             * keinen Platz gibt, landet in der Hauptleiste - und genau das
             * passierte: der Server lieferte den Eintrag richtig im
             * Verwaltungsmenue, das Skript holte ihn wieder heraus.
             *
             * Ohne Schluessel bleibt er, wohin ihn der Haken setzt. Der Preis:
             * er ist in der Themes-Menueverwaltung nicht verschiebbar. Das ist
             * hier richtig - ein Punkt, den ein Verwalter ein paar Mal im Jahr
             * braucht, gehoert nicht in die anpassbare Hauptleiste.
             *
             * Gefunden nur, weil der Quelltext und das DOM auseinanderliefen:
             * curl und der HTTP-Kernel zeigten den Eintrag im Dropdown, der
             * Browser oben. Wer nur das DOM misst, sucht den Fehler im
             * falschen Stueck.
             */
            echo '<li class="'.$active.'"><a href="'.route('lastore.index').'">'
                .'<i class="glyphicon glyphicon-download-alt"></i> '
                .__('LaShop').'</a></li>';
        }, 24);
    }
}
