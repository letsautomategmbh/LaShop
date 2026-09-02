<div class="btn-toolbar margin-bottom">
    <div class="btn-group btn-group-sm">
        <a href="{{ route('lastore.index') }}" class="btn btn-default {{ Route::currentRouteName() === 'lastore.index' ? 'active' : '' }}">
            <i class="glyphicon glyphicon-th-large"></i> {{ __('Katalog') }}
        </a>
        <a href="{{ route('lastore.licenses') }}" class="btn btn-default {{ Str::startsWith(Route::currentRouteName(), 'lastore.licenses') ? 'active' : '' }}">
            <i class="glyphicon glyphicon-certificate"></i> {{ __('Lizenzen') }}
        </a>
    </div>

    @if (config('lastore.transport') === 'static')
        <span class="label label-warning" style="display:inline-block;margin-left:10px;padding:6px 10px;">
            {{ __('Abgelegte Antworten — kein Server') }}
        </span>
    @endif
</div>
