import Avatar from '@/Components/Avatar';
import Button from '@/Components/Button';
import InputError from '@/Components/InputError';
import { useRequest } from '@/Hooks/useRequest';
import { PageProps } from '@/Types';
import { router, usePage } from '@inertiajs/react';
import { ChangeEvent, useState } from 'react';

/** Upload, replace or remove the profile picture. It is saved as soon as a file is chosen. */
export default function UpdateAvatarForm() {
    const { auth, errors } = usePage<PageProps & { errors: Record<string, string> }>().props;
    const user = auth.user!;
    const [uploading, setUploading] = useState(false);
    const { busy, send } = useRequest();

    const upload = (e: ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        e.target.value = '';

        if (!file) {
            return;
        }

        router.post(
            route('profile.avatar.update'),
            { avatar: file },
            { forceFormData: true, preserveScroll: true, onStart: () => setUploading(true), onFinish: () => setUploading(false) },
        );
    };

    return (
        <section>
            <header>
                <h2 className="text-lg font-semibold">Profile picture</h2>
                <p className="mt-1 text-sm text-muted">Shown beside your name. JPG, PNG or WebP, up to 2 MB.</p>
            </header>

            <div className="mt-5 flex flex-wrap items-center gap-4">
                <Avatar name={user.name} url={user.avatar_url} size="lg" />
                <div className="flex flex-wrap gap-2">
                    <label className="inline-flex min-h-10 cursor-pointer items-center rounded-lg bg-brand px-4 py-2 text-sm font-semibold whitespace-nowrap text-white hover:bg-brand-deep has-focus-visible:ring-2 has-focus-visible:ring-pencil">
                        {uploading ? 'Uploading…' : user.avatar_url ? 'Change picture' : 'Upload picture'}
                        <input type="file" accept=".jpg,.jpeg,.png,.webp" onChange={upload} disabled={uploading} className="sr-only" />
                    </label>
                    {user.avatar_url && (
                        <Button variant="secondary" disabled={busy} onClick={() => send('delete', route('profile.avatar.destroy'))}>
                            Remove
                        </Button>
                    )}
                </div>
            </div>
            <InputError message={errors.avatar} className="mt-2" />
        </section>
    );
}
