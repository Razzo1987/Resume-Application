{{-- Updates: upload of a package of the app (checked before the installation) or of one or more themes (installed). --}}
<x-admin.upload target="package" call="packageUploaded" :label="__('admin.updates.upload_title')" :hint="__('admin.upload.no_limit')" />
