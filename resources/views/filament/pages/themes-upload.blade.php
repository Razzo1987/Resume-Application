{{-- Themes: upload of a theme package (one theme or several), installed right after. --}}
<x-admin.upload target="theme" call="installUploaded" :label="__('admin.themes.package')" :hint="__('admin.upload.no_limit')" />
