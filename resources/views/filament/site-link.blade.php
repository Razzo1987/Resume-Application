{{-- "Vai al sito" nella barra in alto, tra la ricerca e il menu utente: su schermi piccoli solo l'icona --}}
<x-filament::button
    tag="a"
    :href="$url"
    target="_blank"
    rel="noopener"
    color="gray"
    outlined
    size="sm"
    :icon="\Filament\Support\Icons\Heroicon::OutlinedArrowTopRightOnSquare"
    icon-position="after"
    labeled-from="sm"
    :tooltip="__('admin.actions.go_to_site_help')"
    class="fi-site-link"
>
    {{ __('admin.actions.go_to_site') }}
</x-filament::button>
