{{-- "Vai al sito" in the top bar, between the search and the user menu: only the icon on small screens --}}
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
