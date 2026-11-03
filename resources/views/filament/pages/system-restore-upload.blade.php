{{-- Import of the content: the uploaded export joins those on the server and is chosen in the list below. --}}
<x-admin.upload target="backup" call="backupUploaded" :label="__('admin.system.restore_file')" :hint="__('admin.system.restore_upload_hint')" />
