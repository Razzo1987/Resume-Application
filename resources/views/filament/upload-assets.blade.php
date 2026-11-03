{{-- Script and styles of the zip upload of the admin (x-admin.upload), on every admin page. --}}
<script>
    window.resumeUpload = (url, token, method) => ({
        over: false, busy: false, checking: false, done: false, error: null, name: null, size: 0, sent: 0,
        sizeLabel() {
            return this.size >= 1048576 ? (this.size / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(this.size / 1024)) + ' KB';
        },
        async pick(files) {
            const file = files && files[0];
            if (!file || this.busy) { return; }
            this.$refs.file.value = '';
            this.name = file.name;
            this.size = file.size;
            this.sent = 0;
            this.error = null;
            this.done = false;
            if (!/\.zip$/i.test(file.name)) { this.error = @js(__('admin.upload.not_zip')); return; }
            const chunk = 1024 * 1024;
            const count = Math.max(1, Math.ceil(file.size / chunk));
            const id = Array.from(crypto.getRandomValues(new Uint8Array(16)), (byte) => byte.toString(16).padStart(2, '0')).join('');
            const name = file.name.replace(/[^A-Za-z0-9._-]/g, '-');
            this.busy = true;
            try {
                let index = 0;
                while (index < count) {
                    const query = new URLSearchParams({ id, index, count, name });
                    const response = await fetch(url + (url.includes('?') ? '&' : '?') + query, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'X-CSRF-TOKEN': token, 'Content-Type': 'application/octet-stream', 'Accept': 'application/json' },
                        body: file.slice(index * chunk, (index + 1) * chunk),
                    });
                    const data = await response.json().catch(() => ({ error: response.status + ' ' + response.statusText }));
                    if (response.status === 409) { index = data.next; continue; }
                    if (!response.ok || data.error) { throw new Error(data.error || response.statusText); }
                    index++;
                    this.sent = Math.min(index * chunk, file.size);
                    if (data.done) {
                        this.checking = true;
                        const result = await this.$wire.$call(method, data.file);
                        if (typeof result === 'string' && result !== '') { throw new Error(result); }
                        this.done = true;
                    }
                }
            } catch (exception) {
                this.error = exception.message;
            } finally {
                this.busy = false;
                this.checking = false;
            }
        },
    });
</script>
<style>
    .resume-upload { display: grid; gap: .5rem; }
    .resume-upload-zone { position: relative; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .5rem; min-height: 4.75rem; padding: 1rem 1.25rem; border-radius: .5rem; background: rgb(249 250 251); box-shadow: 0 0 0 1px rgb(3 7 18 / .1); font-size: .875rem; text-align: center; color: rgb(75 85 99); cursor: pointer; transition: box-shadow .15s, background-color .15s; }
    .dark .resume-upload-zone { background: rgb(255 255 255 / .05); box-shadow: 0 0 0 1px rgb(255 255 255 / .2); color: rgb(156 163 175); }
    .resume-upload-zone:hover, .resume-upload-zone:focus-within, .resume-upload-zone.is-over { box-shadow: 0 0 0 2px var(--primary-500, rgb(100 116 139)); }
    .resume-upload-zone.is-busy { cursor: progress; }
    .resume-upload-zone.is-error { box-shadow: 0 0 0 1px rgb(220 38 38); }
    .resume-upload-zone.is-done { box-shadow: 0 0 0 1px rgb(22 163 74); }
    .resume-upload-input { position: absolute; width: 1px; height: 1px; opacity: 0; overflow: hidden; }
    .resume-upload-browse { text-decoration: underline; color: var(--primary-600, rgb(71 85 105)); font-weight: 500; }
    .dark .resume-upload-browse { color: var(--primary-400, rgb(148 163 184)); }
    .resume-upload-file strong { color: rgb(17 24 39); font-weight: 600; word-break: break-all; }
    .dark .resume-upload-file strong { color: rgb(255 255 255); }
    .resume-upload-progress { display: flex; align-items: center; gap: .75rem; width: 100%; max-width: 28rem; }
    .resume-upload-progress progress { flex: 1; height: .5rem; }
    .resume-upload-error { color: rgb(220 38 38); }
    .resume-upload-hint { margin: 0; font-size: .8125rem; color: rgb(107 114 128); }
    .dark .resume-upload-hint { color: rgb(156 163 175); }
</style>
